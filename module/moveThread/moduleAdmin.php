<?php

namespace Kokonotsuba\Modules\moveThread;

use Kokonotsuba\action_log\actionType;
use Kokonotsuba\error\BoardException;
use Exception;
use Kokonotsuba\post\FlagHelper;
use Kokonotsuba\interfaces\IBoard;
use InvalidArgumentException;
use Kokonotsuba\ip\IPAddress;
use Kokonotsuba\module_classes\abstractModuleAdmin;
use Kokonotsuba\module_classes\traits\listeners\IncludeScriptTrait;
use Kokonotsuba\module_classes\traits\listeners\PostControlHooksTrait;
use Kokonotsuba\post\Post;
use Kokonotsuba\post\helper\postDateFormatter;
use Kokonotsuba\post\postRegistData;
use Kokonotsuba\renderers\commentFormatter;
use Kokonotsuba\thread\Thread;
use Kokonotsuba\thread\ThreadData;
use Kokonotsuba\userRole;

use function Kokonotsuba\libraries\generateModerateButton;
use function Kokonotsuba\libraries\getCsrfHiddenInput;
use function Kokonotsuba\libraries\html\generateBoardListRadioHTML;
use function Kokonotsuba\libraries\getAttachmentsFromPosts;
use function Kokonotsuba\libraries\rebuildBoardsByArray;
use function Kokonotsuba\libraries\searchBoardArrayForBoard;
use function Puchiko\json\sendJsonResponse;
use function Puchiko\request\redirect;
use function Puchiko\strings\sanitizeStr;

//move thread module
class moduleAdmin extends abstractModuleAdmin {
	use PostControlHooksTrait;
	use IncludeScriptTrait;

	private const MERGE_THREAD_LIST_LIMIT = 100;

	private readonly string $modulePageUrl;

    public function getRequiredRole(): userRole {
        return $this->getConfig('AuthLevels.CAN_MOVE_THREAD', userRole::LEV_MODERATOR);
    }

	public function getName(): string {
		return 'Move thread tools';
	}

	public function getVersion(): string {
		return 'Koko 2025';
	}

	public function initialize(): void {
		$this->modulePageUrl = $this->getModulePageURL();

		$this->registerThreadControlPair('renderMoveThreadButton');
		$this->registerThreadControlPair('renderMergeThreadButton');
		$this->registerThreadWidgetHook('onRenderThreadWidget');
		$this->registerAdminHeaderHook('onGenerateModuleHeader');
		$this->registerScript('moveThread.js');
		$this->registerScript('mergeThread.js');
	}

	private function onGenerateModuleHeader(string &$moduleHeader): void {
		$moduleHeader .= $this->generateMoveThreadJsTemplate();
		$moduleHeader .= $this->generateMergeThreadJsTemplate();
	}

	/**
	 * Build the merge form template the JS window clones. The thread list is rendered for the board
	 * being viewed, which is the only board a merge can involve.
	 */
	private function generateMergeThreadJsTemplate(): string {
		$templateValues = [
			'{$FORM_ACTION}' => $this->modulePageUrl,
			'{$THREAD_UID}' => '',
			'{$THREAD_NUMBER}' => '',
			'{$THREAD_SUBJECT}' => '',
			'{$THREAD_LIST_HTML}' => $this->generateThreadListHTML($this->moduleContext->board),
			'{$CSRF_TOKEN}' => getCsrfHiddenInput(),
		];

		$formHtml = $this->moduleContext->adminPageRenderer->ParseBlock('THREAD_MERGE_FORM', $templateValues);

		return $this->generateTemplate('mergeThreadFormTemplate', $formHtml);
	}

