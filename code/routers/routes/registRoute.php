<?php

// regist route - inserts a post/thread from user input

class registRoute {
    public function __construct(private board $board, 
        private readonly array $config,
        private readonly postValidator $postValidator,
        private readonly staffAccountFromSession $staffSession,
		private transactionManager $transactionManager,
        private moduleEngine $moduleEngine,
        private readonly actionLoggerService $actionLoggerService,
		private readonly postRepository $postRepository,
        private readonly postService $postService,
		private readonly fileService $fileService,
		private readonly threadRepository $threadRepository,
		private readonly threadService $threadService,
		private readonly quoteLinkService $quoteLinkService
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

		// Begin transaction
		$this->transactionManager->run(function () use (
			&$postData,
			&$fileMeta,
			&$computedPostInfo,
			&$emailForInsertion,
			&$redirect,
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

			// Get the thread data (if it exists)
			$thread = $this->threadService->getThreadByUID($postData['thread_uid']);

			// Step 3: Verify that thread exists
			$this->postValidator->threadSanityCheck($postData['postOpRoot'], $postData['flgh'], $postData['thread_uid'], $postData['resno'], $postData['ThreadExistsBefore'], $thread);

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
			$attachments = $afterInsertPost['attachments'] ?? [];
			
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
		setcookie('pwdc', $postData['pwd'], time()+7*24*3600);
		setcookie('emailc', htmlspecialchars_decode($postData['email']), time()+7*24*3600);

		// Save files
		foreach($fileMeta['files'] as $entry) {
			// get file object
			$file = $entry['file'];

			// save attachment + thumbnail 
			$this->saveUploadedPostFiles($entry['postFileUploadController'], $file->getExtention());
		}
		// Dispatch webhook
		$webhookDispatcher->dispatch($postData['resno'], $computedPostInfo['no']);

		// Handle javascript/json request
		// It will exit after a successful javascript request
		$this->handleJsonOutput(
			$computedPostInfo['no'], 
			$this->board->getBoardUID(),
			$redirect
		);

		// Rebuild board pages
		$this->handlePageRebuilding($computedPostInfo, $postData, $preInsertThreadList);
		
		// Final redirect (only for non-js requests)
		if(isJavascriptRequest() === false) {
			redirect($redirect, 0);
		}
	}


	private function gatherPostInputData(): array {
		$name = htmlspecialchars($_POST['name'] ?? '');
		$email = htmlspecialchars($_POST['email'] ?? '');
		$sub = htmlspecialchars($_POST['sub'] ?? '');
		$comment = htmlspecialchars($_POST['com'] ?? '');
		$pwd = $_POST['pwd'] ?? '';
		$category = htmlspecialchars($_POST['category'] ?? '');
		$resno = intval($_POST['resto'] ?? 0);
		$pwdc = $_COOKIE['pwdc'] ?? '';
	
		$ip = new IPAddress;
		
		$age = false;
	
		$thread_uid = $this->threadRepository->resolveThreadUidFromResno($this->board, $resno);
		$isReply = $thread_uid ? true : false;
	
		$roleLevel = $this->staffSession->getRoleLevel();
		$time = $_SERVER['REQUEST_TIME'];
		$timeInMilliseconds = intval($_SERVER['REQUEST_TIME_FLOAT'] * 1000);
	
		$postOpRoot = 0;
		$flgh = '';
		$ThreadExistsBefore = $this->threadRepository->isThread($thread_uid);
		$up_incomplete = 0;
		$is_admin = $roleLevel === \Kokonotsuba\Root\Constants\userRole::LEV_ADMIN;

		// full name for cookie, it wont be processed by the tripcode processor
		$nameCookie = $name;

		$tripcode = '';
		$secure_tripcode = '';
		[$name, $tripcode, $secure_tripcode] = array_map('trim', explode('#', $name . '##'));


		return [ 'nameCookie' => $nameCookie, 'name' => $name, 'tripcode_input' => $tripcode, 'secure_tripcode_input' => $secure_tripcode,
			 'tripcode' => '', 'secure_tripcode' => '', 'capcode' => '', 'email' => $email, 'sub' => $sub, 'comment' => $comment, 'pwd' => $pwd,
			 'category' => $category, 'resno' => $resno, 'pwdc' => $pwdc, 'ip' => $ip,
			 'thread_uid' => $thread_uid, 'isReply' => $isReply, 'roleLevel' => $roleLevel, 'time' => $time,
			 'timeInMilliseconds' => $timeInMilliseconds, 'postOpRoot' => $postOpRoot, 'flgh' => $flgh, 'age' => $age, 'status' => '',
			 'ThreadExistsBefore' => $ThreadExistsBefore, 'up_incomplete' => $up_incomplete, 'is_admin' => $is_admin
		];
	}

	private function handleFileUpload(bool $isReply, thumbnailCreator $thumbnailCreator, string $boardFileDirectory): array {
		// init file arrays
		$fileMetaList = [];
		$postFileUploadControllerList = [];

		// determine if multiple files are uploaded on the main input
		$hasMultiUpfile =
			isset($_FILES['upfile']['tmp_name']) &&
			is_array($_FILES['upfile']['tmp_name']) &&
			count($_FILES['upfile']['tmp_name']) > 0;

		// determine if multiple files are uploaded on quick reply
		$hasMultiQuickReply =
			isset($_FILES['quickReplyUpFile']['tmp_name']) &&
			is_array($_FILES['quickReplyUpFile']['tmp_name']) &&
			count($_FILES['quickReplyUpFile']['tmp_name']) > 0;

		// pick which input to use
		$inputName = $hasMultiUpfile ? 'upfile' : ($hasMultiQuickReply ? 'quickReplyUpFile' : null);

		// NO FILES → if OP post & imageboard mode, throw exception
		if ($inputName === null) {
			if (!$isReply && !$this->config['TEXTBOARD_ONLY']) {
				throw new BoardException(_T('regist_upload_noimg'));
			}

			// still return required structure
			return ['files' => []];
		}

		// ----------------------------------------
		// LOOP THROUGH ALL FILES IN THE INPUT
		// ----------------------------------------
		$fileCount = count($_FILES[$inputName]['tmp_name']);

		// get attachment limit
		$attachmentUploadLimit = $this->board->getConfigValue('ATTACHMENT_UPLOAD_LIMIT', 1);

		for ($i = 0; $i < $fileCount; $i++) {
			// break loop if iterator is above limit
			if($i >= $attachmentUploadLimit) {
				break;
			}

			// load indexed upload data
			[$tmp, $name, $status] = loadUploadData($inputName, $i);

			// skip empty slots
			if ($status === UPLOAD_ERR_NO_FILE || !$tmp) {
				continue;
			}

			// convert raw PHP file into fileFromUpload object
			$fileFromUpload = getUserFileFromRequest($tmp, $name, $status, $i);

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

		// return all files
		return ['files' => $fileMetaList];
	}

	private function validateAndCleanPostContent(array &$postData, array $files, bool $isAdmin, bool|array $thread): void {
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
			'tripcode' => &$postData['tripcode'],
			'secure_tripcode' => &$postData['secure_tripcode'],
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

		setrawcookie('namec', rawurlencode(htmlspecialchars_decode($postData['nameCookie'])), time() + 7 * 24 * 3600);
	
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

		// if noko is inside the email-field then redirect to the thread
		if(strstr($email, 'noko') && !strstr($email, 'nonoko')) {
			$redirectReplyNumber = $no;
			$redirect = $this->board->getBoardThreadURL($threadResno, $redirectReplyNumber);
		} elseif(strstr($email, 'dump')) {
			// if 'dump' is contained in the email-field then dont redirect to the reply by setting it to 0
			$redirectReplyNumber = 0;
			$redirect = $this->board->getBoardThreadURL($threadResno, $redirectReplyNumber);
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

		$no = $this->board->getLastPostNoFromBoard() + 1;
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

	// Processes quote links (e.g., >>123 or >>No.123) in a post's comment
	private function handlePostQuoteLink(int $postNumber, string $postComment) {
		// Match all quote patterns like ">>123" or ">>No.123" in the comment
		if(preg_match_all('/((?:&gt;|＞){2})(?:No\.)?(\d+)/i', $postComment, $matches, PREG_SET_ORDER)) {
			// Resolve the UID of the current post from its number
			$postUid = $this->postRepository->resolvePostUidFromPostNumber($this->board, $postNumber);
	
			$uniqueMatches = [];
	
			// Filter out duplicate matches
			foreach ($matches as $match) {
				if (!in_array($match, $uniqueMatches)) {
					$uniqueMatches[] = $match;
				}
			}
	
			$quoteLinkedPostNumbers = [];
	
			// Extract just the numeric post number from each quote match
			foreach ($uniqueMatches as $match) {
				$quoteLinkedPostNumbers[] = $match[2]; // This is the quoted post number
			}
	
			// Resolve the UIDs of all quoted post numbers
			$quoteLinkedPostUids = $this->postRepository->resolvePostUidsFromArray($this->board, $quoteLinkedPostNumbers);
	
			// Store quote link relationships in the database
			$this->quoteLinkService->createQuoteLinksFromArray($this->board->getBoardUID(), $postUid, $quoteLinkedPostUids);
		}
	}

	private function handleJsonOutput(int $postNumber, int $boardUid, string $redirectUrl): void {
		if(isJavascriptRequest()) {
			// build the array to be encoded as json
			$registJsonData = [
				// we want to build the post id so we can redirect to it when noko'ing
				'postId' => "p{$boardUid}_{$postNumber}",
				// in case of no noko or dump we'll want the js to redirect the user
				'redirectUrl' => $redirectUrl,
			];

			// then send ajax (and detach)
			sendAjaxAndDetach($registJsonData);
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

}
