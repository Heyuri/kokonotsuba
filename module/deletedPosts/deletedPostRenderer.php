<?php

namespace Kokonotsuba\Modules\deletedPosts;

use Kokonotsuba\board\board;
use Kokonotsuba\cookie\cookieService;
use Kokonotsuba\error\BoardException;
use Kokonotsuba\post\deletion\deletedPostsService;
use Kokonotsuba\userRole;
use Kokonotsuba\module_classes\moduleEngine;
use Kokonotsuba\post\helper\postDateFormatter;
use Kokonotsuba\post\deletion\DeletedPost;
use Kokonotsuba\template\pageRenderer;
use Kokonotsuba\renderers\postRenderer;
use Kokonotsuba\quote_link\quoteLinkService;
use RuntimeException;
use Kokonotsuba\template\templateEngine;
use Kokonotsuba\renderers\threadRenderer;
use Kokonotsuba\thread\threadService;
use Kokonotsuba\thread\ThreadData;
use Kokonotsuba\request\request;

use function Kokonotsuba\libraries\html\drawPager;
use function Kokonotsuba\libraries\html\drawDeletedPostsFilterForm;
use function Kokonotsuba\libraries\_T;
use function Kokonotsuba\libraries\searchBoardArrayForBoard;
use function Kokonotsuba\libraries\getFiltersFromRequest;
use function Puchiko\strings\buildSmartQuery;

class deletedPostRenderer {
	public function __construct(
		private board $board,
		private array $config,
		private moduleEngine $moduleEngine,
		private templateEngine $moduleTemplateEngine,
		private deletedPostUtility $deletedPostUtility,
		private deletedPostsService $deletedPostsService,
		private userRole $requiredRoleActionForModAll,
		private pageRenderer $adminPageRenderer,
		private threadService $threadService,
		private quoteLinkService $quoteLinkService,
		private cookieService $cookieService,
		private string $modulePageUrl,
		private string $restoredIndexUrl,
		private userRole $requiredRoleForDeleteRestoredRecord,
		private postDateFormatter $postDateFormatter,
		private readonly request $request,
	) {}

	public function drawModPage(int $accountId, userRole $roleLevel): void {
		// get page number from GET
		$page = $this->request->getParameter('page', 'GET', 0);

		// certain actions involve drawing/GET
		$pageName = $this->request->getParameter('pageName', 'GET');

		// init post renderer using the board's template engine (has OP/REPLY blocks)
		$postRenderer = new postRenderer(
			$this->board, 
			$this->config, 
			$this->moduleEngine, 
			$this->moduleTemplateEngine, 
			[],
			$this->request
		);
		
		// init thread renderer using the board's template engine
		$threadRenderer = new threadRenderer(
			$this->board->loadBoardConfig(), 
			$this->moduleTemplateEngine, 
			$postRenderer, 
			$this->moduleEngine
		);

		// view a single deleted post in full detail
		if($pageName === 'viewMore') {
			$this->handlePostView($roleLevel, $accountId, $postRenderer, $threadRenderer);

			// return early so other drawing methods dont run
			return;
		}

		// view restore posts index
		else if($pageName === 'restoredIndex') {
			// handle restored post index logic
			$this->handleRestoredPostIndex($roleLevel, $page, $accountId, $postRenderer, $threadRenderer);
		} 

		// view deleted post index
		else {
			// handle deleted post index logic
			$this->handleDeletedPostIndex($roleLevel, $page, $accountId, $postRenderer, $threadRenderer);
		}
	}

	private function handlePostView(userRole $roleLevel, int $accountId, postRenderer $postRenderer, threadRenderer $threadRenderer): void {
		// to view a deleted post in full detail we need the deleted post id
		$deletedPostId = $this->request->getParameter('deletedPostId', 'GET');

		// cast to int
		$deletedPostId = (int)$deletedPostId;

		// throw error if its null
		// also throw it if its not an integer
		if(is_null($deletedPostId) || !is_int($deletedPostId)) {
			// throw user-facing error
			throw new BoardException("Invalid id selected!");
		}

		// make sure the user is authorized to view this post
		// theres a possibility staff that either didn't delete the post or arent authorized
		$this->deletedPostUtility->authenticateDeletedPost($deletedPostId, $roleLevel, $accountId);

		// get the deleted post row
		$deletedPost = $this->deletedPostsService->getDeletedPostRowById($deletedPostId);

		// throw exception if the post wasn't found
		if(is_null($deletedPost)) {
			// now throw it
			throw new BoardException("Post not found!");
		}

		// draw it
		$this->drawDeletedPostView($roleLevel, $deletedPost, $postRenderer, $threadRenderer);
	}