	/**
	 * Render the board's most recently bumped threads as merge-source checkboxes.
	 */
	private function generateThreadListHTML(IBoard $board, string $excludeThreadUid = ''): string {
		$threads = $this->moduleContext->threadRepository->getThreadsFromBoard(
			$board->getBoardUID(),
			self::MERGE_THREAD_LIST_LIMIT
		) ?? [];

		if (!$threads) {
			return '';
		}

		$openingPosts = $this->moduleContext->threadRepository->getFirstPostsFromThreads(
			array_map(fn(Thread $thread) => $thread->getUid(), $threads)
		);

		$html = '';
		foreach ($threads as $thread) {
			if ($thread->getUid() === $excludeThreadUid) {
				continue;
			}

			$openingPost = $openingPosts[$thread->getUid()] ?? null;

			// escaped here, because a subject is stored as the poster typed it
			$subject = $openingPost
				? trim(commentFormatter::fieldToHtml($openingPost->getSubject(), $openingPost->getTextFormat()))
				: '';
			$replyCount = max(0, $thread->getPostCount() - 1);

			$html .= $this->moduleContext->adminPageRenderer->ParseBlock('MERGE_THREAD_ITEM', [
				'{$THREAD_UID}' => sanitizeStr($thread->getUid()),
				'{$THREAD_NUMBER}' => $thread->getOpNumber(),
				'{$THREAD_SUBJECT}' => $subject !== '' ? $subject : '(no subject)',
				'{$REPLY_COUNT}' => $replyCount === 1 ? '1 reply' : $replyCount . ' replies',
				'{$PREVIEW_ATTRIBUTES}' => $this->buildThreadPreviewAttributes($board, $thread, $openingPost),
			]);
		}

		return $html;
	}

	/**
	 * Attributes letting mergeThread.js preview a list entry's opening post on hover.
	 *
	 * The target id is the post element the board already renders, so a thread visible on the page
	 * previews without a request; the script falls back to fetching the post uid through the post
	 * API when it isn't there, which is the case on a thread page.
	 */
	private function buildThreadPreviewAttributes(IBoard $board, Thread $thread, ?Post $openingPost): string {
		if (!$openingPost) {
			return '';
		}

		return ' data-op-post-uid="' . sanitizeStr($openingPost->getUid()) . '"'
			. ' data-op-target-id="p' . sanitizeStr($board->getBoardUID()) . '_' . sanitizeStr($thread->getOpNumber()) . '"';
	}

	private function generateMoveThreadJsTemplate(): string {
		$boardRadioHTML = $this->generateAllBoardsRadioHTML(GLOBAL_BOARD_ARRAY);

		$templateValues = [
			'{$FORM_ACTION}' => $this->modulePageUrl,
			'{$THREAD_UID}' => '',
			'{$THREAD_NUMBER}' => '',
			'{$CURRENT_BOARD_UID}' => '',
			'{$CURRENT_BOARD_NAME}' => '',
			'{$BOARD_RADIO_HTML}' => $boardRadioHTML,
			'{$CSRF_TOKEN}' => getCsrfHiddenInput(),
		];

		$formHtml = $this->moduleContext->adminPageRenderer->ParseBlock('THREAD_MOVE_FORM', $templateValues);

		return $this->generateTemplate('moveThreadFormTemplate', $formHtml);
	}

	private function generateAllBoardsRadioHTML(array $boards): string {
		$html = '';
		foreach ($boards as $board) {
			$html .= $this->moduleContext->adminPageRenderer->ParseBlock('BOARD_RADIO_ITEM', [
				'{$BOARD_UID}'   => sanitizeStr($board->getBoardUID()),
				'{$BOARD_TITLE}' => sanitizeStr($board->getBoardTitle()),
			]);
		}
		return $html;
	}

	public function renderMoveThreadButton(string &$modfunc, Post $post, bool $noScript): void {
		// url to move thread page with thread uid as parameter
		$moveThreadButtonUrl = $this->generateMoveThreadUrl($post->getThreadUid());

		// append the move thread button to the modfunc string
		$modfunc .= generateModerateButton(
			$moveThreadButtonUrl,
			'MT',
			'Move thread',
			'adminMoveThreadFunction',
			$noScript
		);
	}

	public function renderMergeThreadButton(string &$modfunc, Post $post, bool $noScript): void {
		$modfunc .= generateModerateButton(
			$this->generateMergeThreadUrl($post->getThreadUid()),
			'MG',
			'Merge threads',
			'adminMergeThreadFunction',
			$noScript
		);
	}

