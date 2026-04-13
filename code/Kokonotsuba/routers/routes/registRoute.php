<?php

// regist route - inserts a post/thread from user input

namespace Kokonotsuba\routers\routes;

use Kokonotsuba\board\board;
use Kokonotsuba\post\postValidator;
use Kokonotsuba\account\staffAccountFromSession;
use Kokonotsuba\cookie\cookieService;
use Kokonotsuba\database\transactionManager;
use Kokonotsuba\module_classes\moduleEngine;
use Kokonotsuba\action_log\actionLoggerService;
use Kokonotsuba\post\postRepository;
use Kokonotsuba\post\postService;
use Kokonotsuba\post\attachment\fileService;
use Kokonotsuba\thread\threadRepository;
use Kokonotsuba\thread\threadService;
use Kokonotsuba\quote_link\quoteLinkService;
use Kokonotsuba\post\helper\thumbnailCreator;
use Kokonotsuba\post\helper\defaultTextFiller;
use Kokonotsuba\post\helper\fortuneGenerator;
use Kokonotsuba\post\helper\postFilterApplier;
use Kokonotsuba\post\helper\postDateFormatter;
use Kokonotsuba\post\helper\agingHandler;
use Kokonotsuba\post\helper\webhookDispatcher;
use Kokonotsuba\post\postRegistData;
use Kokonotsuba\renderers\postRenderer;
use Kokonotsuba\file\postFileUploadController;
use Kokonotsuba\request\request;
use Kokonotsuba\userRole;
use Kokonotsuba\error\BoardException;
use Kokonotsuba\thread\ThreadData;

use function Puchiko\request\redirect;
use function Kokonotsuba\libraries\loadUploadData;
use function Kokonotsuba\libraries\getUserFileFromRequest;
use function Kokonotsuba\libraries\getThumbnailFromFile;
use function Kokonotsuba\libraries\scaleThumbnail;
use function Puchiko\strings\strlenUnicode;
use function Kokonotsuba\libraries\_T;
use function Kokonotsuba\libraries\getPageOfThread;
use function Kokonotsuba\libraries\isActiveStaffSession;
use function Kokonotsuba\libraries\searchBoardArrayForBoardByIdentifier;
use function Puchiko\json\sendAjaxAndDetach;

class registRoute {
    public function __construct(private board $board, 
        private readonly array $config,
        private readonly postValidator $postValidator,
        private readonly staffAccountFromSession $staffSession,
		private transactionManager $transactionManager,
        private moduleEngine $moduleEngine,
        private readonly actionLoggerService $actionLoggerService,
		private readonly cookieService $cookieService,
		private readonly postRepository $postRepository,
        private readonly postService $postService,
		private readonly fileService $fileService,
		private readonly threadRepository $threadRepository,
		private readonly threadService $threadService,
		private readonly quoteLinkService $quoteLinkService,
		private readonly request $request
	) {}

