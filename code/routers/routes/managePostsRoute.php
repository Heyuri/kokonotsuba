<?php

use Kokonotsuba\Root\Constants\userRole;

class managePostsRoute {
	public function __construct(
		private board $board,
		private readonly array $config,
		private moduleEngine $moduleEngine,
		private readonly staffAccountFromSession $staffAccountFromSession,
		private readonly postRepository $postRepository,
		private readonly postService $postService,
		private readonly softErrorHandler $softErrorHandler,
		private readonly actionLoggerService $actionLoggerService,
		private readonly pageRenderer $adminPageRenderer,
		private readonly array $allRegularBoards,
		private readonly deletedPostsService $deletedPostsService
	) {}

	public function drawManagePostsPage() {
		$this->softErrorHandler->handleAuthError($this->config['AuthLevels']['CAN_MANAGE_POSTS']);
		
		$isSubmission = isset($_GET['filterSubmissionFlag']);
		
		$managePostsUrl = $this->board->getBoardURL(true) . '?mode=managePosts';

		// account id of the current user (can be null)
		$accountId = getIdFromSession();

		//filter list for the database
		$defaultFilters = $this->initializeManagePostsFilters();
		
		$filtersFromRequest = getFiltersFromRequest($managePostsUrl, $isSubmission, $defaultFilters);
		$cleanUrl = buildSmartQuery($managePostsUrl, $defaultFilters, $filtersFromRequest, true);

		$roleLevel = $this->staffAccountFromSession->getRoleLevel();

		// can delete all
		$canDeleteAll = $this->board->getConfigValue('AuthLevels.CAN_DELETE_ALL', userRole::LEV_MODERATOR);

		// whether the user can view all deleted posts
		$canViewDeleted = $roleLevel->isAtLeast($canDeleteAll);

		// required level for viewing IPs
		$canViewIpAddresses = $this->board->getConfigValue('AuthLevels.CAN_VIEW_IP_ADDRESSES', userRole::LEV_JANITOR);

		// can view ip
		$canViewIp = $roleLevel->isAtLeast($canViewIpAddresses);
		 
		$postsPerPage = $this->config['ADMIN_PAGE_DEF'];
		$numberOfFilteredPosts = $this->postRepository->postCount($filtersFromRequest);
		$page = $_REQUEST['page'] ?? 0;

		if (!filter_var($page, FILTER_VALIDATE_INT) && $page != 0) {
			$this->softErrorHandler->errorAndExit("Page number was not a valid int.");
		}

		$page = ($page >= 0) ? $page : 1;

		$onlyimgdel = !empty($_POST['onlyimgdel']); // Only delete the image
		$posts = array(); //posts to display in the manage posts table
		
		// Delete the article(thread) block
		$postUidsFromCheckbox = $_POST['clist'] ?? [];
		if($postUidsFromCheckbox) {
			$this->deletePostsFromCheckboxes($postUidsFromCheckbox, $onlyimgdel, $accountId);
			$this->board->rebuildBoard();
		}
		
		$posts = $this->postRepository->getFilteredPosts($postsPerPage, $page * $postsPerPage, $filtersFromRequest, $canViewDeleted) ?? [];
		
		// get the associate array for the checkbox generator
		$arrayForFilter = createAssocArrayFromBoardArray($this->allRegularBoards);
		
		// draw post filter form
		drawManagePostsFilterForm($managePostsHtml, $this->board, $filtersFromRequest, $arrayForFilter);
		
		$managePostsHtml .= '<form id="managePostsForm" action="' . htmlspecialchars($cleanUrl) . '" method="POST">';
		$managePostsHtml .= '<input type="hidden" name="mode" value="admin">
						<div id="tableManagePostsContainer">
							<table id="tableManagePosts" class="postlists">
								<thead>
									<tr>'._T('admin_list_header').'</tr>
								</thead>
								<tbody>';
		
		// Eager load all boards first
		$allBoards = GLOBAL_BOARD_ARRAY;

		$allBoardUids = array_map(fn($board) => $board->getBoardUID(), $this->allRegularBoards);;
		$boardList = implode('+', $allBoardUids);

		$boardMap = array();
		foreach($allBoards as $board){
			$boardMap[$board->getBoardUID()] = $board;
		}

		// if there's any posts - render them
		if($posts && is_array($posts)) {
			$managePostsHtml .= $this->renderPostEntries(
				$posts, 
				$boardMap, 
				$canViewIp, 
				$roleLevel, 
				$managePostsUrl,
				$boardList
			);
		} 
		// otherwise display error since there's no (valid) posts found
		else {
			$managePostsHtml .= '
				<tr>
					<td colspan="9"><b class="error" id="no-posts-found"> - No posts found! - </b></td>
				</tr>';
		}
		
		$managePostsHtml.= '
			</tbody>
		</table>
		</div>
		<div class="buttonSection">
			<button type="button" id="selectAllButton" onclick="selectAll()">Select all</button>
			<input type="submit" value="'._T('admin_submit_btn').'"> <input type="reset" value="'._T('admin_reset_btn').'"> [<label><input type="checkbox" name="onlyimgdel" id="onlyimgdel" 		value="on">'._T('del_img_only').'</label>]
		</div>
	</form>
	<script>
		function selectAll() {
			var checkboxes = document.querySelectorAll(\'input[name="clist[]"]\');
			var btn = document.getElementById(\'selectAllButton\');
			var allChecked = Array.from(checkboxes).every(function(checkbox) {
				return checkbox.checked;
			});
			
			checkboxes.forEach(function(checkbox) {
				checkbox.checked = !allChecked;
			});

			btn.textContent = allChecked ? "Select all" : "Unselect all";
		}
	</script>
	';

		$managePostsPager = drawPager($postsPerPage, $numberOfFilteredPosts, $cleanUrl);

		$templateValues = [
			'{$PAGE_CONTENT}' => $managePostsHtml,
			'{$PAGER}' => $managePostsPager
		];
		
		$htmlOutput = $this->adminPageRenderer->ParsePage('GLOBAL_ADMIN_PAGE_CONTENT', $templateValues, true);
	
		echo $htmlOutput;
	}

	private function initializeManagePostsFilters(): array {
		// Default board selection: current board and global board
		$defaultSelectedBoards = [$this->board->getBoardUID()];

		return [
			'ip_address' => '',
			'post_name' => '',
			'tripcode' => '',
			'capcode' => '',
			'subject' => '',
			'comment' => '',
			'board' => $defaultSelectedBoards,
			'date_before' => '',
			'date_after' => '',
			'host' => ''
		];
	}

	private function renderPostEntries(
		array $posts, 
		array $boardMap, 
		bool $canViewIp, 
		userRole $roleLevel, 
		string $managePostsUrl, 
		string $boardList
	): string {
		// init html stored var
		$postsHtml = '';

		// loop through posts
		foreach($posts as $p) {		
			// get the board uid
			$boardUID = $p['boardUID'];
			
			// get tripcode
			$tripcode = $p['tripcode'];
			
			// get the secure tripcode
			$secure_tripcode = $p['secure_tripcode'];

			// get the capcode
			$capcode = $p['capcode'];

			// get the email
			$email = $p['email'];

			// get is_op condition
			$is_op = $p['is_op'];

			// get host
			// hash if the user can't view IPs
			$host = $canViewIp
				? $p['host']
				: substr(md5($p['host']), 0, 8);

			// get comment
			$com = $p['com'];

			// get post uid
			$post_uid = $p['post_uid'];

			// get now
			$now = $p['now'];

			// get no
			$no = $p['no'];

			// get sub
			$sub = $p['sub'];

			// get name
			$name = $p['name'];

			// post board (from preloaded boards)
			if(isset($boardMap[$boardUID])){
				$postBoard = $boardMap[$boardUID];
			}else{
				continue; // Skip if board not found
			}
			$postBoardConfig = $postBoard->loadBoardConfig();
		
			// Modify the field style
			$name = substr($name, 0, 500);
			$sub = substr($sub, 0, 500);
			// $com = substr($com, 0, 500);

			$nameHtml = generatePostNameHtml(
				$this->moduleEngine,  
				$name, 
				$tripcode, 
				$secure_tripcode, 
				$capcode, 
				$email,
				$this->config['NOTICE_SAGE']
			);

			// The first part of the discussion is the stop tick box and module function
			$modFunc = ' ';

			if($is_op) {
				$this->moduleEngine->dispatch('ManagePostsThreadControls', [&$modFunc, &$p]);
			} else {
				$this->moduleEngine->dispatch('ManagePostsReplyControls', [&$modFunc, &$p]);
			}

			// dispatch post admin hook point
			$this->moduleEngine->dispatch('ManagePostsControls', [&$modFunc, &$p]);

			if($roleLevel->isAtMost(\Kokonotsuba\Root\Constants\userRole::LEV_JANITOR)) {
				$host = substr(hash('sha256', $host), 0, 10);
			}

			// generate attachments info html
			$attachmentsHtml = $this->renderAttachments($p['attachments']);

			// Print out the interface
			$postsHtml .= '
				<tr>
					<td class="colFunc">' . $modFunc . '</td>
					<td class="colDel"><input type="checkbox" name="clist[]" value="' . $post_uid . '"><a target="_blank" href="'.$postBoard->getBoardURL().$postBoardConfig['LIVE_INDEX_FILE'].'?res=' . $no . '">' . $no . '</a></td>
					<td class="colBoard">/' . $postBoard->getBoardIdentifier() . '/ ('.$postBoard->getBoardUID().')</td>
					<td class="colDate"><span class="time">' . $now . '</span></td>
					<td class="colSub"><span class="title">' . $sub . '</span></td>
					<td class="colName"><span class="name">' . $nameHtml . '</span></td>
					<td class="colComment"><div class="managepostsCommentWrapper">' . $com . '</div></td>
					<td class="colHost">' . $host . ' <a target="_blank" href="https://whatismyipaddress.com/ip/' . $host . '" title="Resolve hostname"><img height="12" src="' . $this->config['STATIC_URL'] . 'image/glass.png"></a> <a href="'.$managePostsUrl.'&ip_address=' . $host . '&board=' . $boardList . '" title="See all posts">★</a></td>
					' . $attachmentsHtml . '
				</tr>';
		}

		// then return the html
		return $postsHtml;
	}

	private function renderAttachments(array $attachments): string {
		// return default no-file attachment row
		if(empty($attachments)) {
			return '<td class="colImage">---</td>';
		}
		
		// init attachments html
		$attachmentsHtml = '';

		// loop through attachments and 
		foreach($attachments as $att) {
			// continue early if empty
			// probably invalid / a bug if that happens but may as well prevent error page on mod tools in case of failing at critial times
			if(empty($att)) {
				continue;
			}

			// get md5 hash
			$fileMd5 = $att['fileMd5'];

			// get file extension
			$fileExtension = $att['fileExtension'];

			// get stored file name
			$storedFileName = $att['storedFileName'];

			// Extract additional archived image files and generate a link
			$clip = '<a href="'. getAttachmentUrl($att).'" target="_blank">' . $storedFileName . '.' . $fileExtension . '</a>';
			$size = $att['fileSize'];

			// then put together the attachments list
			$attachmentInfoEntry = '<div class="attachmentListEntry">' . $clip . ' (' . $size . ')<br>' . $fileMd5 . '</div>';

			// wrap in strike-through if the attachment doesn't exist
			if(!attachmentFileExists($att)) {
				$attachmentInfoEntry = '<s title="Attachment file doesn\'t exist">' . $attachmentInfoEntry . '</s>';
			}
		
			// append to attachments html
			$attachmentsHtml .= $attachmentInfoEntry;
		}

		// wrap html in td
		$attachmentsHtml = '<td class="colImage">' . $attachmentsHtml . '</td>';

		// return attachments html
		return $attachmentsHtml;
	}
	
	private function deletePostsFromCheckboxes(array $postUids, bool $onlyDeleteImages, int $accountId): void {
		// fetch data for selected posts
		$postsData = $this->postService->getPostsByUids($postUids);
		
		// no post data found
		if(!$postsData) {
			throw new BoardException("Posts not found while deleting!");
		}

		// get the attachments
		$attachments = getAttachmentsFromPosts($postsData);

		$postNumbers = array_column($postsData, 'no');

		$checkboxDeletionActionLogStr = is_array($postNumbers) ? implode(', No. ',$postNumbers) : $postNumbers;

		if($onlyDeleteImages) {
			$this->deletedPostsService->deleteFilesFromPosts($attachments, $accountId);
		} else {
			$this->postService->removePosts($postUids, $accountId);
		}

		$this->actionLoggerService->logAction("Delete posts: $checkboxDeletionActionLogStr".($onlyDeleteImages?' (file only)':''), $this->board->getBoardUID());
	}
}