	private function onRenderThreadWidget(array &$widgetArray, Post &$post): void {
		// generate move thread url
		$moveThreadUrl = $this->generateMoveThreadUrl($post->getThreadUid());

		$board = searchBoardArrayForBoard($post->getBoardUID());
		$boardName = $board
			? sanitizeStr($board->getBoardTitle()) . ' (' . sanitizeStr($post->getBoardUID()) . ')'
			: sanitizeStr($post->getBoardUID());

		// build the widget entry
		$moveThreadWidget = $this->buildWidgetEntry($moveThreadUrl, 'moveThread', 'Move thread', '', [
			'thread_number' => $post->getNumber(),
			'board_uid'     => $post->getBoardUID(),
			'board_name'    => $boardName,
		]);

		// add the widget to the array
		$widgetArray[] = $moveThreadWidget;

		// the merge window lists the board being viewed, so it only makes sense on the thread's own
		// board - elsewhere (the overboard) the plain page form handles it
		if ($post->getBoardUID() !== $this->moduleContext->board->getBoardUID()) {
			return;
		}

		$widgetArray[] = $this->buildWidgetEntry(
			$this->generateMergeThreadUrl($post->getThreadUid()),
			'mergeThread',
			'Merge threads',
			'',
			[
				'thread_uid'     => $post->getThreadUid(),
				'thread_number'  => $post->getNumber(),
				'thread_subject' => commentFormatter::fieldToPlainText($post->getSubject(), $post->getTextFormat()),
			]
		);
	}

	private function generateMergeThreadUrl(string $thread_uid): string {
		return $this->getModulePageURL(
			[
				'pageName' => 'merge',
				'thread_uid' => $thread_uid,
			],
			false,
			true
		);
	}

	private function generateMoveThreadUrl(string $thread_uid): string {
		// generate the move thread url
		$url = $this->getModulePageURL(
				[
					'thread_uid' => $thread_uid
				], 
				false, 
				true);
		
		// return url
		return $url;
	}

	private function leavePostInShadowThread(Thread $originalThread, IBoard $originalBoard, Thread $newThread, IBoard $destinationBoard) {
		// Generate cross-board quote link to the new thread
		$boardIdentifier = sanitizeStr($destinationBoard->getBoardIdentifier());
		$moveComment = 'Thread moved to &gt;&gt;&gt;/' . $boardIdentifier . '/' . $newThread->getOpNumber();

		$this->postSystemNotice($originalThread, $originalBoard, $moveComment, $newThread->getOpPostUid());
	}

	/**
	 * Post a System-chan notice into a thread, quote-linked to the post it points at.
	 *
	 * @param Thread $thread        Thread the notice is left in.
	 * @param IBoard $board         Board that thread belongs to.
	 * @param string $comment       Notice body, already escaped.
	 * @param int    $targetPostUid Post UID the notice's quote link resolves to.
	 */
	private function postSystemNotice(Thread $thread, IBoard $board, string $comment, int $targetPostUid): void {
		$boardConfig = $board->loadBoardConfig();

		$postDateFormatter = new postDateFormatter($boardConfig['TIME_ZONE']);
		$now = $postDateFormatter->formatFromTimestamp($this->moduleContext->request->getRequestTime());

		// Generate new post number
		$no = $board->incrementBoardPostNumber();

		$postRegistData = new postRegistData(
				$no,
				'SYSTEM',
				$thread->getUid(),
				false,
				'',
				'',
				'',
				$now,
				$boardConfig['SYSTEMCHAN_NAME'],
				'',
				'',
				'System',
				'',
				'',
				$comment,
				new IPAddress('127.0.0.1'),
				false,
				'',

			);

		// get next post uid
		$postUid = $this->moduleContext->postRepository->getNextPostUid();

		// Add the notice post
		$this->moduleContext->postService->addPostToThread($board, $postRegistData, $postUid);

		// Register quote link so the reference resolves in the renderer
		$this->moduleContext->quoteLinkService->createQuoteLinksFromArray(
			$board->getBoardUID(),
			$postUid,
			[$targetPostUid]
		);
	}