    /* Write to post table */
	public function registerPostToDatabase() {
		// Upload board cached path
		$this->board->updateBoardPathCache();

		// get the thread list before the insertion
		// this is used so the correct page is rebuilt for threads bumped to the top of the index
		$preInsertThreadList = $this->threadService->getThreadListFromBoard($this->board);

		// Initialize file directories
		$thumbDir = $this->board->getBoardUploadedFilesDirectory() . $this->config['THUMB_DIR'];
		$imgDir = $this->board->getBoardUploadedFilesDirectory() . $this->config['IMG_DIR'];

		// Initialize core handlers
		$thumbnailCreator = new thumbnailCreator($this->config['USE_THUMB'], $this->config['THUMB_SETTING'], $thumbDir);
		$defaultTextFiller = new defaultTextFiller($this->config);
		$fortuneGenerator = new fortuneGenerator($this->config['FORTUNES']);
		$postFilterApplier = new postFilterApplier($this->config, $fortuneGenerator);
		$postDateFormatter = new postDateFormatter($this->config['TIME_ZONE']);
		$agingHandler = new agingHandler($this->config, $this->threadRepository);
		$webhookDispatcher = new webhookDispatcher($this->board, $this->config);

		// Declare variables to be passed by reference
		$postData = [];
		$fileMeta = [];
		$computedPostInfo = [];
		$emailForInsertion = '';
		$redirect = '';
		$newPostsHtml = [];
		$thread = false;

		// Begin transaction
		$this->transactionManager->run(function () use (
			&$postData,
			&$fileMeta,
			&$computedPostInfo,
			&$emailForInsertion,
			&$redirect,
			&$thread,
			$imgDir,
			$thumbnailCreator,
			$defaultTextFiller,
			$postFilterApplier,
			$postDateFormatter,
			$agingHandler,
		) {

			// Get the thread list before insert
			$threadList = $this->threadService->getThreadListFromBoard($this->board);

			// Step 1: Validate regist request
			$this->postValidator->registValidate();

			// Step 2: Gather POST input data
			$postData = $this->gatherPostInputData();

			// get preview count
			$previewCount = $this->board->getConfigValue('RE_DEF', 5);

			// get the amount of recent replies to fetch
			$amountOfRepliesToRender = $this->board->getConfigValue('LAST_AMOUNT_OF_REPLIES', 50);

			// Get the thread data (if it exists)
			$thread = $this->threadService->getThreadLastReplies($postData['thread_uid'], false, $previewCount, $amountOfRepliesToRender);
			// Step 3: Verify that thread exists
			$this->postValidator->threadSanityCheck($postData['postOpRoot'], $postData['flgh'], $postData['thread_uid'], $postData['resno'], $postData['threadDeleted'], $thread);

			// Step 4: Process uploaded file (if any)
			$fileMeta = $this->handleFileUpload($postData['isReply'], $thumbnailCreator, $imgDir);

			// Step 5: Validate & clean post content
			$this->validateAndCleanPostContent($postData, $fileMeta['files'], $postData['is_admin'], $thread);

			// Step 6: Handle tripcode, default text, filters, and categories
			$this->processPostDetails($postData, $defaultTextFiller, $postFilterApplier);

			// Step 7: Final data prep (timestamps, password hashing, unique ID)
			$computedPostInfo = $this->preparePostMetadata($postData, $postDateFormatter, $fileMeta['files']);

			// Step 8: Validate post for database storage
			$this->postValidator->validateForDatabase(
				$postData['pwdc'], $fileMeta['md5'], $computedPostInfo['dest'], $postData['roleLevel']
			);

			// Age/sage logic
			$agingHandler->apply($postData['thread_uid'], $postData['time'], $postData['postOpRoot'], $postData['email'], $postData['age']);

			// Generate redirect URL and sanitize
			$redirect = $this->generateRedirectURL($computedPostInfo['no'], $postData['email'], $postData['resno'], $computedPostInfo['timeInMilliseconds']);

			// Remove noko/nonoko and dump
			$emailForInsertion = $this->preparePostEmail($postData['email']);

			// Commit pre-write hook
			$this->moduleEngine->dispatch('RegistBeforeCommit', [
				&$postData['name'], &$postData['email'], &$emailForInsertion, &$postData['sub'], &$postData['comment'],
				&$postData['category'], &$postData['age'], $fileMeta['files'],
				$postData['isReply'], &$postData['status'], $thread, &$computedPostInfo['poster_hash']
			]);

			// regist data for post
			$postRegistData = new postRegistData(
				$computedPostInfo['no'],
				$computedPostInfo['poster_hash'],
				$postData['thread_uid'],
				$computedPostInfo['is_op'],
				$postData['category'],
				$computedPostInfo['password_hash'],
				$computedPostInfo['now'],
				$postData['name'],
				$postData['tripcode'] ?? '',
				$postData['secure_tripcode'] ?? '',
				$postData['capcode'],
				$emailForInsertion,
				$postData['sub'],
				$postData['comment'],
				$postData['ip'],
				$postData['age'],
				$postData['status']
			);

			// get the post uid
			$nextPostUid = $this->postRepository->getNextPostUid();

			// Add post to database
			$this->postService->addPostToThread($this->board, $postRegistData, $nextPostUid);

			// Handle adding attachments
			$this->handleAttachments($fileMeta['files'], $nextPostUid); 

			// Log and hooks
			$this->actionLoggerService->logAction("Post No.{$computedPostInfo['no']} registered", $this->board->getBoardUID());
			$this->moduleEngine->dispatch('RegistAfterCommit', [$this->board->getLastPostNoFromBoard(), $postData['thread_uid'], $postData['name'], $postData['email'], $postData['sub'], $postData['comment']]);
			
			// get post-insert post data
			$afterInsertPost = $this->postRepository->getPostByUid($nextPostUid);
			
			// get post-insert attachments
			$attachments = $afterInsertPost->getAttachments() ?? [];
			
			// run attachments after insert hook point
			$this->moduleEngine->dispatch('AttachmentsAfterInsert', [&$attachments]);
			
			// Handle quote links
			$this->handlePostQuoteLink($computedPostInfo['no'], $postData['comment']);

			// Get the updated thread list
			$threadList = $this->threadService->getThreadListFromBoard($this->board);

			// Prune old threads
			$this->postValidator->pruneOld($threadList);
		});

		// Set cookies for password and email
		$this->cookieService->set('pwdc', $postData['pwd'], time()+7*24*3600);
		$this->cookieService->set('emailc', htmlspecialchars_decode($postData['email']), time()+7*24*3600);

		// Save files
		foreach($fileMeta['files'] as $entry) {
			// get file object
			$file = $entry['file'];

			// save attachment + thumbnail 
			$this->saveUploadedPostFiles($entry['postFileUploadController'], $file->getExtention());
		}

		// Render new replies to HTML for instant client-side insertion (noko/dump)
		// Done after file saving so images/thumbnails exist on disk when HTML is served
		if ($this->request->isAjax() && $postData['isReply']) {
			$lastPostNo = intval($this->request->getParameter('lastPostNo', 'POST', 0));
			$newPostsHtml = $this->renderNewRepliesHtml(
				$postData['thread_uid'],
				$postData['resno'],
				$lastPostNo,
				$thread
			);
		}

		// Dispatch webhook
		$webhookDispatcher->dispatch($postData['resno'], $computedPostInfo['no']);

		// Handle javascript/json request
		// It will exit after a successful javascript request
		$this->handleJsonOutput(
			$computedPostInfo,
			$postData,
			$preInsertThreadList,
			$computedPostInfo['no'],
			$this->board->getBoardUID(),
			$redirect,
			$newPostsHtml,
		);
	}

