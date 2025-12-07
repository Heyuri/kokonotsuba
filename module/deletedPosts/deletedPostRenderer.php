<?php

namespace Kokonotsuba\Modules\deletedPosts;

use board;
use BoardException;
use deletedPostsService;
use Kokonotsuba\Root\Constants\userRole;
use moduleEngine;
use pageRenderer;
use postRenderer;
use quoteLinkService;
use RuntimeException;
use templateEngine;
use threadRenderer;
use threadService;

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
		private string $modulePageUrl,
		private string $restoredIndexUrl,
		private userRole $requiredRoleForDeleteRestoredRecord
	) {}

	public function drawModPage(int $accountId, userRole $roleLevel): void {
		// get page number from GET
		$page = $_GET['page'] ?? 0;

		// certain actions involve drawing/GET
		$pageName = $_GET['pageName'] ?? null;

		// init post renderer
		$postRenderer = new postRenderer(
			$this->board, 
			$this->config, 
			$this->moduleEngine, 
			$this->moduleTemplateEngine, 
			[]
		);
		
		// init thread renderer
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
		$deletedPostId = $_GET['deletedPostId'] ?? null;

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
		$this->handlePostIndex($roleLevel, $page, $accountId, $postRenderer, $threadRenderer, 'restored', 'Restored posts');
	}

	private function handleDeletedPostIndex(userRole $roleLevel, int $page, int $accountId, postRenderer $postRenderer, threadRenderer $threadRenderer): void {
		$this->handlePostIndex($roleLevel, $page, $accountId, $postRenderer, $threadRenderer, 'deleted', 'Deleted posts');
	}

	private function handlePostIndex(
		userRole $roleLevel,
		int $page,
		int $accountId,
		postRenderer $postRenderer,
		threadRenderer $threadRenderer,
		string $type,
		string $title
	): void {
		// get paginated results
		$isRestored = $type === 'restored';
		$deletedPostsService = $this->deletedPostsService;
		$pageDef = $this->board->getConfigValue('PAGE_DEF', 40);

		// If the user is at least a moderator, get all posts
		if($roleLevel->isAtLeast($this->requiredRoleActionForModAll)) {
			if($isRestored) {
				// get the restored posts from the database
				$posts = $deletedPostsService->getRestoredPosts($page, $pageDef);

				// get the total amount of restored posts
				$postsCount = $deletedPostsService->getTotalAmountOfRestoredPosts();
			} else {
				// get the deleted posts from the database
				$posts = $deletedPostsService->getDeletedPosts($page, $pageDef);

				// get the total amount of deleted posts
				$postsCount = $deletedPostsService->getTotalAmountOfDeletedPosts();
			}
		} 
		
		// get and draw paginated results of posts that the current user has affected
		else {
			if($isRestored) {
				// only get posts restored by the staff
				$posts = $deletedPostsService->getRestoredPostsByAccount($accountId, $page, $pageDef);

				// get the total amount
				$postsCount = $deletedPostsService->getTotalAmountOfRestoredPostsFromAccountId($accountId);
			} else {
				// only get posts deleted by the staff
				$posts = $deletedPostsService->getDeletedPostsByAccount($accountId, $page, $pageDef);

				// get the total amount
				$postsCount = $deletedPostsService->getTotalAmountOfDeletedPostsFromAccountId($accountId);
			}
		}
		
		// finalize html output
		$this->handleHtmlOutput($roleLevel, $posts, $postsCount, $postRenderer, $threadRenderer, $title);
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

	private function drawDeletedPostView(userRole $roleLevel, array $deletedPost, postRenderer $postRenderer, threadRenderer $threadRenderer): void {
		// if its a thread then get the thread data
		if($deletedPost['is_op']) {
			$thread = $this->threadService->getThreadByUID($deletedPost['thread_uid'], true);

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
		$url = $this->deletedPostUtility->generateDeletedPostViewUrl($deletedPost['deleted_post_id']);

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

	private function generateBackUrl(array $deletedPost): string {
		// flag for if its restored or not
		$isRestoredPost = $deletedPost['open_flag'] === 0;

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
		string $moduleHeader = 'Deleted posts'
	): void {
		// Normalize null to empty array
		$deletedPosts = $deletedPosts ?? [];

		// get post uids
		$postUids = array_column($deletedPosts, 'post_uid');

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
		
		// bind deleted posts list html to placeholder
		$deletedPostsPageHtml = $this->adminPageRenderer->ParseBlock(
			'DELETED_POSTS_MOD_PAGE',
			[
				'{$DELETED_POSTS}' => $deletedPostListValues,
				'{$ARE_NO_POSTS}' => $areNoPosts,
				'{$MODULE_HEADER_TEXT}' => $moduleHeader,
				'{$URL}' => htmlspecialchars($this->modulePageUrl)
			]
		);

		// pager
		$entriesPerPage = $this->board->getConfigValue('PAGE_DEF');
		$totalEntries = $deletedPostsCount;
		$pager = drawPager($entriesPerPage, $totalEntries, $this->modulePageUrl);
		
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
		$viewMoreUrl = $this->generateViewMoreUrl($id, $this->modulePageUrl);

		// handle post html rendering logic
		$postHtml = $this->generatePostHtml($deletedEntry, $thread, $showAll, $postRenderer, $threadRenderer);

		// note for the deleted post
		$note = $deletedEntry['deleted_note'] ?? '';

		// init a truncated one for the preview
		$notePreview = $this->generateNotePreview($note);

		// attachment only deletion
		$isAttachmentOnly = !empty($deletedEntry['file_id']) && !empty($deletedEntry['file_only_deleted']);

		// put together the placeholder => value
		$templateValues = [
			'{$DELETED_BY}' => htmlspecialchars($deletedByUsername),
			'{$DELETED_AT}' => htmlspecialchars($deletedTimestamp),
			'{$ID}' => htmlspecialchars($id),
			'{$BOARD_UID}' => htmlspecialchars($boardUID),
			'{$CAN_PURGE}' => $roleLevel->isAtLeast($this->requiredRoleActionForModAll),
			'{$BOARD_TITLE}' => $boardTitle,
			'{$VIEW_MORE_URL}' => htmlspecialchars($viewMoreUrl),
			'{$POST_HTML}' => $postHtml,
			'{$IS_OPEN}' => $deletedEntry['open_flag'] ? 1 : null,
			'{$IS_ATTACHMENT_ONLY}' => $isAttachmentOnly,
			'{$RESTORED_AT}' => htmlspecialchars($deletedEntry['restored_at']),
			'{$RESTORED_BY}' => htmlspecialchars($restoredByUsername),
			'{$NOTE}' => htmlspecialchars($note),
			'{$NOTE_PREVIEW}' => $notePreview,
			'{$SHOW_ALL}' => htmlspecialchars($showAll),
			'{$URL}' => htmlspecialchars($this->modulePageUrl),
			'{$CAN_PURGE_RESTORE_RECORD}' => $roleLevel->isAtLeast($this->requiredRoleForDeleteRestoredRecord)
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

	private function generatePostHtml(
		array $deletedEntry, 
		array $thread, 
		bool $showAll, 
		postRenderer $postRenderer, 
		threadRenderer $threadRenderer
	): string {
		// Attachment-only view
		if ($deletedEntry['file_only_deleted']) {
			return $this->renderAttachmentDeletion($deletedEntry, $postRenderer);
		}

		// init template values
		$templateValues = [];

		// get the board of the post
		$board = searchBoardArrayForBoard($deletedEntry['boardUID']);

		// get the base url of the board
		$boardUrl = $board->getBoardURL();

		// if the post is a reply then render it as an OP
		if(!$deletedEntry['is_op'] || !$showAll) {
			// flag to make sure the reply gets rendered using the OP template block
			$renderAsOp = true;

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
			$postHtml = $threadRenderer->render([$thread], true, $thread['thread'], $posts, 0, false, true, 0, '', $boardUrl, $templateValues);
		}

		// return post/thread html
		return $postHtml;
	}

	/**
	 * Render ONLY the deleted attachment for an attachment-only deletion entry.
	 * The post itself is NOT marked deleted — only the attachment.
	 */
	private function renderAttachmentDeletion(array $deletedEntry, postRenderer $postRenderer): string {
		// file id of the deleted attachment
		$fileId = $deletedEntry['file_id'];

		// if deleted_attachments[field] doesn’t exist, return empty
		if (empty($deletedEntry['deleted_attachments'][$fileId]) || empty($deletedEntry['attachments'][$fileId])) {
			return '<div class="error centerText">' . _T('attachment_not_found') . '</div>';
		}

		// get the deleted attachment metadata
		$deletedAttachmentMeta = $deletedEntry['deleted_attachments'][$fileId];

		// get attachment
		// null if its not found
		$attachment = $deletedEntry['attachments'][$fileId];

		// But overwrite fields to indicate that it's deleted
		$attachment['is_deleted'] = true;
		$attachment['deleted_at'] = $deletedAttachmentMeta['deleted_at'];
		$attachment['deleted_note'] = $deletedAttachmentMeta['note'];
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

}