	private function copyThreadToBoard(array $filesToCopy, string $originalThreadUid, IBoard $destinationBoard): string {	
		// Step 1: Copy the thread and posts, receiving new thread UID and post UID mapping
		$copyResult = $this->moduleContext->threadService->copyThreadAndPosts($originalThreadUid, $destinationBoard);

		if (!is_array($copyResult)) {
			throw new Exception("copyThreadAndPosts() returned a non-array value.");
		}

		if (!isset($copyResult['threadUid'])) {
			throw new Exception("copyThreadAndPosts() result is missing 'threadUid'.");
		}

		if (!is_string($copyResult['threadUid'])) {
			throw new Exception("'threadUid' in copyThreadAndPosts() result is not a string.");
		}

		if (!isset($copyResult['postUidMap'])) {
			throw new Exception("copyThreadAndPosts() result is missing 'postUidMap'.");
		}

		if (!is_array($copyResult['postUidMap'])) {
			throw new Exception("'postUidMap' in copyThreadAndPosts() result is not an array.");
		}

		if (!isset($copyResult['fileIdMapping'])) {
			throw new Exception("copyThreadAndPosts() result is missing 'fileIdMapping'.");
		}

		if (!is_array($copyResult['fileIdMapping'])) {
			throw new Exception("'fileIdMapping' in copyThreadAndPosts() result is not an array.");
		}

		$newThreadUid   = $copyResult['threadUid'];
		$postUidMapping = $copyResult['postUidMap'];
		$fileIdMapping	= $copyResult['fileIdMapping'];
	
		// Step 2: Copy quote links using the post UID mapping
		$this->moduleContext->quoteLinkService->copyQuoteLinksFromThread($originalThreadUid, $destinationBoard->getBoardUID(), $postUidMapping);
	
		// Step 3: Copy attachment files
		$this->copyAttachmentsFiles($filesToCopy, $fileIdMapping, $destinationBoard);

		// Step 4: Return the UID of the newly created thread
		return $newThreadUid;
	}	
	
	private function copyAttachmentsFiles(array $attachments, array $fileIdMapping, IBoard $destinationBoard): void {
		$this->processAttachmentFiles($attachments, $fileIdMapping, $destinationBoard, true);
	}

	private function moveAttachmentFiles(array $attachments, IBoard $destinationBoard): void {
		$this->processAttachmentFiles($attachments, null, $destinationBoard, false);
	}