	private function gatherPostInputData(): array {
		// Extract tripcode from raw name before HTML escaping
		$rawName = $this->request->getParameter('name', 'POST', '');
		$email = htmlspecialchars($this->request->getParameter('email', 'POST', ''));
		$sub = htmlspecialchars($this->request->getParameter('sub', 'POST', ''));
		$comment = htmlspecialchars($this->request->getParameter('com', 'POST', ''));
		$pwd = $this->request->getParameter('pwd', 'POST', '');
		$category = htmlspecialchars($this->request->getParameter('category', 'POST', ''));
		$resno = intval($this->request->getParameter('resto', 'POST', 0));
		$pwdc = $this->cookieService->get('pwdc', '');
	
		$ip = $this->request->userIp();
		
		$age = false;
	
		$thread_uid = $this->threadRepository->resolveThreadUidFromResno($this->board, $resno);
		$isReply = $thread_uid ? true : false;
	
		$roleLevel = $this->staffSession->getRoleLevel();
		$time = $this->request->getRequestTime();
		$timeInMilliseconds = intval($this->request->getRequestTimeFloat() * 1000);
	
		$postOpRoot = 0;
		$flgh = '';
		$threadDeleted = empty($this->threadRepository->getThreadByUid($thread_uid, false));
		$up_incomplete = 0;
		$is_admin = $roleLevel === userRole::LEV_ADMIN;

		// Parse tripcode from raw name before HTML escaping
		$tripcode = '';
		$secure_tripcode = '';
		$staffCapcodeInput = '';

		// Extract staff capcode first ( ## Capcode at the end, with leading space)
		// This must be done before tripcode parsing so "name#trip ## Mod" works
		if (preg_match('/\s##\s+(.+)$/', $rawName, $capcodeMatch)) {
			$staffCapcodeInput = trim($capcodeMatch[1]);
			$rawName = substr($rawName, 0, -strlen($capcodeMatch[0]));
		}

		// Parse tripcode from remaining name
		[$nameOnly, $tripcode, $secure_tripcode] = array_map('trim', explode('#', $rawName . '##'));

		// If a staff capcode was extracted, pass it via secure_tripcode_input
		// (only when secure_tripcode isn't already set from a ##securetrip)
		if ($staffCapcodeInput !== '' && $secure_tripcode === '') {
			$secure_tripcode = $staffCapcodeInput;
		}
		
		// Now apply HTML escaping to the name portion and store full name for cookie
		$name = htmlspecialchars($nameOnly);
		
		// Store the raw name (with tripcode) in a separate variable for cookie storage
		$nameCookie = $rawName;

		return [ 'nameCookie' => $nameCookie, 'name' => $name, 'tripcode_input' => $tripcode, 'secure_tripcode_input' => $secure_tripcode,
			 'tripcode' => '', 'secure_tripcode' => '', 'capcode' => '', 'email' => $email, 'sub' => $sub, 'comment' => $comment, 'pwd' => $pwd,
			 'category' => $category, 'resno' => $resno, 'pwdc' => $pwdc, 'ip' => $ip,
			 'thread_uid' => $thread_uid, 'isReply' => $isReply, 'roleLevel' => $roleLevel, 'time' => $time,
			 'timeInMilliseconds' => $timeInMilliseconds, 'postOpRoot' => $postOpRoot, 'flgh' => $flgh, 'age' => $age, 'status' => '',
			 'threadDeleted' => $threadDeleted, 'up_incomplete' => $up_incomplete, 'is_admin' => $is_admin
		];
	}

