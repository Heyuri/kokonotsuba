<?php

namespace Kokonotsuba\Modules\deletedPosts;

use BoardException;
use Kokonotsuba\ModuleClasses\abstractModuleAdmin;
use Kokonotsuba\Root\Constants\userRole;
use postRenderer;
use RuntimeException;
use staffAccountFromSession;
use templateEngine;
use threadRenderer;

class moduleAdmin extends abstractModuleAdmin {
	// property to store the url of the module
	private string $myPage;

	// template engine property used for rendering posts
	private templateEngine $moduleTemplateEngine;
	
	// property for the role required to modify all deleted posts
	private userRole $requiredRoleForAll;

	public function getRequiredRole(): userRole {
		return $this->getConfig('AuthLevels.CAN_DELETE_POST');
	}

	public function getName(): string {
		return 'Deleted posts mod page';
	}

	public function getVersion(): string {
		return 'Koko 2025';
	}

	public function initialize(): void {
		// initialize role property
		$this->requiredRoleForAll = $this->getConfig('AuthLevels.CAN_DELETE_ALL');

		// initialize url
		$this->myPage = $this->getModulePageURL([], false);

		// init the module template engine
		$this->moduleTemplateEngine = $this->initModuleTemplateEngine('ModuleSettings.DELETED_POSTS_TEMPLATE', 'kokoimg.tpl');

		$this->moduleContext->moduleEngine->addRoleProtectedListener(
			$this->getRequiredRole(),
			'LinksAboveBar',
			function(string &$linkHtml) {
				$this->onRenderLinksAboveBar($linkHtml);
			}
		);

		$this->moduleContext->moduleEngine->addRoleProtectedListener(
			$this->getRequiredRole(),
			'PostAdminControls',
			function(string &$modControlSection, array &$post) {
				$this->onRenderPostAdminControls($modControlSection, $post);
			}
		);

		$this->moduleContext->moduleEngine->addRoleProtectedListener(
			$this->getRequiredRole(),
			'Post',
			function(&$arrLabels, $post, $threadPosts, $board) {
				$this->onRenderPost($arrLabels, $post);
			}
		);

		$this->moduleContext->moduleEngine->addRoleProtectedListener(
			$this->getRequiredRole(),
			'AttachmentCssClass',
			function(&$postCssClasses, &$post, $isLiveFrontend) {
				$this->onRenderAttachmentCssClass($postCssClasses, $post, $isLiveFrontend);
			}
		);

		$this->moduleContext->moduleEngine->addRoleProtectedListener(
			$this->getRequiredRole(),
			'PostCssClass',
			function(&$postCssClasses, $post) {
				$this->onRenderPostCssClass($postCssClasses, $post);
			}
		);

		$this->moduleContext->moduleEngine->addRoleProtectedListener(
			$this->getRequiredRole(),
			'ThreadCssClass',
			function(&$threadCssClasses, $thread) {
				$this->onRenderThreadCssClass($threadCssClasses, $thread);
			}
		);

		$this->moduleContext->moduleEngine->addRoleProtectedListener(
			$this->getRequiredRole(),
			'ModuleHeader',
			function(&$moduleHeader) {
				$this->onGenerateModuleHeader($moduleHeader);
			}
		);
	}

	private function onRenderLinksAboveBar(string &$linkHtml): void {
		// modify the "links above bar" html to have a [Deleted Posts] button
		$linkHtml .= '<li class="adminNavLink"><a title="Manage posts that have been deleted" href="' . htmlspecialchars($this->myPage) . '">Manage deleted posts</a></li>';
	}

	private function onRenderPostAdminControls(string &$modFunc, array &$post): void {
		// whether the post is deleted or not
		$isPostDeleted = $this->isPostDeleted($post);

		// don't bother if the post isn't deleted
		if(!$isPostDeleted) {
			return;
		}

		// is this being viewed from the module page?
		$isModulePage = $this->isModulePage();

		// if this the module page, then return coz it should render like normal here
		if($isModulePage) {
			return;
		}

		// whether only the file was deleted
		$onlyFileDeleted = $post['file_only_deleted'] ?? 0;

		// render the <a> button to take the user to the entry in the module
		$modFunc .= $this->adminPostViewModuleButton($post);

		// render the [DELETED] indicator
		$modFunc .= $this->renderDeletionIndicator($onlyFileDeleted);
	}