	private function processAttachmentFiles(array $attachments, ?array $fileIdMapping, IBoard $destinationBoard, bool $isCopy): void {
		// return early if there's no attachments to process
		if(empty($attachments)) {
			return;
		}

		// Destination board paths and config
		$destBasePath = $destinationBoard->getBoardUploadedFilesDirectory();
		$destConfig = $destinationBoard->loadBoardConfig();
		$baseDestImgPath = $destBasePath . $destConfig['IMG_DIR'];
		$baseDestThumbPath = $destBasePath . $destConfig['THUMB_DIR'];

		foreach ($attachments as $att) {
			// fetch board that the attachment belongs to
			$board = searchBoardArrayForBoard($att['boardUID']);

			// determine new file id
			$newFileId = $fileIdMapping[$att['fileId']] ?? $att['fileId'];

			// if this is a copy and mapping is required but missing, skip
			if ($isCopy && $fileIdMapping !== null && empty($fileIdMapping[$att['fileId']])) {
				continue;
			}

			// handle all hidden vs non-hidden behavior in one place
			if ($att['isHidden']) {
				// Hidden attachments live in the global attachment directory,
				// and both the image and thumbnail must use it as the source and destination.

				$srcImgPath = getGlobalAttachmentDirectory();
				$srcThumbPath = getGlobalAttachmentDirectory();

				$destImgPath = getGlobalAttachmentDirectory();
				$destThumbPath = getGlobalAttachmentDirectory();

				// Hidden files encode their fileId into the stored filename
				$baseSrcFilename =
					$att['storedFileName'] . '_' . $att['fileId'];

				$baseDestinationFilename =
					$att['storedFileName'] . '_' . $newFileId;
			}
			else {
				// Visible attachments are stored under the board’s normal directories.
				// Use the board where the attachment originated to determine the correct source paths.

				$srcImgPath =
					$board->getBoardUploadedFilesDirectory() . $board->getConfigValue('IMG_DIR');

				$srcThumbPath =
					$board->getBoardUploadedFilesDirectory() . $board->getConfigValue('THUMB_DIR');

				// Destination is always the destination board’s normal attachment dirs.
				$destImgPath = $baseDestImgPath;
				$destThumbPath = $baseDestThumbPath;

				// Non-hidden files do not append their fileId
				$baseSrcFilename = $att['storedFileName'];
				$baseDestinationFilename = $att['storedFileName'];
			}


			$srcImage = $srcImgPath . $baseSrcFilename . '.' . $att['fileExtension'];
			$destImage = $destImgPath . $baseDestinationFilename . '.' . $att['fileExtension'];
			$srcThumb = $srcThumbPath . $baseSrcFilename . 's.' . $destinationBoard->getConfigValue('THUMB_SETTING.Format');
			$destThumb = $destThumbPath . $baseDestinationFilename . 's.' . $destinationBoard->getConfigValue('THUMB_SETTING.Format');

			// Copy/move the image file if it exists
			$this->processFileOperation($srcImage, $destImage, $isCopy);

			// Copy/move the thumbnail file if it exists
			$this->processFileOperation($srcThumb, $destThumb, $isCopy);
		}
	}

	private function processFileOperation(string $srcFull, string $destFull, bool $isCopy): void {
		// file must exist
		if (!is_file($srcFull)) {
			return;
		}

		// skip if source and destination refer to the same file
		if (realpath($srcFull) === realpath($destFull)) {
			return;
		}

		if ($isCopy) {
			copy($srcFull, $destFull);
		} else {
			rename($srcFull, $destFull);
		}
	}

	private function handleThreadMove(ThreadData $thread, IBoard $hostBoard, IBoard $destinationBoard, bool $leaveShadowThread = true) {
		// redirect for url
		$threadRedirectUrl = '';

		$threadData = $thread->getThread();
		$threadUid = $threadData->getUid();
		$threadPosts = $thread->getPosts();

		// board uid of the destination board
		$destinationBoardUID = $destinationBoard->getBoardUID();

		$attachments = getAttachmentsFromPosts($threadPosts);

		// use thread redirection
		if($leaveShadowThread) { 
			// lock original thread and duplicate contents to destination board
			$newThreadUid = $this->copyThreadToBoard($attachments, $threadUid, $destinationBoard);

			$newThreadData = $this->moduleContext->threadRepository->getThreadByUid($newThreadUid, true);

			if (!$newThreadData) {
				throw new \RuntimeException('Failed to fetch newly created thread.');
			}

			// leave shadow post
			$this->leavePostInShadowThread($threadData, $hostBoard, $newThreadData, $destinationBoard);
			
			// opening post
			$openingPost = $thread->getOpeningPost();

			// lock thread
			$flags = $this->toggleThreadStatus($openingPost, 'stop');

			// make unmoveable
			$this->toggleThreadStatus($openingPost, 'ghost', $flags);

			$threadRedirectUrl = $destinationBoard->getBoardThreadURL($newThreadData->getOpNumber()); 
		} else {
			$this->moduleContext->postRedirectService->addNewRedirect($hostBoard->getBoardUID(), $destinationBoard->getBoardUID(), $threadUid);

			$this->moveAttachmentFiles($attachments, $destinationBoard);

			$this->moduleContext->threadService->moveThreadAndUpdate($threadUid, $destinationBoard);

			$this->moduleContext->quoteLinkService->moveQuoteLinksFromThread($threadUid, $destinationBoardUID);

			
			$threadRedirectUrl = $this->moduleContext->postRedirectService->resolveRedirectUrlFromThreadUID($threadUid);
		}

		// rebuild the boards' html
		$boardsToRebuild = [
			$hostBoard,
			$destinationBoard
		];

		rebuildBoardsByArray($boardsToRebuild);

		// return redirect
		return $threadRedirectUrl;
	}