	private function handleFileUpload(bool $isReply, thumbnailCreator $thumbnailCreator, string $boardFileDirectory): array {
		// init file arrays
		$fileMetaList = [];
		$postFileUploadControllerList = [];

		// determine if multiple files are uploaded on the main input
		$upfileData = $this->request->getFile('upfile');
		$hasMultiUpfile =
			isset($upfileData['tmp_name']) &&
			is_array($upfileData['tmp_name']) &&
			count(array_filter($upfileData['tmp_name'])) > 0;

		// determine if multiple files are uploaded on quick reply
		$quickReplyData = $this->request->getFile('quickReplyUpFile');
		$hasMultiQuickReply =
			isset($quickReplyData['tmp_name']) &&
			is_array($quickReplyData['tmp_name']) &&
			count(array_filter($quickReplyData['tmp_name'])) > 0;

		// pick which input to use
		$inputName = $hasMultiUpfile ? 'upfile' : ($hasMultiQuickReply ? 'quickReplyUpFile' : null);

		// NO FILES → if OP post & imageboard mode, throw exception
		if ($inputName === null) {
			// text-board only mode flag
			$textBoardOnly = $this->board->getConfigValue('TEXTBOARD_ONLY');

			// whether its optional to post an attachment when posting a thread OP
			$threadAttachmentRequired = $this->board->getConfigValue('THREAD_ATTACHMENT_REQUIRED', true);

			// if its not a reply, board isn't in textboard only mode, and OP attachments aren't optional then throw an error		
			if (!$isReply && !$textBoardOnly && $threadAttachmentRequired) {
				throw new BoardException(_T('regist_upload_noimg'));
			}

			// still return required structure
			return ['files' => []];
		}

		// get attachment limit
		$attachmentUploadLimit = $this->board->getConfigValue('ATTACHMENT_UPLOAD_LIMIT', 1);

		// ----------------------------------------
		// LOOP THROUGH ALL FILES IN THE INPUT
		// ----------------------------------------
		$fileCount = count($this->request->getFile($inputName)['tmp_name']);

		// if the file count is above the limit, we will only process up to the limit to prevent errors
		if ($fileCount > $attachmentUploadLimit) {
			$fileCount = $attachmentUploadLimit;
		}

		for ($i = 0; $i < $fileCount && $i < $attachmentUploadLimit; $i++) {
			// break loop if iterator is above limit
			if($i >= $attachmentUploadLimit) {
				break;
			}

			// load indexed upload data
			[$tmp, $name, $status] = loadUploadData($inputName, $i, $this->request);

			// skip empty slots
			if ($status === UPLOAD_ERR_NO_FILE || !$tmp) {
				continue;
			}

			// convert raw PHP file into fileFromUpload object
			$fileFromUpload = getUserFileFromRequest($tmp, $name, $status, $i, $this->request);

			// extract file object
			$file = $fileFromUpload->getFile();

			// generate thumbnail
			$thumbnail = getThumbnailFromFile($file);
			$thumbnail = scaleThumbnail(
				$thumbnail,
				$isReply,
				$this->config['MAX_RW'],
				$this->config['MAX_RH'],
				$this->config['MAX_W'],
				$this->config['MAX_H']
			);

			// create upload controller per file
			$postFileUploadController = new postFileUploadController(
				$this->config,
				$fileFromUpload,
				$thumbnailCreator,
				$thumbnail,
				$boardFileDirectory,
				$fileCount
			);

			// validate this specific file
			$postFileUploadController->validateFile();

			// store reference for saving later
			$postFileUploadControllerList[] = $postFileUploadController;

			// push file meta block
			$fileMetaList[] = [
				'index' => $i,
				'file' => $file,
				'thumbnail' => $thumbnail,
				'postFileUploadController' => $postFileUploadController,
				'status' => $status,
				'fileName' => $file->getFileName(),
				'ext' => $file->getExtention(),
				'fileTimeInMilliseconds' => $file->getTimeInMilliseconds(),
				'md5' => $file->getMd5Chksum(),
				'imgW' => $file->getImageWidth(),
				'imgH' => $file->getImageHeight(),
				'thumbWidth' => $thumbnail->getThumbnailWidth(),
				'thumbHeight' => $thumbnail->getThumbnailHeight(),
				'fileSize' => $file->getFileSize(),
				'mimeType' => $file->getMimeType(),
			];
		}

		// if the user somehow selected multiple files but the board is set to only allow 1 attachment, we will only process the first file to prevent errors
		if (
			count($fileMetaList) > 1 
			&& $attachmentUploadLimit < 2
			&& isset($fileMetaList[0])
		) {
			$fileMetaList = [$fileMetaList[0]];
		}

		// return all files
		return ['files' => $fileMetaList];
	}