	private function isPostDeleted(array $post): bool {
		// has a value of 1 if the post is deleted
		$openFlag = $post['open_flag'] ?? 0;

		// return true if its value is 1
		if((int)$openFlag === 1) {
			return true;
		} 
		// not deleted / restored
		else {
			return false;
		}
	}

	private function isModulePage(): bool {
		// get current module
		$loadedModule = $_REQUEST['load'] ?? '';

		// return true if its the module
		if($loadedModule === 'deletedPosts') {
			return true;
		}
		// return false otherwise
		else {
			return false;
		}

	}

	private function adminPostViewModuleButton(array $post): string {
		// whether we're viewing from the module page
		$isModulePage = $this->isModulePage();

		// don't display it if we're in the module view - 
		// coz we can already see all the infos or we're already on the page it'd take the user to
		if($isModulePage) {
			return '';
		}

		// is a reply of a deleted thread
		$byProxy = $post['by_proxy'] ?? 0;

		// also don't display it if the post is only deleted by proxy
		// replies of deleted threads aren't meant to be view or changed individually
		// in other words, they're bound to whatever action happens to the OP post
		// e.g, OP purged = reply also purged
		if($byProxy) {
			return '';
		}

		// get the deleted post id
		$deletedPostId = $post['deleted_post_id'];
		
		// url parameters
		$urlParameters = [
			'action' => 'viewMore',
			'deletedPostId' => $deletedPostId,
		];

		// get url
		$modulePageUrl = $this->getModulePageURL($urlParameters,
			false,
			true
		);

		// render the html
		$buttonUrl = '<span class="adminFunctions adminViewDeletedPostFunction">[<a href="' . htmlspecialchars($modulePageUrl) . '" title="View deleted post">VD</a>]</span> ';

		// return string
		return $buttonUrl;
	}

	private function renderDeletionIndicator(bool $onlyFileDeleted): string {
		// generate message for file-only
		if($onlyFileDeleted) {
			// message for within the square brackets
			$message = "FILE DELETED";

			// the title
			$spanTitle = "This post's file was deleted";
		} 
		// default - post was simply deleted
		else {
			$message = "DELETED";

			$spanTitle = "This post was deleted";
		}

		// return html
		return '<span class="warning" title="' . htmlspecialchars($spanTitle) . '">[' . htmlspecialchars($message) . ']</span>';
	}

	private function onRenderPost(array &$templateValues, array $post): void {
		// whether the post is deleted or not
		$isPostDeleted = $this->isPostDeleted($post);

		// don't bother if the post isn't deleted
		if(!$isPostDeleted) {
			return;
		}

		// OK - this post is deleted. Proceed

		// Append the staff note to the comment
		if(isset($post['deleted_note'])) {
			$templateValues['{$COM}'] .= $this->renderStaffNoteOnPost($post);
		}
	}

	private function renderStaffNoteOnPost(array $post): string {
		// get the note
		$note = $post['deleted_note'] ?? '';

		// sanitize the note
		$sanitizedNote = sanitizeStr($note);

		// convert new lines to break lines
		$sanitizedNote = nl2br($sanitizedNote);

		// generate the string
		$noteHtml = '<br><br><small class="noteOnPost warning" title="This is a note left by staff"> ' . $sanitizedNote . ' </small>';

		// return the generate message
		return $noteHtml;
	}

	private function appendCssClassIf(string &$cssClasses, bool $condition, string $className): void {
		// don't bother if condition is not met
		if(!$condition) {
			return;
		}

		// append hidden css class
		// space separated on each side
		$cssClasses .= " $className ";
	}

	private function onRenderAttachmentCssClass(string &$attachmentCssClasses, array &$post, bool $isLiveFrontend): void {
		// return early if this isn't done from the live frontend
		if(!$isLiveFrontend) {
			return;
		}

		// is this being viewed from the module page?
		$isModulePage = $this->isModulePage();

		// if this the module page, then return coz it should render like normal here
		if($isModulePage) {
			return;
		}

		// whether the post is deleted or not
		$isPostDeleted = $this->isPostDeleted($post);

		// whether only the file was deleted
		$onlyFileDeleted = $post['file_only_deleted'] ?? 0;

		$this->appendCssClassIf($attachmentCssClasses, $isPostDeleted && $onlyFileDeleted, 'deletedFile');
	}

	private function onRenderPostCssClass(string &$postCssClasses, array $post): void {
		// is this being viewed from the module page?
		$isModulePage = $this->isModulePage();

		// if this the module page, then return coz it should render like normal here
		if($isModulePage) {
			return;
		}

		// whether only the file was deleted
		$onlyFileDeleted = $post['file_only_deleted'] ?? 0;

		// whether the post is deleted or not
		$isPostDeleted = $this->isPostDeleted($post) && $onlyFileDeleted === 0;

		$this->appendCssClassIf($postCssClasses, $isPostDeleted, 'deletedPost');
	}
	