	public function ModulePage() {
		$pageName = $this->moduleContext->request->getParameter('pageName', 'GET', '');

		// Show the merge or the move form
		if ($pageName === 'merge') {
			$formHtml = $this->moduleContext->adminPageRenderer->ParseBlock(
				'THREAD_MERGE_FORM',
				$this->prepareMergeFormTemplateValues()
			);
		} else {
			$formHtml = $this->moduleContext->adminPageRenderer->ParseBlock(
				'THREAD_MOVE_FORM',
				$this->prepareMoveFormTemplateValues()
			);
		}

		echo $this->moduleContext->adminPageRenderer->ParsePage(
			'GLOBAL_ADMIN_PAGE_CONTENT',
			['{$PAGE_CONTENT}' => $formHtml],
			true
		);
	}

	protected function handleModuleRequest(): void {
		if ($this->moduleContext->request->getParameter('merge-thread-action', 'POST')) {
			$this->handleMergeRequest();
			return;
		}

		$thread_uid = $this->moduleContext->request->getParameter('move-thread-uid', 'POST');
		$destinationBoardUID = $this->moduleContext->request->getParameter('radio-board-selection', 'POST');
		$leaveShadowThread = !empty($this->moduleContext->request->getParameter('leave-shadow-thread', 'POST'));

		// Validate inputs
		if (empty($thread_uid)) {
			throw new BoardException("Invalid thread_uid from request");
		}
		if (empty($destinationBoardUID)) {
			throw new BoardException("Invalid board uid from request");
		}

		// Retrieve thread and validate
		$thread = $this->moduleContext->threadService->getThreadAllReplies($thread_uid, true, $this->moduleContext->board->getConfigValue('RE_DEF'));
		if (!$thread) {
			throw new BoardException("Thread not found");
		}

		$threadOP = $thread->getOpeningPost();
		$threadStatus = $threadOP->getFlags();

		if ($threadStatus->value('ghost')) {
			throw new BoardException("Cannot move ghost threads");
		}

		// Get board objects
		$hostBoard = searchBoardArrayForBoard($thread->getThread()->getBoardUID());
		$destinationBoard = searchBoardArrayForBoard($destinationBoardUID);

		$redirectURL = '';
		$this->moduleContext->transactionManager->run(function () use (
			&$redirectURL,
			$thread,
			$hostBoard,
			$destinationBoard,
			$leaveShadowThread
		) {
			$redirectURL = $this->handleThreadMove(
				$thread,
				$hostBoard,
				$destinationBoard,
				$leaveShadowThread
			);
		});

		// Log the action
		$destinationBoardTitle = htmlspecialchars($destinationBoard->getBoardTitle());
		$this->moduleContext->actionLoggerService->logAction(
			"Moved thread No.{$thread->getThread()->getOpNumber()} to board $destinationBoardTitle",
			$hostBoard->getBoardUID(),
			actionType::POST_MOVE
		);

		if ($this->moduleContext->request->isAjax()) {
			sendJsonResponse(['redirectUrl' => $redirectURL]);
		}

		redirect($redirectURL);
	}
	

	/**
	 * Build the merge form for the thread named by the request, listing the rest of its board as
	 * candidate sources.
	 */
	private function prepareMergeFormTemplateValues(): array {
		$thread_uid = $this->moduleContext->request->getParameter('thread_uid', 'GET', '');

		if (!$thread_uid) {
			throw new InvalidArgumentException("No thread uid selected");
		}

		$thread = $this->moduleContext->threadService->getThreadData($thread_uid, true);

		if (!$thread) {
			throw new BoardException("Thread not found");
		}

		$board = searchBoardArrayForBoard($thread->getBoardUID());
		$openingPost = $this->moduleContext->postRepository->getOpeningPostFromThread($thread_uid, true);
		$subject = $openingPost
			? trim(commentFormatter::fieldToHtml($openingPost->getSubject(), $openingPost->getTextFormat()))
			: '';

		return [
			'{$FORM_ACTION}' => $this->modulePageUrl,
			'{$THREAD_UID}' => htmlspecialchars($thread_uid),
			'{$THREAD_NUMBER}' => $thread->getOpNumber(),
			'{$THREAD_SUBJECT}' => $subject !== '' ? $subject : '(no subject)',
			'{$THREAD_LIST_HTML}' => $this->generateThreadListHTML($board, $thread_uid),
			'{$CSRF_TOKEN}' => getCsrfHiddenInput(),
		];
	}