	private function validateAndCleanPostContent(array &$postData, array $files, bool $isAdmin, bool|ThreadData $thread): void {
		$this->postValidator->spamValidate($postData['name'], $postData['email'], $postData['sub'], $postData['comment']);
	
		$registInfo = [
			'name' => &$postData['name'], 
			'email' => &$postData['email'], 
			'sub' => &$postData['sub'],
			'com' => &$postData['comment'],
			'tripcode' => &$postData['tripcode'],
			'secure_tripcode' => &$postData['secure_tripcode'],
			'tripcode_input' => &$postData['tripcode_input'],
			'secure_tripcode_input' => &$postData['secure_tripcode_input'],
			'capcode' => &$postData['capcode'],
			'age' => &$postData['age'],
			'files' => $files,
			'ip' => $postData['ip'], 
			'isThreadSubmit' => empty($postData['thread_uid']),
			'roleLevel' => $postData['roleLevel'],
			'thread' => $thread,
		];

		$this->moduleEngine->dispatch('RegistBegin', [&$registInfo]);
	
		if (strlenUnicode($postData['name']) > $this->config['INPUT_MAX']) throw new BoardException(_T('regist_nametoolong'));
		if (strlenUnicode($postData['email']) > $this->config['INPUT_MAX']) throw new BoardException(_T('regist_emailtoolong'));
		if (strlenUnicode($postData['sub']) > $this->config['INPUT_MAX']) throw new BoardException(_T('regist_topictoolong'));
		if (strlenUnicode($postData['pwd']) > $this->config['INPUT_MAX']) throw new BoardException(_T('regist_passtoolong'));

		$this->cookieService->setRaw('namec', rawurlencode(htmlspecialchars_decode($postData['nameCookie'])), time() + 7 * 24 * 3600);
	
		$postData['email'] = str_replace("\r\n", '', $postData['email']);
		$postData['sub'] = str_replace("\r\n", '', $postData['sub']);
	
		$postData['comment'] = $this->postValidator->cleanComment($postData['comment'], $files, $isAdmin);
		
		// Ensure name is not empty or whitespace
		$postData['name'] = $this->ensureNameSet($postData['name']);
	}