	private function handleRestoredPostIndex(userRole $roleLevel, int $page, int $accountId, postRenderer $postRenderer, threadRenderer $threadRenderer): void {
		$filters = $this->resolveFilters('restoredIndex');
		$this->handlePostIndex($roleLevel, $page, $accountId, $postRenderer, $threadRenderer, 'restored', 'Restored posts', $filters);
	}

	private function handleDeletedPostIndex(userRole $roleLevel, int $page, int $accountId, postRenderer $postRenderer, threadRenderer $threadRenderer): void {
		$filters = $this->resolveFilters();
		$this->handlePostIndex($roleLevel, $page, $accountId, $postRenderer, $threadRenderer, 'deleted', 'Deleted posts', $filters);
	}

	private function handlePostIndex(
		userRole $roleLevel,
		int $page,
		int $accountId,
		postRenderer $postRenderer,
		threadRenderer $threadRenderer,
		string $type,
		string $title,
		array $filters = []
	): void {
		// get paginated results
		$isRestored = $type === 'restored';
		$deletedPostsService = $this->deletedPostsService;
		$pageDef = $this->board->getConfigValue('PAGE_DEF', 40);

		// If the user is at least a moderator, get all posts
		if($roleLevel->isAtLeast($this->requiredRoleActionForModAll)) {
			if($isRestored) {
				// get the restored posts from the database
				$posts = $deletedPostsService->getRestoredPosts($page, $pageDef, $filters);

				// get the total amount of restored posts
				$postsCount = $deletedPostsService->getTotalAmountOfRestoredPosts($filters);
			} else {
				// get the deleted posts from the database
				$posts = $deletedPostsService->getDeletedPosts($page, $pageDef, $filters);

				// get the total amount of deleted posts
				$postsCount = $deletedPostsService->getTotalAmountOfDeletedPosts($filters);
			}
		} 
		
		// get and draw paginated results of posts that the current user has affected
		else {
			if($isRestored) {
				// only get posts restored by the staff
				$posts = $deletedPostsService->getRestoredPostsByAccount($accountId, $page, $pageDef, $filters);

				// get the total amount
				$postsCount = $deletedPostsService->getTotalAmountOfRestoredPostsFromAccountId($accountId, $filters);
			} else {
				// only get posts deleted by the staff
				$posts = $deletedPostsService->getDeletedPostsByAccount($accountId, $page, $pageDef, $filters);

				// get the total amount
				$postsCount = $deletedPostsService->getTotalAmountOfDeletedPostsFromAccountId($accountId, $filters);
			}
		}
		
		// finalize html output
		$this->handleHtmlOutput($roleLevel, $posts, $postsCount, $postRenderer, $threadRenderer, $title, $filters);
	}

	private function outputAdminPage(string $pageContentHtml, string $pagerHtml = ''): void {
		// assign placeholder values
		$pageContent = [
			'{$PAGE_CONTENT}' => $pageContentHtml,
			'{$PAGER}' => $pagerHtml
		];

		// parse the page
		$pageHtml = $this->adminPageRenderer->ParsePage('GLOBAL_ADMIN_PAGE_CONTENT', $pageContent, true);
		
		// echo output to browser
		echo $pageHtml;
	}

