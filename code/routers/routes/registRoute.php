<?php

// regist route - inserts a post/thread from user input

class registRoute {
    private readonly board $board;
    private readonly array $config;
    private readonly globalHTML $globalHTML;
    private readonly postValidator $postValidator;
    private readonly staffAccountFromSession $staffSession;

    private moduleEngine $moduleEngine;

    private readonly actionLogger $actionLogger;
    private readonly mixed $FileIO;
    private readonly mixed $PIO;
	private readonly mixed $threadSingleton;


    public function __construct(board $board, 
        array $config,
        globalHTML $globalHTML, 
        postValidator $postValidator,
        staffAccountFromSession $staffSession,
        moduleEngine $moduleEngine,
        actionLogger $actionLogger,
        mixed $FileIO,
        mixed $PIO,
		mixed $threadSingleton) {
        $this->board = $board;
        $this->config = $config;

        $this->globalHTML = $globalHTML;
        $this->postValidator = $postValidator;
        $this->staffSession = $staffSession;

        $this->moduleEngine = $moduleEngine;
        
        $this->actionLogger = $actionLogger;
        $this->FileIO = $FileIO;
        $this->PIO = $PIO;
		$this->threadSingleton = $threadSingleton;
	}

    /* Write to post table */
	public function registerPostToDatabase() {
		$this->board->updateBoardPathCache(); // Upload board cached path
	
		// Initialize file directories
		$thumbDir = $this->board->getBoardUploadedFilesDirectory() . $this->config['THUMB_DIR'];
		$imgDir = $this->board->getBoardUploadedFilesDirectory() . $this->config['IMG_DIR'];
	
		// Initialize core handlers
		$thumbnailCreator = new thumbnailCreator($this->config['USE_THUMB'], $this->config['THUMB_SETTING'], $thumbDir);
		$tripcodeProcessor = new tripcodeProcessor($this->config, $this->globalHTML);
		$defaultTextFiller = new defaultTextFiller($this->config);
		$fortuneGenerator = new fortuneGenerator($this->config['FORTUNES']);
		$postFilterApplier = new postFilterApplier($this->config, $this->globalHTML, $fortuneGenerator);
		$postDateFormatter = new postDateFormatter($this->config);
		$postIdGenerator = new postIdGenerator($this->config, $this->PIO, $this->staffSession);
		$agingHandler = new agingHandler($this->config, $this->threadSingleton);
		$webhookDispatcher = new webhookDispatcher($this->board, $this->config);
	
		// Step 1: Validate regist request
		$this->postValidator->registValidate();

		// Step 2: Gather POST input data
		$postData = $this->gatherPostInputData();
	
		// Step 3: Process uploaded file (if any)
		$fileMeta = $this->handleFileUpload($postData['isReply'], $thumbnailCreator, $imgDir);

		// Step 4: Validate & clean post content
		$this->validateAndCleanPostContent($postData, $fileMeta['status'], $fileMeta['file'], $postData['is_admin']);
	
		// Step 5: Handle tripcode, default text, filters, and categories
		$this->processPostDetails($postData, $tripcodeProcessor, $defaultTextFiller, $postFilterApplier);
	
		// Step 6: Final data prep (timestamps, password hashing, unique ID)
		$computedPostInfo = $this->preparePostMetadata($postData, $postDateFormatter, $postIdGenerator, $fileMeta['file']);
	
		// Step 7: Validate post for database storage
		$this->postValidator->validateForDatabase(
			$postData['pwdc'], $postData['comment'], $postData['time'], $computedPostInfo['pass'],
			$postData['ip'], $fileMeta['upfile'], $fileMeta['md5'], $computedPostInfo['dest'],
			$this->PIO, $postData['roleLevel']
		);
	
		// Thread-related checks
		if($postData['thread_uid']){
			$postData['ThreadExistsBefore'] = $this->threadSingleton->isThread($postData['thread_uid']);
		}
	
		$this->postValidator->pruneOld($this->moduleEngine, $this->PIO, $this->FileIO);
		$this->postValidator->threadSanityCheck($postData['chktime'], $postData['flgh'], $postData['thread_uid'], $postData['ThreadExistsBefore']);

		// Age/sage logic
		$agingHandler->apply($postData['thread_uid'], $postData['time'], $postData['chktime'], $postData['email'], $postData['age']);
	
		// Generate redirect URL and sanitize
		$redirect = $this->generateRedirectURL($computedPostInfo['no'], $postData['email'], $postData['resno'], $computedPostInfo['timeInMilliseconds']);

		// Commit pre-write hook
		$this->moduleEngine->useModuleMethods('RegistBeforeCommit', [
			&$postData['name'], &$postData['email'], &$postData['sub'], &$postData['comment'],
			&$postData['category'], &$postData['age'], $fileMeta['file'], $postData['thread_uid'],
			[$fileMeta['thumbWidth'], $fileMeta['thumbHeight'], $fileMeta['imgW'], $fileMeta['imgH'], $fileMeta['fileTimeInMilliseconds'], $fileMeta['ext']],
			&$postData['status']
		]);

		// Add post to database
		$this->PIO->addPost(
			$this->board, $computedPostInfo['no'], $postData['thread_uid'], $computedPostInfo['post_position'], $computedPostInfo['is_op'],  $fileMeta['md5'],
			$postData['category'], $fileMeta['fileTimeInMilliseconds'], $fileMeta['fileName'], $fileMeta['ext'],
			$fileMeta['imgW'], $fileMeta['imgH'], $fileMeta['readableSize'], $fileMeta['thumbWidth'], $fileMeta['thumbHeight'],
			$computedPostInfo['pass'], $computedPostInfo['now'],
			$postData['name'], $postData['tripcode'], $postData['secure_tripcode'], $postData['capcode'], $postData['email'], $postData['sub'], $postData['comment'],
			$postData['ip'], $postData['age'], $postData['status']
		);
	
		// Log and hooks
		$this->actionLogger->logAction("Post No.{$computedPostInfo['no']} registered", $this->board->getBoardUID());
		$this->moduleEngine->useModuleMethods('RegistAfterCommit', [$this->board->getLastPostNoFromBoard(), $postData['thread_uid'], $postData['name'], $postData['email'], $postData['sub'], $postData['comment']]);
	
		// Set cookies for password and email
		setcookie('pwdc', $postData['pwd'], time()+7*24*3600);
		setcookie('emailc', htmlspecialchars_decode($postData['email']), time()+7*24*3600);
	
		// Dispatch webhook
		$webhookDispatcher->dispatch($postData['resno'], $computedPostInfo['no']);
	
		// Save files
		if($fileMeta['file']->getExtention()) {
			$fileMeta['uploadController']->savePostThumbnailToBoard();
			$fileMeta['uploadController']->savePostFileToBoard();
		}
	
		// Handle quote links
		$this->handlePostQuoteLink($computedPostInfo['no'], $postData['comment']);

		// Rebuild board
		$threads = $this->threadSingleton->getThreadListFromBoard($this->board);

		// Rebuild pages from 0 to the one the thread is on
		if($computedPostInfo['is_op']) {
			$this->board->rebuildBoard();
		} else {
			$pageToRebuild = getPageOfThread($postData['thread_uid'], $threads, $this->config['PAGE_DEF']);
			
			if($postData['age'] === false) {
				$this->board->rebuildBoardPage($pageToRebuild);
			} else {
				$this->board->rebuildBoardPages($pageToRebuild);
			}
		}
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
		$host = gethostbyaddr($ip);
		
		$age = false;
	
		$thread_uid = $this->threadSingleton->resolveThreadUidFromResno($this->board, $resno);
		$isReply = $thread_uid ? true : false;
	
		$roleLevel = $this->staffSession->getRoleLevel();
		$time = $_SERVER['REQUEST_TIME'];
		$timeInMilliseconds = intval($_SERVER['REQUEST_TIME_FLOAT'] * 1000);
	
		$chktime = 0;
		$flgh = '';
		$ThreadExistsBefore = false;
		$up_incomplete = 0;
		$is_admin = $roleLevel === \Kokonotsuba\Root\Constants\userRole::LEV_ADMIN;

		// full name for cookie, it wont be processed by the tripcode processor
		$nameCookie = $name;

		$tripcode = '';
		$secure_tripcode = '';
		[$name, $tripcode, $secure_tripcode] = array_map('trim', explode('#', $name . '##'));


		return [ 'nameCookie' => $nameCookie, 'name' => $name, 'tripcode' => $tripcode, 'secure_tripcode' => $secure_tripcode, 
			 'capcode' => '', 'email' => $email, 'sub' => $sub, 'comment' => $comment, 'pwd' => $pwd,
			 'category' => $category, 'resno' => $resno, 'pwdc' => $pwdc, 'ip' => $ip, 'host' => $host,
			 'thread_uid' => $thread_uid, 'isReply' => $isReply, 'roleLevel' => $roleLevel, 'time' => $time,
			 'timeInMilliseconds' => $timeInMilliseconds, 'chktime' => $chktime, 'flgh' => $flgh, 'age' => $age, 'status' => '',
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
	
			$postFileUploadController = new postFileUploadController($this->config, $fileFromUpload, $thumbnailCreator, $thumbnail, $this->globalHTML, $boardFileDirectory);
			$postFileUploadController->validateFile();
			
			[$upfile, $upfile_path, $upfile_status] = loadUploadData();

		} else {
			// show an error if the user tries to make a thread without an image in image-board mode
			if($upfile_status === UPLOAD_ERR_NO_FILE && !$isReply && !$this->config['TEXTBOARD_ONLY']) {
				$this->globalHTML->error(_T('regist_upload_noimg'));
			}
		}
	
		return [
			'file' => $file,
			'thumbnail' => $thumbnail,
			'uploadController' => $postFileUploadController,
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

	private function validateAndCleanPostContent(array &$postData, string $upfileStatus, file $file, bool $isAdmin): void {
		$this->postValidator->spamValidate($postData['name'], $postData['email'], $postData['sub'], $postData['comment']);
	
		$this->moduleEngine->useModuleMethods('RegistBegin', [
			&$postData['name'], &$postData['email'], &$postData['sub'], &$postData['comment'],
			['file' => '', 'path' => '', 'name' => '', 'status' => $upfileStatus],
			['ip' => $postData['ip'], 'host' => $postData['host']], $postData['thread_uid']
		]);
	
		if (strlenUnicode($postData['name']) > $this->config['INPUT_MAX']) $this->globalHTML->error(_T('regist_nametoolong'));
		if (strlenUnicode($postData['email']) > $this->config['INPUT_MAX']) $this->globalHTML->error(_T('regist_emailtoolong'));
		if (strlenUnicode($postData['sub']) > $this->config['INPUT_MAX']) $this->globalHTML->error(_T('regist_topictoolong'));
	
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
	private function generateRedirectURL(int $no, string &$email, int $threadResno, int $timeInMilliseconds): string {
		// get the board static index
		$redirect = $this->config['PHP_SELF2'] . '?' . $timeInMilliseconds;
		
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
			$redirect = $this->config['PHP_SELF2'];
		}

		// remove "noko" from the post email since most posts will contain it
		$email = preg_replace('/^(no)+ko\d*$/i', '', $email);

		// remove "dump" from the email field
		$email = preg_replace('/dump/i', '', $email);

		// return processed redirect
		return $redirect;
	}


	// prepare post meta data
	private function preparePostMetadata(array &$postData, postDateFormatter $formatter, postIdGenerator $idGen, file $file): array {
		if ($postData['pwd'] == '') {
			$postData['pwd'] = ($postData['pwdc'] == '') ? substr(rand(), 0, 8) : $postData['pwdc'];
		}
		$pass = substr(md5($postData['pwd']), 2, 8);
	
		$no = $this->board->getLastPostNoFromBoard() + 1;
		$post_position = 0;
		$is_op = $postData['resno'] ? false : true;
		$now = $formatter->format($postData['time']);
		$now .= $idGen->generate($postData['email'], $postData['time'], $postData['thread_uid']);
	
		return [
			'no' => $no,
			'post_position' => $post_position,
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
			$postUid = $this->PIO->resolvePostUidFromPostNumber($this->board, $postNumber);
	
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
			$quoteLinkedPostUids = $this->PIO->resolvePostUidsFromArray($this->board, $quoteLinkedPostNumbers);
	
			// Store quote link relationships in the database
			createQuoteLinksFromArray($this->board, $postUid, $quoteLinkedPostUids);
		}
	}
	

}