	// Ensure the name is set; use default or trigger error if not
	private function ensureNameSet(string $name): string {
		if (!$name || preg_match("/^[ |　|]*$/", $name)) {
			if ($this->config['ALLOW_NONAME']) {
				// Assign default name if allowed
				$name = $this->config['DEFAULT_NONAME'];
			} else {
				// Otherwise, trigger an error
				throw new BoardException(_T('regist_withoutname'));
			}
		}

		// return modified name
		return $name;
	}

	private function processPostDetails(array &$postData, defaultTextFiller $defaultTextFiller, postFilterApplier $postFilterApplier): void {
		$defaultTextFiller->fill($postData['sub'], $postData['comment']);
		$postFilterApplier->applyFilters($postData['comment'], $postData['email']);
	
		if ($postData['category'] && $this->config['USE_CATEGORY']) {
			$categories = explode(',', $postData['category']);
			$postData['category'] = ',' . implode(',', array_map('trim', $categories)) . ',';
		} else {
			$postData['category'] = '';
		}
	
		if ($postData['up_incomplete']) {
			$postData['comment'] .= '<p class="incompleteFile"><span class="warning">' . _T('notice_incompletefile') . '</span></p>';
		}
	}

	// generate url for redirect after post
	private function generateRedirectURL(int $no, string $email, int $threadResno, int $timeInMilliseconds): string {		
		// If $threadResno is 0, this is a new thread; set it to the current post number ($no)
		if($threadResno === 0) {
			$threadResno = $no;
		}

		// get always noko config value for the noko condition
		// default to false
		$alwaysNoko = $this->board->getConfigValue('ALWAYS_NOKO', false);

		// if noko is inside the email-field or always noko is enabled, then redirect to the thread
		if((strstr($email, 'noko') && !strstr($email, 'nonoko')) || ($alwaysNoko && !strstr($email, 'nonoko'))) {
			$redirectReplyNumber = $no;
			$redirect = $this->board->getBoardThreadURL($redirectReplyNumber);
		} elseif(strstr($email, 'dump')) {
			// if 'dump' is contained in the email-field then dont redirect to the reply by setting it to 0
			$redirectReplyNumber = 0;
			$redirect = $this->board->getBoardThreadURL($redirectReplyNumber);
		} else {
			// default to board index if neither noko nor dump
			$redirect = $this->config['STATIC_INDEX_FILE'] . '?' . $timeInMilliseconds;
		}

		// return processed redirect
		return $redirect;
	}

	// remove options from email
	private function preparePostEmail(string $email): string {
		// remove "noko" from the post email since most posts will contain it
		$email = preg_replace('/^(no)+ko\d*$/i', '', $email);

		// remove "dump" from the email field
		$email = preg_replace('/(?<!\S)dump(?!\S)/i', '', $email);

		return $email;
	}

	private function handleAttachments(array $files, int $postUid): void {
		// loop through files and insert attachments
		foreach($files as $f) {
			// construct the the stored file name
			// milisecond timestamp
			$storedFileName = $f['fileTimeInMilliseconds'];

			// append index if theres more than 1 attachment being uploaded
			if(count($files) > 1) {
				$storedFileName .= '_' . $f['index'];
			}

			// select the file object
			$this->fileService->addFile(
				$postUid,
				$f['fileName'],
				$storedFileName,
				$f['ext'],
				$f['md5'],
				$f['imgW'],
				$f['imgH'],
				$f['thumbWidth'],
				$f['thumbHeight'],
				$f['fileSize'],
				$f['mimeType'],
				false,
			);
		}
	}

	// prepare post meta data
	private function preparePostMetadata(array &$postData, postDateFormatter $postDateFormatter, array $files): array {
		if ($postData['pwd'] == '') {
			$postData['pwd'] = ($postData['pwdc'] == '') ? substr(rand(), 0, 12) : $postData['pwdc'];
		}

		// generate the password hash
		$passwordHash = $this->generatePasswordHash($postData['pwd']);

		$no = $this->board->incrementBoardPostNumber();
		$is_op = $postData['resno'] ? false : true;
		$now = $postDateFormatter->formatFromTimestamp($postData['time']);

		return [
			'no' => $no,
			'is_op' => $is_op,
			'password_hash' => $passwordHash,
			'now' => $now,
			'poster_hash' => '',
			'dest' => !empty($files),
			'timeInMilliseconds' => $postData['timeInMilliseconds']
		];
	}
	