	private function drawDeletedPostView(userRole $roleLevel, DeletedPost $deletedPost, postRenderer $postRenderer, threadRenderer $threadRenderer): void {
		// if its a thread then get the thread data
		if($deletedPost->isOp()) {
			$thread = $this->threadService->getThreadAllReplies($deletedPost->getThreadUid(), true, 0);

			// throw error if thread not found
			if(!$thread) {
				throw new BoardException(_T('thread_not_found_for_deletion'));
			}

			// get the post uids from the thread
			$postUids = $thread->getPostUids();
		} 
		// just set it blank
		else {
			$thread = null;

			// but set the post uids to be the post uid of the deleted post
			$postUids = [$deletedPost->getUid()];
		}

		// get the quotelinks
		$quoteLinks = $this->quoteLinkService->getQuoteLinksByPostUids($postUids, true);

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
		$url = $this->deletedPostUtility->generateDeletedPostViewUrl($deletedPost->getDeletedPostId());

		// add url for the view
		$deletedPostTemplateValues['{$VIEW_URL}'] = htmlspecialchars($url);

		// parse the template block
		$deletedPostHtml = $this->adminPageRenderer->ParseBlock('DELETED_POST_ENTRY', $deletedPostTemplateValues);

		// generate the url for the back button
		$backUrl = $this->generateBackUrl($deletedPost);

		// generate page section html
		$deletedPostEntryPage = $this->adminPageRenderer->ParseBlock('DELETED_POST_VIEW_ENTRY', [
			'{$DELETED_POST}' => $deletedPostHtml,
			'{$BACK_URL}' => htmlspecialchars($backUrl),
			'{$IS_OPEN}' => $deletedPostTemplateValues['{$IS_OPEN}'],
			'{$URL}' => htmlspecialchars($this->modulePageUrl)
		]);

		// output the admin page
		$this->outputAdminPage($deletedPostEntryPage);
	}

	private function generateBackUrl(DeletedPost $deletedPost): string {
		// flag for if its restored or not
		$isRestoredPost = $deletedPost->getOpenFlag() === 0;

		// if its a restored post then make the [Back] url go to the restored index
		if($isRestoredPost) {
			return $this->restoredIndexUrl;
		}
		// otherwise if its a deleted post then return to the regular dp index
		else {
			// return the deleted index url
			return $this->modulePageUrl;
		}
	}