	private function onRenderThreadCssClass(string &$threadCssClasses, array $thread): void {
		// is this being viewed from the module page?
		$isModulePage = $this->isModulePage();

		// if this the module page, then return coz it should render like normal here
		if($isModulePage) {
			return;
		}

		// whether the thread is deleted or not
		$isThreadDeleted = !empty($thread['thread_deleted']) && empty($thread['thread_attachment_deleted']);

		$this->appendCssClassIf($threadCssClasses, $isThreadDeleted, 'deletedPost');
	}

	private function onGenerateModuleHeader(string &$moduleHeader): void {
		// generate the url path of the deleted posts javascript
		// this prevents the user from needing to refresh + wait through the rebuild
		$jsFileUrl = $this->generateJavascriptUrl('deletedPosts.js');

		// generate the script html for including the deleted posts js
		// defer
		$jsHtml = $this->generateScriptHtml($jsFileUrl, true);

		// then append it to the header
		$moduleHeader .= $jsHtml;
	}

	private function handleModPageRequests(int $accountId, userRole $roleLevel): void {
		$deletedPostId = $_POST['deletedPostId'] ?? null;
		$action = $_POST['action'] ?? null;

		// handle an action for single deleted post
		if(isset($deletedPostId)) {
			// make sure the user is a high enough role level if the post wasn't deleted by them
			// if not, throw excepton
			$this->authenticateDeletedPost($deletedPostId, $roleLevel, $accountId);

			$this->handleAction($deletedPostId, $accountId, $roleLevel, $action);

			return;
		}

		// invalid action from request - it didn't fit any of the above criteria
		else {
			throw new BoardException("Invalid action");
		}
	}

	private function authenticateDeletedPost(int $deletedPostId, userRole $roleLevel, int $accountId): void {
		// don't loop if the user has the required permission to restore/purge any post regardless of their role
		if($roleLevel->isAtLeast($this->requiredRoleForAll)) {
			return;
		}

		// check the database if the user is the one who deleted the post
		$isAuthenticated = $this->moduleContext->deletedPostsService->authenticateDeletedPost($deletedPostId, $accountId);

		// throw an exception if the user isn't authenticated to deleted/restored it
		if(!$isAuthenticated) {
			throw new BoardException("You are not authenticated to modify or view this deleted post!");
		}
	}

	private function handleAction(int $deletedPostId, int $accountId, userRole $roleLevel, string $action): void {
		// If its a restore action, handle the restoring of the post
		if($action === 'restore') {
			$this->moduleContext->deletedPostsService->restorePost($deletedPostId, $accountId);
		}

		// if it's a purge action, handle the purging and associated actions 
		else if ($action === 'purge' && $roleLevel->isAtLeast($this->requiredRoleForAll)) {
			$this->moduleContext->deletedPostsService->purgePost($deletedPostId);
		}

		// if it's an attachment purge then delete the file only
		// then mark it as 'restored' by the mod since theres no more action to do on it
		else if ($action === 'purgeAttachment' && $roleLevel->isAtLeast($this->requiredRoleForAll)) {
			$this->moduleContext->deletedPostsService->purgeFileOnly($deletedPostId, $accountId);
		}

		// if it's a saveNote action. handle saving a new note tied to that post
		else if ($action === 'saveNote') {
			// note from request
			$note = $_POST['note'] ?? '';

			// update the note
			$this->moduleContext->deletedPostsService->updateNote($deletedPostId, $note);
			
			// url of the deleted post
			$url = $this->generateDeletedPostViewUrl($deletedPostId);

			redirect($url);
		}

		// rebuild board
		$this->rebuildBoardByDeletedPostId($deletedPostId);
	}

	private function rebuildBoardByDeletedPostId(int $deletedPostId): void {
		// get the board uid by deleted post id
		$boardUid = $this->moduleContext->deletedPostsService->getBoardUidByDeletedPostId($deletedPostId);

		// if its null then dont bother
		if(is_null($boardUid)) {
			return;
		}

		// get board from board uid
		$board = searchBoardArrayForBoard($boardUid);

		// rebuild the board html
		$board->rebuildBoard();
	}