	/**
	 * Validate a merge submission, absorb the chosen threads into the destination and rebuild.
	 */
	private function handleMergeRequest(): void {
		$request = $this->moduleContext->request;

		$destinationThreadUid = $request->getParameter('merge-thread-uid', 'POST', '');

		if (empty($destinationThreadUid) || !is_string($destinationThreadUid)) {
			throw new BoardException("Invalid thread_uid from request");
		}

		$destinationThread = $this->moduleContext->threadService->getThreadData($destinationThreadUid, true);

		if (!$destinationThread) {
			throw new BoardException("Destination thread not found");
		}

		$board = searchBoardArrayForBoard($destinationThread->getBoardUID());

		if ($this->isGhostThread($destinationThreadUid)) {
			throw new BoardException("Cannot merge into a ghost thread");
		}

		$sourceThreadUids = $this->collectMergeSources($destinationThread, $board);
		$leaveShadowThreads = !empty($request->getParameter('leave-shadow-thread', 'POST'));

		$this->moduleContext->transactionManager->run(function () use (
			$destinationThread,
			$sourceThreadUids,
			$board,
			$leaveShadowThreads
		) {
			if ($leaveShadowThreads) {
				foreach ($sourceThreadUids as $sourceThreadUid) {
					$this->copyThreadIntoThread($sourceThreadUid, $destinationThread, $board);
				}

				return;
			}

			$this->moduleContext->threadService->mergeThreadsIntoThread(
				$destinationThread->getUid(),
				$sourceThreadUids
			);
		});

		rebuildBoardsByArray([$board]);

		$this->moduleContext->actionLoggerService->logAction(
			"Merged " . count($sourceThreadUids) . " thread(s) into thread No.{$destinationThread->getOpNumber()}",
			$board->getBoardUID(),
			actionType::POST_MOVE
		);

		$redirectURL = $board->getBoardThreadURL($destinationThread->getOpNumber());

		if ($request->isAjax()) {
			sendJsonResponse(['redirectUrl' => $redirectURL]);
		}

		redirect($redirectURL);
	}