	private function handleHtmlOutput(
		userRole $roleLevel, 
		?array $deletedPosts, 
		int $deletedPostsCount, 
		postRenderer $postRenderer, 
		threadRenderer $threadRenderer,
		string $moduleHeader = 'Deleted posts',
		array $filters = []
	): void {
		// Normalize null to empty array
		$deletedPosts = $deletedPosts ?? [];

		// get post uids
		$postUids = array_map(fn(DeletedPost $p) => $p->getUid(), $deletedPosts);

		// then get the quote links for the posts
		$quoteLinks = $this->quoteLinkService->getQuoteLinksByPostUids($postUids, true);

		// set the post renderer quote links
		$postRenderer->setQuoteLinks($quoteLinks);
		
		// flag for if there's no posts.
		$areNoPosts = empty($deletedPosts);
		
		// keep track of the template values for deleted post entries
		$deletedPostListValues = [];

		// don't bother trying to parse if there's no posts
		if (!$areNoPosts) {
			// get deleted posts html
			$deletedPostListValues = $this->renderDeletedPosts(
				$roleLevel,
				$deletedPosts,
				$postRenderer,
				$threadRenderer
			);
		}

		// determine if the user can view IP addresses
		$canViewIp = $roleLevel->isAtLeast($this->board->getConfigValue('AuthLevels.CAN_VIEW_IP_ADDRESSES', \Kokonotsuba\userRole::LEV_MODERATOR));

		// render the filter form
		$filterFormHtml = '';
		$formAction = $this->board->getBoardURL(true);
		$hiddenPageName = ($this->request->getParameter('pageName', 'GET') === 'restoredIndex') ? 'restoredIndex' : '';
		drawDeletedPostsFilterForm($filterFormHtml, $formAction, $filters, $canViewIp, $hiddenPageName);
		
		// bind deleted posts list html to placeholder
		$deletedPostsPageHtml = $this->adminPageRenderer->ParseBlock(
			'DELETED_POSTS_MOD_PAGE',
			[
				'{$DELETED_POSTS}' => $deletedPostListValues,
				'{$SHOW_DELETED_POSTS}' => $this->cookieService->get('viewDeletedPosts', true),
				'{$CAN_VIEW_ALL_DELETED_POSTS}' => $roleLevel->isAtLeast($this->requiredRoleActionForModAll),
				'{$ARE_NO_POSTS}' => $areNoPosts,
				'{$MODULE_HEADER_TEXT}' => $moduleHeader,
				'{$URL}' => htmlspecialchars($this->modulePageUrl),
				'{$FILTER_FORM}' => $filterFormHtml
			]
		);

		// generate the url for the pager
		$pagerUrl = $this->modulePageUrl;

		// generate the url for the pager
		if($this->request->getParameter('pageName', 'GET') === 'restoredIndex') {
			$pagerUrl .= '&pageName=restoredIndex';
		}

		// append active filter params to the pager url
		$defaultFilters = $this->getDefaultFilters();
		$pagerUrl = buildSmartQuery($pagerUrl, $defaultFilters, $filters);

		// pager
		$entriesPerPage = $this->board->getConfigValue('PAGE_DEF');
		$totalEntries = $deletedPostsCount;
		$pager = drawPager($entriesPerPage, $totalEntries, $pagerUrl, $this->request);
		
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
		DeletedPost $deletedEntry, 
		postRenderer $postRenderer, 
		threadRenderer $threadRenderer, 
		bool $showAll = false, 
		?ThreadData $thread = null
		): array {
		// post board uid 
		$boardUID = $deletedEntry->getBoardUID();

		// username of the staff/user who deleted the post
		$deletedByUsername = $deletedEntry->getDeletedByUsername() ?? null;

		// username of the staff who deleted the post
		$restoredByUsername = $deletedEntry->getRestoredByUsername() ?? null;

		// timestamp the post was deleted at
		$deletedTimestamp = $deletedEntry->getDeletedAt();

		// board the post was made to
		$board = searchBoardArrayForBoard($boardUID);

		// title of the board
		$boardTitle = $board->getBoardTitle();

		// id of the deleted post row
		$id = $deletedEntry->getDeletedPostId();

		// url to view more detailed information about the post
		$viewMoreUrl = $this->generateViewMoreUrl($id, $this->modulePageUrl);

		// handle post html rendering logic
		$postHtml = $this->generatePostHtml($deletedEntry, $thread, $showAll, $postRenderer, $threadRenderer);

		// attachment only deletion
		$isAttachmentOnly = !empty($deletedEntry->getFileId()) && !empty($deletedEntry->getFileOnlyDeleted());

		// put together the placeholder => value
		$templateValues = [
			'{$DELETED_BY}' => htmlspecialchars($deletedByUsername),
			'{$DELETED_AT}' => isset($deletedTimestamp) ? $this->postDateFormatter->formatFromDateString($deletedTimestamp) : '',
			'{$ID}' => htmlspecialchars($id),
			'{$BOARD_UID}' => htmlspecialchars($boardUID),
			'{$CAN_PURGE}' => $roleLevel->isAtLeast($this->requiredRoleActionForModAll),
			'{$BOARD_TITLE}' => $boardTitle,
			'{$VIEW_MORE_URL}' => htmlspecialchars($viewMoreUrl),
			'{$POST_HTML}' => $postHtml,
			'{$IS_OPEN}' => $deletedEntry->getOpenFlag() ? 1 : null,
			'{$IS_ATTACHMENT_ONLY}' => $isAttachmentOnly,
			'{$RESTORED_AT}' => ($deletedEntry->getRestoredAt() !== null) ? $this->postDateFormatter->formatFromDateString($deletedEntry->getRestoredAt()) : '',
			'{$RESTORED_BY}' => htmlspecialchars($restoredByUsername),
			'{$SHOW_ALL}' => htmlspecialchars($showAll),
			'{$URL}' => htmlspecialchars($this->modulePageUrl),
			'{$CAN_PURGE_RESTORE_RECORD}' => $roleLevel->isAtLeast($this->requiredRoleForDeleteRestoredRecord)
		];

		// return results
		return $templateValues;
	}

	private function generatePostHtml(
		DeletedPost $deletedEntry, 
		?ThreadData $thread, 
		bool $showAll, 
		postRenderer $postRenderer, 
		threadRenderer $threadRenderer
	): string {
		// Attachment-only view
		if ($deletedEntry->getFileOnlyDeleted()) {
			return $this->renderAttachmentDeletion($deletedEntry, $postRenderer);
		}

		// init template values
		$templateValues = [];

		// get the board of the post
		$board = searchBoardArrayForBoard($deletedEntry->getBoardUID());

		// get the base url of the board
		$boardUrl = $board->getBoardURL();

		// if the post is a reply then render it as an OP
		if(!$deletedEntry->isOp() || !$showAll) {
			// flag to make sure the reply gets rendered using the OP template block
			$renderAsOp = true;

			// fetch thread number and default to zero if it isn't there for some reason
			$threadNumber = $deletedEntry->getOpNumber();

			// html of the post / thread
			$postHtml = $postRenderer->render($deletedEntry, $templateValues, $threadNumber, false, [$deletedEntry], true, '', '', 0, false, $boardUrl, $renderAsOp);
		}
		// if its a thread (and we're showing all) then render it along with its replies
		elseif ($deletedEntry->isOp() && $showAll) {
			// posts from the thread
			$posts = $thread->getPosts();
			
			// make every post marked as deleted
			$posts = array_map(function($row) {
				//$row['open_flag'] = 1;  // mark row as deleted
   				return $row;
			}, $posts);

			// thread html
			$postHtml = $threadRenderer->render([$thread], true, $thread->getThread(), $posts, 0, false, true, 0, '', $boardUrl, $templateValues);
		}

		// return post/thread html
		return $postHtml;
	}