	private function drawModPage(int $accountId, userRole $roleLevel): void {
		// get page number from GET
		$page = $_GET['page'] ?? 0;

		// certain actions involve drawing/GET
		$action = $_GET['action'] ?? null;

		$postRenderer = new postRenderer($this->moduleContext->board, $this->moduleContext->config, $this->moduleContext->moduleEngine, $this->moduleTemplateEngine, []);
		$threadRenderer = new threadRenderer($this->moduleContext->board->loadBoardConfig(), $this->moduleContext->templateEngine, $postRenderer, $this->moduleContext->moduleEngine);

		// view a single deleted post in full detail
		if($action === 'viewMore') {
			// to view a deleted post in full detail we need the deleted post id
			$deletedPostId = (int)$_GET['deletedPostId'] ?? null;

			// throw error if its null
			// also throw it if its not an integer
			if(is_null($deletedPostId) || !is_int($deletedPostId)) {
				// throw user-facing error
				throw new BoardException("Invalid id selected!");
			}

			// make sure the user is authorized to view this post
			// theres a possibility staff that either didn't delete the post or arent authorized
			$this->authenticateDeletedPost($deletedPostId, $roleLevel, $accountId);

			// get the deleted post row
			$deletedPost = $this->moduleContext->deletedPostsService->getDeletedPostRowById($deletedPostId);

			// throw exception if the post wasn't found
			if(is_null($deletedPost)) {
				// now throw it
				throw new BoardException("Post not found!");
			}

			// draw it
			$this->drawDeletedPostView($roleLevel, $deletedPost, $postRenderer, $threadRenderer);

			// return early so other drawing methods dont run
			return;
		}

		// get paginated results
		// If the user is at least a moderator, get all deleted posts
		elseif($roleLevel->isAtLeast($this->requiredRoleForAll)) {
			// get the deleted posts from the database
			$deletedPosts = $this->moduleContext->deletedPostsService->getDeletedPosts($page, $this->moduleContext->board->getConfigValue('PAGE_DEF', 40));

			// get the total amount of deleted posts
			$deletedPostsCount = $this->moduleContext->deletedPostsService->getTotalAmount();
		} 
		
		// get and draw paginated results of posts that the current user has deleted
		else {
			// only get posts deleted by the staff
			$deletedPosts = $this->moduleContext->deletedPostsService->getDeletedPostsByAccount($accountId, $page, $this->moduleContext->board->getConfigValue('PAGE_DEF', 40));

			// get the total amount
			$deletedPostsCount = $this->moduleContext->deletedPostsService->getTotalAmountFromAccountId($accountId);
		}

		// finalize html output
		$this->handleHtmlOutput($roleLevel, $deletedPosts, $deletedPostsCount, $postRenderer, $threadRenderer);
	}

	private function outputAdminPage(string $pageContentHtml, string $pagerHtml = ''): void {
		// assign placeholder values
		$pageContent = [
			'{$PAGE_CONTENT}' => $pageContentHtml,
			'{$PAGER}' => $pagerHtml
		];

		// parse the page
		$pageHtml = $this->moduleContext->adminPageRenderer->ParsePage('GLOBAL_ADMIN_PAGE_CONTENT', $pageContent, true);
		
		// echo output to browser
		echo $pageHtml;
	}

	private function drawDeletedPostView(userRole $roleLevel, array $deletedPost, postRenderer $postRenderer, threadRenderer $threadRenderer): void {
		// if its a thread then get the thread data
		if($deletedPost['is_op']) {
			$thread = $this->moduleContext->threadService->getThreadByUID($deletedPost['thread_uid'], true);

			// get the post uids from the thread
			$postUids = $thread['post_uids'];
		} 
		// just set it blank
		else {
			$thread = [];

			// but set the post uids to be the post uid of the deleted post
			$postUids = [$deletedPost['post_uid']];
		}

		// get the quotelinks
		$quoteLinks = $this->moduleContext->quoteLinkService->getQuoteLinksByPostUids($postUids, true);

		// set the quotelinks
		$postRenderer->setQuoteLinks($quoteLinks);

		// get the template values for the deleted post entry
		$deletedPostTemplateValues = $this->prepareDeletedEntryPlaceholders(
			$roleLevel,
			$deletedPost, 
			$postRenderer, 
			$threadRenderer, 
			true, 
			$thread);

		// add IS_VIEW template value to array to stop [View] from being rendered
		$deletedPostTemplateValues['{$IS_VIEW}'] = true;

		// get the url of the view
		$url = $this->generateDeletedPostViewUrl($deletedPost['deleted_post_id']);

		// add url for the view
		$deletedPostTemplateValues['{$VIEW_URL}'] = htmlspecialchars($url);

		// parse the template block
		$deletedPostHtml = $this->moduleContext->adminPageRenderer->ParseBlock('DELETED_POST_ENTRY', $deletedPostTemplateValues);

		// generate page section html
		$deletedPostEntryPage = $this->moduleContext->adminPageRenderer->ParseBlock('DELETED_POST_VIEW_ENTRY', [
			'{$DELETED_POST}' => $deletedPostHtml,
			'{$URL}' => htmlspecialchars($this->myPage),
		]);

		// output the admin page
		$this->outputAdminPage($deletedPostEntryPage);
	}