	/**
	 * Resolve the ticked thread UIDs and the typed post numbers into a validated source list.
	 *
	 * @return string[] Thread UIDs, guaranteed to share the destination's board.
	 */
	private function collectMergeSources(Thread $destinationThread, IBoard $board): array {
		$request = $this->moduleContext->request;

		$selectedUids = $request->getParameter('merge-source-uids', 'POST', []);
		$selectedUids = is_array($selectedUids) ? array_map('strval', $selectedUids) : [];

		// threads too old to appear in the list are named by post number instead
		$typedNumbers = (string)$request->getParameter('merge-source-numbers', 'POST', '');
		foreach (preg_split('/[^0-9]+/', $typedNumbers, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $postNumber) {
			$resolvedUid = $this->moduleContext->threadRepository->resolveThreadUidFromResno($board, (int)$postNumber);

			if (!$resolvedUid) {
				throw new BoardException("No thread found with post number $postNumber on this board");
			}

			$selectedUids[] = (string)$resolvedUid;
		}

		$selectedUids = array_values(array_diff(array_unique($selectedUids), [$destinationThread->getUid()]));

		if (empty($selectedUids)) {
			throw new BoardException("No threads selected to merge");
		}

		foreach ($selectedUids as $sourceThreadUid) {
			$sourceThread = $this->moduleContext->threadService->getThreadData($sourceThreadUid, true);

			if (!$sourceThread) {
				throw new BoardException("Thread not found");
			}

			if ($sourceThread->getBoardUID() !== $destinationThread->getBoardUID()) {
				throw new BoardException("Threads can only be merged within the same board");
			}

			if ($this->isGhostThread($sourceThreadUid)) {
				throw new BoardException("Cannot merge ghost threads");
			}
		}

		return $selectedUids;
	}

	private function isGhostThread(string $threadUid): bool {
		$openingPost = $this->moduleContext->postRepository->getOpeningPostFromThread($threadUid, true);

		return $openingPost ? $openingPost->getFlags()->value('ghost') : false;
	}

	/**
	 * Shadow variant of a merge: duplicate a thread's posts into the destination, then lock the
	 * original and point it at where its posts went.
	 */
	private function copyThreadIntoThread(string $sourceThreadUid, Thread $destinationThread, IBoard $board): void {
		$sourceThread = $this->moduleContext->threadService->getThreadAllReplies(
			$sourceThreadUid,
			true,
			$board->getConfigValue('RE_DEF')
		);

		if (!$sourceThread) {
			throw new BoardException("Thread not found");
		}

		$copyResult = $this->moduleContext->threadService->copyThreadPostsIntoThread(
			$sourceThreadUid,
			$destinationThread->getUid(),
			$board
		);

		$this->moduleContext->quoteLinkService->copyQuoteLinksFromThread(
			$sourceThreadUid,
			$board->getBoardUID(),
			$copyResult['postUidMap']
		);

		$this->copyAttachmentsFiles(
			getAttachmentsFromPosts($sourceThread->getPosts()),
			$copyResult['fileIdMapping'],
			$board
		);

		$this->postSystemNotice(
			$sourceThread->getThread(),
			$board,
			'Thread merged into &gt;&gt;' . $destinationThread->getOpNumber(),
			$destinationThread->getOpPostUid()
		);

		// lock the emptied original and make it unmoveable
		$openingPost = $sourceThread->getOpeningPost();
		$flags = $this->toggleThreadStatus($openingPost, 'stop');
		$this->toggleThreadStatus($openingPost, 'ghost', $flags);
	}

	private function prepareMoveFormTemplateValues(): array {
		$thread_uid = $this->moduleContext->request->getParameter('thread_uid', 'GET', '');

		if (!$thread_uid) {
			throw new InvalidArgumentException("No thread uid selected");
		}
		$thread = $this->moduleContext->threadService->getThreadData($thread_uid, true);
		$threadNumber = $this->moduleContext->threadRepository->resolveThreadNumberFromUID($thread_uid);
		$threadParentBoard = searchBoardArrayForBoard($thread->getBoardUID());

		$boardRadioHTML = generateBoardListRadioHTML($threadParentBoard, GLOBAL_BOARD_ARRAY);

		return [
			'{$FORM_ACTION}' => $this->modulePageUrl,
			'{$THREAD_UID}' => htmlspecialchars($thread_uid),
			'{$THREAD_NUMBER}' => $threadNumber,
			'{$CURRENT_BOARD_UID}' => $threadParentBoard->getBoardUID(),
			'{$CURRENT_BOARD_NAME}' => htmlspecialchars($threadParentBoard->getBoardTitle()) . ' (' . $threadParentBoard->getBoardUID() . ')',
			'{$BOARD_RADIO_HTML}' => $boardRadioHTML,
			'{$CSRF_TOKEN}' => getCsrfHiddenInput(),
		];
	}

	private function toggleThreadStatus(Post $openingPost, string $flag, ?FlagHelper $flags = null): FlagHelper {
		// Use provided flags or create helper with current status
		if ($flags === null) {
			$flags = $openingPost->getFlags();
		}

		// Toggle the specified flag
		$flags->toggle($flag);

		// Save the updated status back to the post
		$this->moduleContext->postRepository->setPostStatus($openingPost->getUid(), $flags->toString());

		// Return updated flags
		return $flags;
	}
}
