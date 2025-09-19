<?php

use Kokonotsuba\Root\Constants\userRole;

class managePostsRoute {
	public function __construct(
		private board $board,
		private readonly array $config,
		private moduleEngine $moduleEngine,
		private readonly boardService $boardService,
		private readonly staffAccountFromSession $staffAccountFromSession,
		private readonly postRedirectService $postRedirectService,
		private readonly postRepository $postRepository,
		private readonly postService $postService,
		private readonly softErrorHandler $softErrorHandler,
		private mixed $FileIO,
		private readonly actionLoggerService $actionLoggerService,
		private attachmentService $attachmentService,
		private readonly pageRenderer $adminPageRenderer,
		private readonly array $allRegularBoards
	) {}

	public function drawManagePostsPage() {
		$this->softErrorHandler->handleAuthError($this->config['AuthLevels']['CAN_MANAGE_POSTS']);
		
		$isSubmission = isset($_GET['filterSubmissionFlag']);
		
		$managePostsUrl = $this->board->getBoardURL(true) . '?mode=managePosts';

		//filter list for the database
		$defaultFilters = $this->initializeManagePostsFilters();
		
		$filtersFromRequest = getFiltersFromRequest($managePostsUrl, $isSubmission, $defaultFilters);
		$cleanUrl = buildSmartQuery($managePostsUrl, $defaultFilters, $filtersFromRequest, true);

		$roleLevel = $this->staffAccountFromSession->getRoleLevel();

		// can delete all
		$canDeleteAll = $this->board->getConfigValue('AuthLevels.CAN_DELETE_ALL', userRole::LEV_MODERATOR);

		// whether the user can view all deleted posts
		$canViewDeleted = $roleLevel->isAtLeast($canDeleteAll);
		 
		$postsPerPage = $this->config['ADMIN_PAGE_DEF'];
		$numberOfFilteredPosts = $this->postRepository->postCount($filtersFromRequest);
		$page = $_REQUEST['page'] ?? 0;

		if (!filter_var($page, FILTER_VALIDATE_INT) && $page != 0) {
			$this->softErrorHandler->errorAndExit("Page number was not a valid int.");
		}

		$page = ($page >= 0) ? $page : 1;

		$onlyimgdel = !empty($_POST['onlyimgdel']); // Only delete the image
		$modFunc = '';
		$host = $_GET['host'] ?? 0;
		$posts = array(); //posts to display in the manage posts table
		
		// Delete the article(thread) block
		$postUidsFromCheckbox = $_POST['clist'] ?? array();
		if($postUidsFromCheckbox) {
			$this->deletePostsFromCheckboxes($postUidsFromCheckbox, $onlyimgdel);
			$this->board->rebuildBoard();
		}
		
		$posts = $this->postRepository->getFilteredPosts($postsPerPage, $page * $postsPerPage, $filtersFromRequest, $canViewDeleted) ?? array();
		$posts_count = count($posts); // Number of cycles
		
		// get the associate array for the checkbox generator
		$arrayForFilter = createAssocArrayFromBoardArray($this->allRegularBoards);
		
		// draw post filter form
		drawManagePostsFilterForm($managePostsHtml, $this->board, $filtersFromRequest, $arrayForFilter);
		
		$managePostsHtml .= '<form id="managePostsForm" action="' . $cleanUrl . '" method="POST">';
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

		for($j = 0; $j < $posts_count; $j++) {
			extract($posts[$j]);
			
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

			$nameHtml = generatePostNameHtml($this->config['staffCapcodes'], $this->config['CAPCODES'], $name, $tripcode, $secure_tripcode, $capcode, $email);

			// The first part of the discussion is the stop tick box and module function
			$modFunc = ' ';

			if($is_op) {
				$this->moduleEngine->dispatch('ThreadAdminControls', [&$modFunc, &$posts[$j]]);
			} else {
				$this->moduleEngine->dispatch('ReplyAdminControls', [&$modFunc, &$posts[$j]]);
			}

			// dispatch post admin hook point
			$this->moduleEngine->dispatch('PostAdminControls', [&$modFunc, &$posts[$j]]);

			// Extract additional archived image files and generate a link
			if($ext && $this->FileIO->imageExists($tim.$ext, $postBoard)){
				$clip = '<a href="'.$this->FileIO->getImageURL($tim.$ext, $postBoard).'" target="_blank">'.$tim.$ext.'</a>';
				$size = $this->FileIO->getImageFilesize($tim.$ext, $postBoard);
				$thumbName = $this->FileIO->resolveThumbName($tim, $postBoard);
				if($thumbName != false) $size += $this->FileIO->getImageFilesize($thumbName, $postBoard);
			}else{
				$clip = $md5chksum = '--';
				$size = 0;
			}

			if($roleLevel->isAtMost(\Kokonotsuba\Root\Constants\userRole::LEV_JANITOR)) {
				$host = substr(hash('sha256', $host), 0, 10);
			}

			// Print out the interface
			$managePostsHtml .= '
				<tr>
					<td class="colFunc">' . $modFunc . '</td>
					<td class="colDel"><input type="checkbox" name="clist[]" value="' . $post_uid . '"><a target="_blank" href="'.$postBoard->getBoardURL().$postBoardConfig['LIVE_INDEX_FILE'].'?res=' . $no . '">' . $no . '</a></td>
					<td class="colBoard">/' . $postBoard->getBoardIdentifier() . '/ ('.$postBoard->getBoardUID().')</td>
					<td class="colDate"><span class="time">' . $now . '</span></td>
					<td class="colSub"><span class="title">' . $sub . '</span></td>
					<td class="colName"><span class="name">' . $nameHtml . '</span></td>
					<td class="colComment"><div class="managepostsCommentWrapper">' . $com . '</div></td>
					<td class="colHost">' . $host . ' <a target="_blank" href="https://whatismyipaddress.com/ip/' . $host . '" title="Resolve hostname"><img height="12" src="' . $this->config['STATIC_URL'] . 'image/glass.png"></a> <a href="'.$managePostsUrl.'&ip_address=' . $host . '&board=' . $boardList . '" title="See all posts">★</a></td>
					<td class="colImage">' . $clip . ' (' . $size . ')<br>' . $md5chksum . '</td>
				</tr>';
		}

		
		if(!$posts) {
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

	private function deletePostsFromCheckboxes(array $postUids, bool $onlyDeleteImages): void {
		$postsData = $this->postService->getPostsByUids($postUids);
		$postNumbers = array_column($postsData, 'no');

		$checkboxDeletionActionLogStr = is_array($postNumbers) ? implode(', No. ',$postNumbers) : $postNumbers;
		$this->actionLoggerService->logAction("Delete posts: $checkboxDeletionActionLogStr".($onlyDeleteImages?' (file only)':''), $this->board->getBoardUID());

		if($onlyDeleteImages) {
			$files = $this->attachmentService->removeAttachments($postUids);

			if ($files) {
				$this->FileIO->deleteImage($files, $this->board);
			}
		} else {
			$this->postService->removePosts($postUids);
		}
	}
}