	private function generateDeletedPostViewUrl(int $deletedPostId): string {
		// generate module url for page
		$url = $this->getModulePageURL(
			[
				'deletedPostId' => $deletedPostId,
				'action' => 'viewMore'
			],
			false
		);

		// return generated url
		return $url;
	}

	private function handleHtmlOutput(userRole $roleLevel, array $deletedPosts, int $deletedPostsCount, postRenderer $postRenderer, threadRenderer $threadRenderer): void {
		// get post uids
		$postUids = array_column($deletedPosts, 'post_uid');

		// then get the quote links for the posts
		$quoteLinks = $this->moduleContext->quoteLinkService->getQuoteLinksByPostUids($postUids, true);

		// set the post renderer quote links
		$postRenderer->setQuoteLinks($quoteLinks);
		
		// flag for if there's no posts.
		$areNoPosts = empty($deletedPosts);
		
		// keep track of the template values for deleted post entries
		$deletedPostListValues = [];

		// don't bother trying to parse if there's no posts
		if(!$areNoPosts) {
			// get deleted posts html
			$deletedPostListValues = $this->renderDeletedPosts($roleLevel, $deletedPosts, $postRenderer, $threadRenderer);
		}
		
		// bind deted posts list html to placeholder
		$deletedPostsPageHtml = $this->moduleContext->adminPageRenderer->ParseBlock('DELETED_POSTS_MOD_PAGE', [
			'{$DELETED_POSTS}' => $deletedPostListValues,
			'{$ARE_NO_POSTS}' => $areNoPosts,
			'{$URL}' => htmlspecialchars($this->myPage)
		]);

		// pager
		$entriesPerPage = $this->moduleContext->board->getConfigValue('PAGE_DEF');
		$totalEntries = $deletedPostsCount;
		$pager = drawPager($entriesPerPage, $totalEntries, $this->myPage);
		
		// output the admin page
		$this->outputAdminPage($deletedPostsPageHtml, $pager);
	}

	private function renderDeletedPosts(userRole $roleLevel, array $deletedPostEntries, postRenderer $postRenderer, threadRenderer $threadRenderer): array {
		// array to store the template values
		$deletedPostsTemplateValues = [];
		
		// loop through the deleted posts data and generate placeholder-to-value elements
		foreach($deletedPostEntries as $deletedEntry) {
			// prepare the entry placeholders
			$deletedPostsTemplateValues[] = $this->prepareDeletedEntryPlaceholders(
				$roleLevel, 
				$deletedEntry, 
				$postRenderer, 
				$threadRenderer);
		}

		// now, return the template values
		return $deletedPostsTemplateValues;
	}