	/**
	 * Render ONLY the deleted attachment for an attachment-only deletion entry.
	 * The post itself is NOT marked deleted — only the attachment.
	 */
	private function renderAttachmentDeletion(DeletedPost $deletedEntry, postRenderer $postRenderer): string {
		// file id of the deleted attachment
		$fileId = $deletedEntry->getFileId();

		// if deleted_attachments[field] doesn't exist, return empty
		if (empty($deletedEntry->getDeletedAttachments()[$fileId]) || !$deletedEntry->getAttachmentById($fileId)) {
			return '<div class="error centerText">' . _T('attachment_not_found') . '</div>';
		}

		// get the deleted attachment metadata
		$deletedAttachmentMeta = $deletedEntry->getDeletedAttachments()[$fileId];

		// get attachment
		// null if its not found
		$attachment = $deletedEntry->getAttachmentById($fileId);

		// But overwrite fields to indicate that it's deleted
		$attachment['is_deleted'] = true;
		$attachment['deleted_at'] = $deletedAttachmentMeta['deleted_at'];
		$attachment['deleted_post_id'] = $deletedAttachmentMeta['deleted_post_id'];

		// Render the attachment using postRenderer’s normal function
		// Arguments: attachments[], renderDeleted=true, forceSingle=true
		$attachmentHtml = $postRenderer->processAttachments([$attachment], true, true);

		return $attachmentHtml;
	}

	private function generateViewMoreUrl(int $id, string $baseUrl): string {
		// generate the view more url
		$viewMoreUrl = $this->generatePageNameUrl($id, $baseUrl, 'viewMore');

		// return generated view more url
		return $viewMoreUrl;
	}

	private function generatePageNameUrl(int $id, string $baseUrl, string $action): string {
		// if the action (which is used as an array key) is blank then something has gone very wrong 
		if(empty($action)) {
			throw new RuntimeException("Invalid action when generating action URLs");
		}
		
		// generate the url parameters
		$purgeParams = http_build_query([
			'deletedPostId' => $id,
			'pageName' => $action
		]);

		// get the base url
		$actionUrl = $baseUrl . '&' . $purgeParams;

		// return generated url
		return $actionUrl;	
	}

	private function getDefaultFilters(): array {
		return [
			'deleted_by_type' => '',
			'post_type' => '',
			'staff_username' => '',
			'ip_address' => ''
		];
	}

	private function resolveFilters(string $pageName = ''): array {
		// determine if the filter form was submitted
		$isSubmission = $this->request->hasParameter('filterSubmissionFlag', 'GET');

		// build the base URL for redirect
		$baseUrl = $this->modulePageUrl;
		if ($pageName !== '') {
			$baseUrl .= '&pageName=' . urlencode($pageName);
		}

		// get default filters
		$defaultFilters = $this->getDefaultFilters();

		// read filters from request (handles redirect on form submission)
		$filters = getFiltersFromRequest($baseUrl, $isSubmission, $defaultFilters, $this->request);

		// strip ip_address if user lacks permission
		$canViewIp = false;
		$staffAccount = new \Kokonotsuba\account\staffAccountFromSession;
		$roleLevel = $staffAccount->getRoleLevel();
		$canViewIp = $roleLevel->isAtLeast($this->board->getConfigValue('AuthLevels.CAN_VIEW_IP_ADDRESSES', \Kokonotsuba\userRole::LEV_MODERATOR));
		if (!$canViewIp && !empty($filters['ip_address'])) {
			$filters['ip_address'] = '';
		}

		return $filters;
	}

}