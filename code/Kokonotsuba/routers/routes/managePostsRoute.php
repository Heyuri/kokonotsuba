<?php

namespace Kokonotsuba\routers\routes;

use Kokonotsuba\board\board;
use Kokonotsuba\module_classes\moduleEngine;
use Kokonotsuba\account\staffAccountFromSession;
use Kokonotsuba\post\postRepository;
use Kokonotsuba\post\postService;
use Kokonotsuba\error\softErrorHandler;
use Kokonotsuba\action_log\actionLoggerService;
use Kokonotsuba\template\pageRenderer;
use Kokonotsuba\post\deletion\deletedPostsService;
use Kokonotsuba\policy\postRenderingPolicy;
use Kokonotsuba\userRole;
use Kokonotsuba\error\BoardException;
use function Kokonotsuba\libraries\getFiltersFromRequest;
use function Puchiko\strings\buildSmartQuery;
use function Kokonotsuba\libraries\createAssocArrayFromBoardArray;
use function Kokonotsuba\libraries\html\drawManagePostsFilterForm;
use function Kokonotsuba\libraries\_T;
use function Kokonotsuba\libraries\html\drawPager;
use function Kokonotsuba\libraries\html\generatePostNameHtml;
use function Kokonotsuba\libraries\getAttachmentUrl;
use function Kokonotsuba\libraries\attachmentFileExists;
use function Kokonotsuba\libraries\getAttachmentsFromPosts;
use function Kokonotsuba\libraries\getBoardsByUIDs;
use function Kokonotsuba\libraries\rebuildBoardsByArray;
use function Kokonotsuba\libraries\rebuildBoardsFromPosts;

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
		private readonly deletedPostsService $deletedPostsService,
		private postRenderingPolicy $postRenderingPolicy,
		private ?int $currentUserId
	) {}

	public function drawManagePostsPage() {
		// Validate user permissions
		$this->softErrorHandler->handleAuthError($this->config['AuthLevels']['CAN_MANAGE_POSTS']);
		
		// Initialize page context and filters
		$context = $this->initializePageContext();
		
		// Handle post deletion if form was submitted
		$this->handlePostDeletion($context);

		// Fetch filtered posts from database
		$posts = $this->fetchFilteredPosts($context);
		
		// Build board mapping and related data
		$boardMap = $this->buildBoardMap();
		$boardList = $this->buildBoardList();
		
		// Render the manage posts page
		$managePostsHtml = $this->renderManagePostsPage($posts, $boardMap, $boardList, $context);
		
		// Output final HTML
		$this->outputPageContent($managePostsHtml);
	}

	private function initializePageContext(): array {
		$isSubmission = isset($_GET['filterSubmissionFlag']);
		$managePostsUrl = $this->board->getBoardURL(true) . '?mode=managePosts';
		$accountId = $this->currentUserId;
		
		$defaultFilters = $this->initializeManagePostsFilters();
		$filtersFromRequest = getFiltersFromRequest($managePostsUrl, $isSubmission, $defaultFilters);
		
		// Keep form filters clean - don't expose the resolved IP
		$formFilters = $filtersFromRequest;
		
		// Create query filters with resolved postsFrom for accurate results
		$queryFilters = $filtersFromRequest;
		$postsFrom = $_GET['postsFrom'] ?? null;
		if($postsFrom && is_numeric($postsFrom)) {
			$queryFilters['ip_address'] = $this->postRepository->resolveHostFromPostUid((int)$postsFrom);
		}
		
		$cleanUrl = buildSmartQuery($managePostsUrl, $defaultFilters, $formFilters, true);
		
		// Append postsFrom to cleanUrl if it exists (keep URL clean - don't expose IP)
		if($postsFrom) {
			$cleanUrl .= '&postsFrom=' . urlencode($postsFrom);
		}
		
		$roleLevel = $this->staffAccountFromSession->getRoleLevel();
		$canViewIp = $roleLevel->isAtLeast($this->board->getConfigValue('AuthLevels.CAN_VIEW_IP_ADDRESSES', userRole::LEV_JANITOR));
		$canViewDeleted = $this->postRenderingPolicy->viewDeleted();
		
		$page = (int) ($_REQUEST['page'] ?? 0);
		if ($page < 0) $page = 1;
		
		return [
			'managePostsUrl' => $managePostsUrl,
			'accountId' => $accountId,
			'formFilters' => $formFilters,
			'queryFilters' => $queryFilters,
			'cleanUrl' => $cleanUrl,
			'roleLevel' => $roleLevel,
			'canViewIp' => $canViewIp,
			'canViewDeleted' => $canViewDeleted,
			'page' => $page,
			'postsPerPage' => $this->config['ADMIN_PAGE_DEF'],
			'numberOfFilteredPosts' => $this->postRepository->postCount($queryFilters)
		];
	}

	private function handlePostDeletion(array $context): void {
		// Check if delete form was submitted
		$postUidsFromCheckbox = $_POST['clist'] ?? [];
		if (!$postUidsFromCheckbox) {
			return;
		}
		
		$onlyDeleteImages = !empty($_POST['onlyimgdel']);
		$this->deletePostsFromCheckboxes($postUidsFromCheckbox, $onlyDeleteImages, $context['accountId']);
	}

	private function fetchFilteredPosts(array $context): array {
		// Fetch and return the filtered posts using query filters
		return $this->postRepository->getFilteredPosts(
			$context['postsPerPage'],
			$context['page'] * $context['postsPerPage'],
			$context['queryFilters'],
			$context['canViewDeleted']
		) ?: [];
	}

	private function buildBoardMap(): array {
		$boardMap = [];
		foreach (GLOBAL_BOARD_ARRAY as $board) {
			$boardMap[$board->getBoardUID()] = $board;
		}
		return $boardMap;
	}

	private function buildBoardList(): string {
		$allBoardUids = array_map(fn($board) => $board->getBoardUID(), $this->allRegularBoards);
		return implode('+', $allBoardUids);
	}

	private function renderManagePostsPage(array $posts, array $boardMap, string $boardList, array $context): string {
		$html = '';
		
		// Render filter form
		drawManagePostsFilterForm($html, $this->board, $context['formFilters'], createAssocArrayFromBoardArray($this->allRegularBoards));
		
		// Render posts table
		$html .= $this->renderPostsTable($posts, $boardMap, $boardList, $context);
		
		// Render action buttons and pagination
		$html .= $this->renderPostsTableFooter();
		$html .= drawPager($context['postsPerPage'], $context['numberOfFilteredPosts'], $context['cleanUrl']);
		
		return $html;
	}

	private function renderPostsTable(array $posts, array $boardMap, string $boardList, array $context): string {
		$html = '<form id="managePostsForm" action="' . htmlspecialchars($context['cleanUrl']) . '" method="POST">';
		$html .= '<input type="hidden" name="mode" value="admin">
					<div id="tableManagePostsContainer">
						<table id="tableManagePosts" class="postlists">
							<thead>
								<tr>' . _T('admin_list_header') . '</tr>
							</thead>
							<tbody>';
		
		// Render posts or empty state message
		if ($posts && is_array($posts)) {
			$html .= $this->renderPostEntries($posts, $boardMap, $context['canViewIp'], $context['managePostsUrl'], $boardList);
		} else {
			$html .= '<tr><td colspan="9"><b class="error" id="no-posts-found"> - No posts found! - </b></td></tr>';
		}
		
		$html .= '</tbody></table></div>';
		return $html;
	}

	private function renderPostsTableFooter(): string {
		return '
		<div class="buttonSection">
			<button type="button" id="selectAllButton" onclick="selectAll()">Select all</button>
			<input type="submit" value="' . _T('admin_submit_btn') . '"> <input type="reset" value="' . _T('admin_reset_btn') . '"> [<label><input type="checkbox" name="onlyimgdel" id="onlyimgdel" value="on">' . _T('del_img_only') . '</label>]
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
	</script>';
	}

	private function outputPageContent(string $managePostsHtml): void {
		$templateValues = [
			'{$PAGE_CONTENT}' => $managePostsHtml,
			'{$PAGER}' => ''
		];
		
		$htmlOutput = $this->adminPageRenderer->ParsePage('GLOBAL_ADMIN_PAGE_CONTENT', $templateValues, true);
		echo $htmlOutput;
	}

	private function initializeManagePostsFilters(): array {
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
			'postsFrom' => ''
		];
	}

	private function renderPostEntries(
		array $posts, 
		array $boardMap, 
		bool $canViewIp, 
		string $managePostsUrl, 
		string $boardList
	): string {
		$postsHtml = '';

		foreach($posts as $post) {
			$postsHtml .= $this->renderPostEntry(
				$post, 
				$boardMap, 
				$canViewIp, 
				$managePostsUrl, 
				$boardList
			);
		}

		return $postsHtml;
	}

	private function renderPostEntry(
		array $post,
		array $boardMap,
		bool $canViewIp,
		string $managePostsUrl,
		string $boardList
	): string {
		$boardUID = $post['boardUID'];

		if(!isset($boardMap[$boardUID])){
			return '';
		}

		$postBoard = $boardMap[$boardUID];
		$postBoardConfig = $postBoard->loadBoardConfig();

		// Prepare post data
		$name = substr($post['name'], 0, 500);
		$sub = substr($post['sub'], 0, 500);
		$com = $post['com'];
		$post_uid = $post['post_uid'];
		$no = $post['no'];
		$now = $post['now'];
		$is_op = $post['is_op'];

		// Handle host display
		$host = $canViewIp
			? $post['host']
			: substr(md5($post['host']), 0, 8);

		// Generate name HTML
		$nameHtml = generatePostNameHtml(
			$this->moduleEngine, 
			$name, 
			$post['tripcode'], 
			$post['secure_tripcode'], 
			$post['capcode'], 
			$post['email'],
			$this->config['NOTICE_SAGE']
		);

		// Build module functions
		$modFunc = ' ';
		if($is_op) {
			$this->moduleEngine->dispatch('ManagePostsThreadControls', [&$modFunc, &$post]);
		} else {
			$this->moduleEngine->dispatch('ManagePostsReplyControls', [&$modFunc, &$post]);
		}

		$this->moduleEngine->dispatch('ManagePostsControls', [&$modFunc, &$post]);
		$this->moduleEngine->dispatch('PostComment', [&$com, &$post]);

		// Generate attachments HTML
		$attachmentsHtml = $this->renderAttachments($post['attachments']);

		// Build and return the table row
		return '
			<tr>
				<td class="colFunc">' . $modFunc . '</td>
				<td class="colDel"><input type="checkbox" name="clist[]" value="' . $post_uid . '"><a target="_blank" href="'.$postBoard->getBoardURL().$postBoardConfig['LIVE_INDEX_FILE'].'?res=' . $no . '">' . $no . '</a></td>
				<td class="colBoard">/' . $postBoard->getBoardIdentifier() . '/ ('.$postBoard->getBoardUID().')</td>
				<td class="colDate"><span class="time">' . $now . '</span></td>
				<td class="colSub"><span class="title">' . $sub . '</span></td>
				<td class="colName"><span class="name">' . $nameHtml . '</span></td>
				<td class="colComment"><div class="managepostsCommentWrapper">' . $com . '</div></td>
				<td class="colHost">' . ($canViewIp ? $host . ' <a target="_blank" href="https://whatismyipaddress.com/ip/' . $host . '" title="Resolve hostname"><img height="12" src="' . $this->config['STATIC_URL'] . 'image/glass.png"></a> <a href="'.$managePostsUrl.'&ip_address=' . $host . '&board=' . $boardList . '" title="See all posts">★</a>' : $host) . '</td>
				' . $attachmentsHtml . '
			</tr>';
	}

	private function renderAttachments(array $attachments): string {
		// if there are no attachments, return the placeholder html
		if(empty($attachments)) {
			return '<td class="colImage">---</td>';
		}
		
		// html string to hold the attachments html, will be concatenated with each attachment's html in the loop below
		$attachmentsHtml = '';

		// loop through attachments and render each one, concatenating the html for each into a single string
		foreach($attachments as $att) {
			if(empty($att)) {
				continue;
			}

			$attachmentsHtml .= $this->renderAttachmentEntry($att);
		}

		// return the attachments html wrapped in the table cell
		return '<td class="colImage">' . $attachmentsHtml . '</td>';
	}

	private function renderAttachmentEntry(array $attachment): string {
		$fileMd5 = $attachment['fileMd5'];
		$fileExtension = $attachment['fileExtension'];
		$storedFileName = $attachment['storedFileName'];
		$size = $attachment['fileSize'];

		$clip = '<a href="'. getAttachmentUrl($attachment).'" target="_blank">' . $storedFileName . '.' . $fileExtension . '</a>';
		$attachmentInfoEntry = '<div class="attachmentListEntry">' . $clip . ' (' . $size . ')<br>' . $fileMd5 . '</div>';

		if(!attachmentFileExists($attachment)) {
			$attachmentInfoEntry = '<s title="Attachment file doesn\'t exist">' . $attachmentInfoEntry . '</s>';
		}
	
		return $attachmentInfoEntry;
	}
	
	private function deletePostsFromCheckboxes(array $postUids, bool $onlyDeleteImages, int $accountId): void {
		// Fetch post data for deletion
		$postsData = $this->postService->getPostsByUids($postUids);
		
		// Validate posts exist
		if(!$postsData) {
			throw new BoardException("Posts not found while deleting!");
		}

		// Extract attachments and post numbers for logging
		$attachments = getAttachmentsFromPosts($postsData);
		$postNumbers = array_column($postsData, 'no');
		$checkboxDeletionActionLogStr = is_array($postNumbers) ? implode(', No. ',$postNumbers) : $postNumbers;

		// Delete only files or entire posts based on user selection
		if($onlyDeleteImages) {
			$this->deletedPostsService->deleteFilesFromPosts($attachments, $accountId);
		} else {
			$this->postService->removePosts($postUids, $accountId);
		}

		// Rebuild affected boards after deletion
		rebuildBoardsFromPosts($postUids, $this->postService);

		// Record deletion action in logs
		$this->actionLoggerService->logAction("Delete posts: $checkboxDeletionActionLogStr".($onlyDeleteImages?' (file only)':''), $this->board->getBoardUID());
	}
}