	private function generatePasswordHash(string $password): string {
		// cost of the password
		// the higher the cost - the longer it takes to generate, but harder to bruteforce
		// since a password is generated for everyone post, we'll keep the cost low
		$cost = 9;

		// options for the bcrypt hash
		$options = [
			'cost' => $cost,
		];

		// hash the password
		$passwordHash = password_hash($password, PASSWORD_BCRYPT, $options);

		// return hash
		return $passwordHash;
	}

	// Processes quote links (e.g., >>123 or >>No.123 or >>>/board/123) in a post's comment
	private function handlePostQuoteLink(int $postNumber, string $postComment) {
		$allQuoteLinkedPostUids = [];

		// Match same-board quote patterns like ">>123" or ">>No.123"
		if(preg_match_all('/((?:&gt;|＞){2})(?:No\.)?(\d+)/i', $postComment, $matches, PREG_SET_ORDER)) {
			$uniqueMatches = [];
			foreach ($matches as $match) {
				if (!in_array($match, $uniqueMatches)) {
					$uniqueMatches[] = $match;
				}
			}
	
			$quoteLinkedPostNumbers = [];
			foreach ($uniqueMatches as $match) {
				$quoteLinkedPostNumbers[] = $match[2];
			}
	
			$quoteLinkedPostUids = $this->postRepository->resolvePostUidsFromArray($this->board, $quoteLinkedPostNumbers);
			$allQuoteLinkedPostUids = array_merge($allQuoteLinkedPostUids, array_values($quoteLinkedPostUids));
		}

		// Match cross-board quote patterns like ">>>/c/123"
		if(preg_match_all('/((?:&gt;|＞){3})\/([a-zA-Z0-9]+)\/(\d+)/i', $postComment, $crossMatches, PREG_SET_ORDER)) {
			$crossBoardPosts = [];
			foreach ($crossMatches as $match) {
				$boardIdentifier = $match[2];
				$postNo = $match[3];
				$crossBoardPosts[$boardIdentifier][] = $postNo;
			}

			foreach ($crossBoardPosts as $identifier => $postNumbers) {
				$targetBoard = searchBoardArrayForBoardByIdentifier($identifier);
				if (!$targetBoard) continue;

				$postNumbers = array_unique($postNumbers);
				$resolvedUids = $this->postRepository->resolvePostUidsFromArray($targetBoard, $postNumbers);
				$allQuoteLinkedPostUids = array_merge($allQuoteLinkedPostUids, array_values($resolvedUids));
			}
		}

		if (!empty($allQuoteLinkedPostUids)) {
			$postUid = $this->postRepository->resolvePostUidFromPostNumber($this->board, $postNumber);
			$allQuoteLinkedPostUids = array_unique($allQuoteLinkedPostUids);
			$this->quoteLinkService->createQuoteLinksFromArray($this->board->getBoardUID(), $postUid, $allQuoteLinkedPostUids);
		}
	}

	private function shouldRebuildAfterDetach(array $postData): bool {
		// the poster's email
		$email = $postData['email'] ?? '';
		
		// always noko
		$alwaysNoko = $this->board->getConfigValue('ALWAYS_NOKO', false);
		
		// noko
		$isNoko = (strstr($email, 'noko') && !strstr($email, 'nonoko')) || ($alwaysNoko && !strstr($email, 'nonoko'));
		
		// dump
		$isDump = strstr($email, 'dump');
		
		// for noko or dump we'll return true to trigger page rebuild after sending JSON
		return $isNoko || $isDump;
	}

	private function handleJsonDetach(
		array $postData, 
		array $computedPostInfo, 
		array $preInsertThreadList,
		array $registJsonData
	): void {
		// If the post is marked with "noko" or "dump", we want to trigger the page rebuild before sending the JSON response to ensure the client receives the updated page state.
		if ($this->shouldRebuildAfterDetach($postData)) {
			sendAjaxAndDetach($registJsonData);
			$this->handlePageRebuilding($computedPostInfo, $postData, $preInsertThreadList);
			exit;
		} 
		// Otherwise, we can send the JSON response first and then handle the page rebuilding.
		else {
			$this->handlePageRebuilding($computedPostInfo, $postData, $preInsertThreadList);
			sendAjaxAndDetach($registJsonData);
			exit;
		}
	}

