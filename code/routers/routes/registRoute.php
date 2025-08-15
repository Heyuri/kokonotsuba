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
        private mixed $FileIO,
		private readonly postRepository $postRepository,
        private readonly postService $postService,
		private readonly threadRepository $threadRepository,
		private readonly threadService $threadService,
		private readonly quoteLinkService $quoteLinkService,
		private readonly softErrorHandler $softErrorHandler) {}

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
		$tripcodeProcessor = new tripcodeProcessor($this->config, $this->softErrorHandler);
		$defaultTextFiller = new defaultTextFiller($this->config);
		$fortuneGenerator = new fortuneGenerator($this->config['FORTUNES']);
		$postFilterApplier = new postFilterApplier($this->config, $fortuneGenerator);
		$postDateFormatter = new postDateFormatter($this->config);
		$postIdGenerator = new postIdGenerator($this->config, $this->board, $this->staffSession);
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
			$tripcodeProcessor,
			$defaultTextFiller,
			$postFilterApplier,
			$postDateFormatter,
			$postIdGenerator,
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
			$this->postValidator->threadSanityCheck($postData['postOpRoot'], $postData['flgh'], $postData['thread_uid'], $postData['resno'], $postData['ThreadExistsBefore']);

			// Step 4: Process uploaded file (if any)
			$fileMeta = $this->handleFileUpload($postData['isReply'], $thumbnailCreator, $imgDir);

			// Step 5: Validate & clean post content
			$this->validateAndCleanPostContent($postData, $fileMeta['status'], $fileMeta['file'], $postData['is_admin'], $thread);

			// Step 6: Handle tripcode, default text, filters, and categories
			$this->processPostDetails($postData, $tripcodeProcessor, $defaultTextFiller, $postFilterApplier);

			// Step 7: Final data prep (timestamps, password hashing, unique ID)
			$computedPostInfo = $this->preparePostMetadata($postData, $postDateFormatter, $postIdGenerator, $fileMeta['file']);

			// Step 8: Validate post for database storage
			$this->postValidator->validateForDatabase(
				$postData['pwdc'], $postData['comment'], $postData['time'], $computedPostInfo['pass'],
				$postData['ip'], $fileMeta['upfile'], $fileMeta['md5'], $computedPostInfo['dest'], $postData['roleLevel']
			);

			// Age/sage logic
			$agingHandler->apply($postData['thread_uid'], $postData['time'], $postData['postOpRoot'], $postData['email'], $postData['age']);

			// Generate redirect URL and sanitize
			$redirect = $this->generateRedirectURL($computedPostInfo['no'], $postData['email'], $postData['resno'], $computedPostInfo['timeInMilliseconds']);

			// Remove noko/nonoko and dump
			$emailForInsertion = $this->preparePostEmail($postData['email']);

			// Commit pre-write hook
			$this->moduleEngine->dispatch('RegistBeforeCommit', [
				&$postData['name'], &$postData['email'], &$postData['sub'], &$postData['comment'],
				&$postData['category'], &$postData['age'], $fileMeta['file'],
				$postData['isReply'], &$postData['status'], $thread
			]);

			$postRegistData = new postRegistData(
				$computedPostInfo['no'],
				$postData['thread_uid'],
				$computedPostInfo['is_op'],
				$fileMeta['md5'],
				$postData['category'],
				$fileMeta['fileTimeInMilliseconds'],
				$fileMeta['fileName'],
				$fileMeta['ext'],
				$fileMeta['imgW'],
				$fileMeta['imgH'],
				$fileMeta['readableSize'],
				$fileMeta['thumbWidth'],
				$fileMeta['thumbHeight'],
				$computedPostInfo['pass'],
				$computedPostInfo['now'],
				$postData['name'],
				$postData['tripcode'],
				$postData['secure_tripcode'],
				$postData['capcode'],
				$emailForInsertion,
				$postData['sub'],
				$postData['comment'],
				$postData['ip'],
				$postData['age'],
				$postData['status']
			);
 
			// Add post to database
			$this->postService->addPostToThread($this->board, $postRegistData);
			
			// Log and hooks
			$this->actionLoggerService->logAction("Post No.{$computedPostInfo['no']} registered", $this->board->getBoardUID());
			$this->moduleEngine->dispatch('RegistAfterCommit', [$this->board->getLastPostNoFromBoard(), $postData['thread_uid'], $postData['name'], $postData['email'], $postData['sub'], $postData['comment']]);

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
		$this->saveUploadedPostFiles($fileMeta['postFileUploadController'], $fileMeta['file']->getExtention());

		// Dispatch webhook
		$webhookDispatcher->dispatch($postData['resno'], $computedPostInfo['no']);

		// Rebuild board pages
		$this->handlePageRebuilding($computedPostInfo, $postData, $preInsertThreadList);

		// Final redirect
		redirect($redirect, 0);
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


		return [ 'nameCookie' => $nameCookie, 'name' => $name, 'tripcode' => $tripcode, 'secure_tripcode' => $secure_tripcode, 
			 'capcode' => '', 'email' => $email, 'sub' => $sub, 'comment' => $comment, 'pwd' => $pwd,
			 'category' => $category, 'resno' => $resno, 'pwdc' => $pwdc, 'ip' => $ip,
			 'thread_uid' => $thread_uid, 'isReply' => $isReply, 'roleLevel' => $roleLevel, 'time' => $time,
			 'timeInMilliseconds' => $timeInMilliseconds, 'postOpRoot' => $postOpRoot, 'flgh' => $flgh, 'age' => $age, 'status' => '',
			 'ThreadExistsBefore' => $ThreadExistsBefore, 'up_incomplete' => $up_incomplete, 'is_admin' => $is_admin
		];
	}

	private function handleFileUpload(bool $isReply, thumbnailCreator $thumbnailCreator, string $boardFileDirectory): array {
		$file = new file();
		$thumbnail = new thumbnail();

		$upfile = $upfile_path = $upfile_name = '';
		$upfile_status = UPLOAD_ERR_NO_FILE;

		$postFileUploadController = null;
	
		if (isset($_FILES['upfile']) && is_uploaded_file($_FILES['upfile']['tmp_name'])) {
			$fileFromUpload = getUserFileFromRequest();
	
			$file = $fileFromUpload->getFile();
			$thumbnail = getThumbnailFromFile($file, $this->config['THUMB_SETTING']['Method']);
			$thumbnail = scaleThumbnail($thumbnail, $isReply, $this->config['MAX_RW'], $this->config['MAX_RH'], $this->config['MAX_W'], $this->config['MAX_H']);
	
			$postFileUploadController = new postFileUploadController($this->config, $fileFromUpload, $thumbnailCreator, $thumbnail, $boardFileDirectory, $this->softErrorHandler);
			$postFileUploadController->validateFile();
			
			[$upfile, $upfile_path, $upfile_status] = loadUploadData();

		} else {
			// show an error if the user tries to make a thread without an image in image-board mode
			if($upfile_status === UPLOAD_ERR_NO_FILE && !$isReply && !$this->config['TEXTBOARD_ONLY']) {
				$this->softErrorHandler->errorAndExit(_T('regist_upload_noimg'));
			}
		}
	
		return [
			'file' => $file,
			'thumbnail' => $thumbnail,
			'postFileUploadController' => $postFileUploadController,
			'upfile' => $upfile,
			'path' => $upfile_path,
			'name' => $upfile_name,
			'status' => $upfile_status,
			'imgW' => $file->getImageWidth(),
			'imgH' => $file->getImageHeight(),
			'fileName' => $file->getFileName(),
			'ext' => $file->getExtention(),
			'fileTimeInMilliseconds' => $file->getTimeInMilliseconds(),
			'md5' => $file->getMd5Chksum(),
			'thumbWidth' => $thumbnail->getThumbnailWidth(),
			'thumbHeight' => $thumbnail->getThumbnailHeight(),
			'readableSize' => formatFileSize($file->getFileSize())
		];
	}

	private function validateAndCleanPostContent(array &$postData, string $upfileStatus, file $file, bool $isAdmin, bool|array $thread): void {
		$this->postValidator->spamValidate($postData['name'], $postData['email'], $postData['sub'], $postData['comment']);
	
		$registInfo = [
			'name' => &$postData['name'], 
			'email' => &$postData['email'], 
			'sub' => &$postData['sub'],
			'com' => &$postData['comment'],
			'age' => &$postData['age'],
			'file' => $file,
			'ip' => $postData['ip'], 
			'isThreadSubmit' => empty($postData['thread_uid']),
			'roleLevel' => $postData['roleLevel'],
			'thread' => $thread
		];

		$this->moduleEngine->dispatch('RegistBegin', [&$registInfo]);
	
		if (strlenUnicode($postData['name']) > $this->config['INPUT_MAX']) $this->softErrorHandler->errorAndExit(_T('regist_nametoolong'));
		if (strlenUnicode($postData['email']) > $this->config['INPUT_MAX']) $this->softErrorHandler->errorAndExit(_T('regist_emailtoolong'));
		if (strlenUnicode($postData['sub']) > $this->config['INPUT_MAX']) $this->softErrorHandler->errorAndExit(_T('regist_topictoolong'));
	
		setrawcookie('namec', rawurlencode(htmlspecialchars_decode($postData['nameCookie'])), time() + 7 * 24 * 3600);
	
		$postData['email'] = str_replace("\r\n", '', $postData['email']);
		$postData['sub'] = str_replace("\r\n", '', $postData['sub']);
	
		$postData['comment'] = $this->postValidator->cleanComment($postData['comment'], $upfileStatus, $isAdmin, $file->getTemporaryFileName());
	}

	private function processPostDetails(array &$postData, tripcodeProcessor $tripcodeProcessor, defaultTextFiller $defaultTextFiller, postFilterApplier $postFilterApplier): void {
		$tripcodeProcessor->apply($postData['name'], $postData['tripcode'], $postData['secure_tripcode'], $postData['capcode'], $postData['roleLevel']);
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
		// get the board static index
		$redirect = $this->config['STATIC_INDEX_FILE'] . '?' . $timeInMilliseconds;
		
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
			$redirect = $this->config['STATIC_INDEX_FILE'];
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

	// prepare post meta data
	private function preparePostMetadata(array &$postData, postDateFormatter $postDateFormatter, postIdGenerator $postIdGenerator, file $file): array {
		if ($postData['pwd'] == '') {
			$postData['pwd'] = ($postData['pwdc'] == '') ? substr(rand(), 0, 8) : $postData['pwdc'];
		}
		$pass = substr(md5($postData['pwd']), 2, 8);
	
		$no = $this->board->getLastPostNoFromBoard() + 1;
		$is_op = $postData['resno'] ? false : true;
		$now = $postDateFormatter->formatFromTimestamp($postData['time']);
		$now .= $postIdGenerator->generate($postData['email'], $postData['time'], $postData['thread_uid']);
	
		return [
			'no' => $no,
			'is_op' => $is_op,
			'pass' => $pass,
			'now' => $now,
			'dest' => $file->getTemporaryFileName(),
			'timeInMilliseconds' => $postData['timeInMilliseconds']
		];
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

	// Handle page rebuilding logic
	private function handlePageRebuilding(array $computedPostInfo, array $postData, array $threadList): void {
		// Rebuild pages from 0 to the one the thread is on
		if($computedPostInfo['is_op']) {
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