	private function prepareDeletedEntryPlaceholders(
		userRole $roleLevel,
		array $deletedEntry, 
		postRenderer $postRenderer, 
		threadRenderer $threadRenderer, 
		bool $showAll = false, 
		array $thread = []
		): array {
		// post board uid 
		$boardUID = $deletedEntry['boardUID'];

		// username of the staff/user who deleted the post
		$deletedByUsername = $deletedEntry['deleted_by_username'] ?? null;

		// username of the staff who deleted the post
		$restoredByUsername = $deletedEntry['restored_by_username'] ?? null;

		// timestamp the post was deleted at
		$deletedTimestamp = $deletedEntry['deleted_at'];

		// board the post was made to
		$board = searchBoardArrayForBoard($boardUID);

		// title of the board
		$boardTitle = $board->getBoardTitle();

		// id of the deleted post row
		$id = $deletedEntry['deleted_post_id'];

		// url to view more detailed information about the post
		$viewMoreUrl = $this->generateViewMoreUrl($id, $this->myPage);

		// handle post html rendering logic
		$postHtml = $this->generatePostHtml($deletedEntry, $thread, $showAll, $postRenderer, $threadRenderer);

		// note for the deleted post
		$note = $deletedEntry['note'] ?? '';

		// init a truncated one for the preview
		$notePreview = $this->generateNotePreview($note);

		// put together the placeholder => value
		$templateValues = [
			'{$DELETED_BY}' => htmlspecialchars($deletedByUsername),
			'{$DELETED_AT}' => htmlspecialchars($deletedTimestamp),
			'{$ID}' => htmlspecialchars($id),
			'{$BOARD_UID}' => htmlspecialchars($boardUID),
			'{$CAN_PURGE}' => $roleLevel->isAtLeast($this->requiredRoleForAll),
			'{$BOARD_TITLE}' => $boardTitle,
			'{$VIEW_MORE_URL}' => htmlspecialchars($viewMoreUrl),
			'{$POST_HTML}' => $postHtml,
			'{$IS_OPEN}' => $deletedEntry['open_flag'] ? "No" : "Yes",
			'{$FILE_ONLY}' => $deletedEntry['file_only_deleted'],
			'{$RESTORED_AT}' => htmlspecialchars($deletedEntry['restored_at']),
			'{$RESTORED_BY}' => htmlspecialchars($restoredByUsername),
			'{$NOTE}' => htmlspecialchars($note),
			'{$NOTE_PREVIEW}' => $notePreview,
			'{$SHOW_ALL}' => htmlspecialchars($showAll),
			'{$URL}' => htmlspecialchars($this->myPage)
		];

		// return results
		return $templateValues;
	}

	private function generateNotePreview(string $note): string {
		// limit it to 100 chars
		$notePreview = truncateText($note, 100);

		// sanitize the preview
		$notePreview = htmlspecialchars($notePreview);

		// convert new lines to <br> for preview
		$notePreview = nl2br($notePreview);

		// return note preview string
		return $notePreview;
	}

	private function generatePostHtml(array $deletedEntry, array $thread, bool $showAll, postRenderer $postRenderer, threadRenderer $threadRenderer): string {
		// init template values
		$templateValues = [];
		
		// if the post is a reply then render it as an OP
		if(!$deletedEntry['is_op'] || !$showAll) {
			// flag to make sure the reply gets rendered using the OP template block
			$renderAsOp = true;

			// get the board of the post
			$board = searchBoardArrayForBoard($deletedEntry['boardUID']);

			// get the base url of the board
			$boardUrl = $board->getBoardURL();

			// html of the post / thread
			$postHtml = $postRenderer->render($deletedEntry, $templateValues, 0, false, [$deletedEntry], true, '', '', '', '', '', 0, false, $boardUrl, $renderAsOp);
		}
		// if its a thread (and we're showing all) then render it along with its replies
		elseif ($deletedEntry['is_op'] && $showAll) {
			// posts from the thread
			$posts = $thread['posts'];
			
			// make every post marked as deleted
			$posts = array_map(function($row) {
				//$row['open_flag'] = 1;  // mark row as deleted
   				return $row;
			}, $posts);

			// thread html
			$postHtml = $threadRenderer->render([$thread], true, $thread['thread'], $posts, 0, false, true);
		}

		// return post/thread html
		return $postHtml;
	}

	private function generateViewMoreUrl(int $id, string $baseUrl): string {
		// generate the view more url
		$viewMoreUrl = $this->generateActionUrl($id, $baseUrl, 'viewMore');

		// return generated view more url
		return $viewMoreUrl;
	}

	private function generateActionUrl(int $id, string $baseUrl, string $action): string {
		// if the action (which is used as an array key) is blank then something has gone very wrong 
		if(empty($action)) {
			throw new RuntimeException("Invalid action when generating action URLs");
		}
		
		// generate the url parameters
		$purgeParams = http_build_query([
			'deletedPostId' => $id,
			'action' => $action
		]);

		// get the base url
		$actionUrl = $baseUrl . '&' . $purgeParams;

		// return generated url
		return $actionUrl;	
	}

	public function ModulePage(): void {
		// Account session values
		$staffAccountFromSession = new staffAccountFromSession;

		// get staff id and role level
		$accountId = $staffAccountFromSession->getUID();
		$roleLevel = $staffAccountFromSession->getRoleLevel();

		// request vs draw
		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
			$this->handleModPageRequests($accountId, $roleLevel);
			
			redirect($this->myPage);
		} elseif (isset($_GET['json'])) {
			// handle json output (API)
			// to do
		} else {
			// draw the overview of the deleted posts
			$this->drawModPage($accountId, $roleLevel);
		}
	}

}