	private function handleJsonOutput(
		array $computedPostInfo, 
		array $postData, 
		array $preInsertThreadList,
		int $postNumber, 
		int $boardUid, 
		string $redirectUrl,
		array $newPostsHtml = []
	): void {
		// If it's a JavaScript request, return JSON response with post ID and redirect URL
		if($this->request->isAjax()) {
			// Construct the JSON response data
			$registJsonData = [
				'postId' => "p{$boardUid}_{$postNumber}",
				'redirectUrl' => $redirectUrl,
			];

			// Include rendered new reply HTML for instant client-side insertion
			if (!empty($newPostsHtml)) {
				$registJsonData['newPostsHtml'] = $newPostsHtml;
			}

			// handle detach logic
			$this->handleJsonDetach(
				$postData, 
				$computedPostInfo, 
				$preInsertThreadList, 
				$registJsonData
			);
		} 
		// For non-JavaScript requests, handle page rebuilding and then redirect
		else {
			$this->handlePageRebuilding($computedPostInfo, $postData, $preInsertThreadList);
			redirect($redirectUrl, 0);
		}
	}

	// Handle page rebuilding logic
	private function handlePageRebuilding(array $computedPostInfo, array $postData, array $threadList): void {
		// A new thread submission requires all pages to be rebuilt
		if($computedPostInfo['is_op']) {
			// rebuild all static pages
			$this->board->rebuildBoard();
		} else {
			$pageToRebuild = getPageOfThread($postData['thread_uid'], $threadList, $this->config['PAGE_DEF']);

			// static page limit
			$staticHtmlUntil = $this->board->getConfigValue('STATIC_HTML_UNTIL', 10);

			// dont even bother if the page is larger than the static page limit
			if(($pageToRebuild > $staticHtmlUntil) && $staticHtmlUntil > 0) {
				return;
			}

			// If saging, just rebuild that one page
			if($postData['age'] === false) {
				$this->board->rebuildBoardPage($pageToRebuild);
			} else {
				// If a non-sage reply, rebuild all pages until the page the thread is on
				$this->board->rebuildBoardPages($pageToRebuild);
			}
		}
	}

	// If the extention is set (i.e the file is set), then save the files
	private function saveUploadedPostFiles(?postFileUploadController $postFileUploadController, ?string $fileExtention): void {
		if($fileExtention) {
			$postFileUploadController->savePostThumbnailToBoard();
			$postFileUploadController->savePostFileToBoard();
		}
	}

	// Render all new replies in a thread after a given post number
	private function renderNewRepliesHtml(string $threadUid, int $threadResno, int $lastPostNo, ThreadData|false $thread): array {
		// Fetch all replies after the client's last known post
		$newPosts = $this->threadRepository->getRepliesAfterPostNumber($threadUid, $lastPostNo);

		if (!$newPosts) {
			return [];
		}

		// Switch to the reply template for rendering
		// clone it so it doesn't affect page rebuild
		$templateEngine = clone $this->board->getBoardTemplateEngine();
		$replyTemplateName = $this->board->getConfigValue('REPLY_TEMPLATE_FILE');
		$templateEngine->setTemplateFile($replyTemplateName);

		$newPostUids = array_map(fn($p) => $p->getUid(), $newPosts);
		$quoteLinksFromBoard = $this->quoteLinkService->getQuoteLinksByPostUids($newPostUids);

		$renderer = new postRenderer(
			$this->board,
			$this->config,
			$this->moduleEngine,
			$templateEngine,
			$quoteLinksFromBoard,
			$this->request
		);

		$threadPosts = $thread ? $thread->getPosts() : $newPosts;
		$replyCount = $thread ? $thread->getThread()->getPostCount() - 1 : 0;

		$htmlArray = [];
		foreach ($newPosts as $post) {
			$templateValues = [];
			$htmlArray[] = $renderer->render(
				$post,
				$templateValues,
				$threadResno,
				false,
				$threadPosts,
				isActiveStaffSession(),
				'',
				'',
				$replyCount,
				true,
				''
			);
		}

		return $htmlArray;
	}

}
