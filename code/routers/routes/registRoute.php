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
		$this->board->updateBoardPathCache(); //upload board cached path

		// uploaded file handlers
		$thumbnailCreator = new thumbnailCreator($this->board, $this->config, $this->FileIO);
		$fileProcessor = new fileProcessor($this->board, $this->config, $this->postValidator, $this->globalHTML, $thumbnailCreator, $this->FileIO);
		
		// post data manipulation
		$tripcodeProcessor = new tripcodeProcessor($this->config, $this->globalHTML);
		$defaultTextFiller = new defaultTextFiller($this->config);
		$fortuneGenerator = new fortuneGenerator($this->config['FORTUNES']);
		
		// filter, date and IDs
		$postFilterApplier = new postFilterApplier($this->config, $this->globalHTML, $fortuneGenerator);
		$postDateFormatter = new postDateFormatter($this->config);
		$postIdGenerator = new postIdGenerator($this->config, $this->PIO, $this->staffSession);

		// age/sage
		$agingHandler = new agingHandler($this->config, $this->threadSingleton);

		// webhook for post notifcations
		$webhookDispatcher = new webhookDispatcher($this->board, $this->config);

		$chktime = 0;
		$flgh = '';
		$ThreadExistsBefore = false;
		$up_incomplete = 0; 
		$is_admin = false;
		
		/* get post data */
		$name = htmlspecialchars($_POST['name']??'');
		$email = htmlspecialchars($_POST['email']??'');
		$sub = htmlspecialchars($_POST['sub']??'');
		$com = htmlspecialchars($_POST['com']??'');
		$pwd = $_POST['pwd']??'';
		$category = htmlspecialchars($_POST['category']??'');
		$resno = intval($_POST['resto']??0);
		$thread_uid = $this->threadSingleton->resolveThreadUidFromResno($this->board, $resno);
		$pwdc = $_COOKIE['pwdc']??'';

		$ip = new IPAddress; 
		$host = gethostbyaddr($ip);
		// Unix timestamp in seconds
		$time = $_SERVER['REQUEST_TIME'];
		// Unix timestamp in milliseconds
		$tim  = intval($_SERVER['REQUEST_TIME_FLOAT'] * 1000);
		
		$dest = $_FILES['upfile']['tmp_name'];
		// file attributes
		$upfile = '';
		$upfile_path = '';
		$upfile_name = '';
		$upfile_status = '';

		$file = new file();
		$thumbnail = new thumbnail();

		// get file attributes
		[$upfile, $upfile_path, $upfile_name, $upfile_status] = loadUploadData();
		
		$roleLevel = $this->staffSession->getRoleLevel();
		
		$this->postValidator->spamValidate($name, $email, $sub, $com);
		/* hook call */
		$this->moduleEngine->useModuleMethods('RegistBegin', array(&$name, &$email, &$sub, &$com, array('file'=>&$upfile, 'path'=>&$upfile_path, 'name'=>&$upfile_name, 'status'=>&$upfile_status), array('ip'=>$ip, 'host'=>$host), $thread_uid)); // "RegistBegin" Hook Point
		if($this->config['TEXTBOARD_ONLY'] == false) {
				[$file, $thumbnail] = $fileProcessor->process($thread_uid, $tim);
		}
		
		// Calculate the last fields needed before putitng in db
		$no = $this->board->getLastPostNoFromBoard() + 1;
		$ext = $file->getExtention();
		$imgW = $file->getImageWidth();
		$imgH = $file->getImageHeight();
		$imgSize = $file->getFileSize();
		$fileName = $file->getFileName();
		$md5chksum = $file->getMd5Chksum();
		$dest = $file->getDest();
		$thumbWidth = $thumbnail->getThumbnailWidth();
		$thumbHeight = $thumbnail->getThumbnailHeight();
		$age = false;
		$status = '';

		// Check the form field contents and trim them
		if(strlenUnicode($name) > $this->config['INPUT_MAX'])	$this->globalHTML->error(_T('regist_nametoolong'));
		if(strlenUnicode($email) > $this->config['INPUT_MAX'])	$this->globalHTML->error(_T('regist_emailtoolong'));
		if(strlenUnicode($sub) > $this->config['INPUT_MAX'])	$this->globalHTML->error(_T('regist_topictoolong'));

		setrawcookie('namec', rawurlencode(htmlspecialchars_decode($name)), time()+7*24*3600);
		
		// E-mail / Title trimming
		$email = str_replace("\r\n", '', $email); 
		$sub = str_replace("\r\n", '', $sub);
		
		$this->postValidator->cleanComment($com, $upfile_status, $is_admin, $dest);
		$tripcodeProcessor->apply($name, $roleLevel);
		$defaultTextFiller->fill($sub, $com);
		$postFilterApplier->applyFilters($com, $email);
	
		// Trimming label style
		if($category && $this->config['USE_CATEGORY']){
				$category = explode(',', $category); // Disassemble the labels into an array
				$category = ','.implode(',', array_map('trim', $category)).','; // Remove the white space and merge into a single string (left and right, you can directly search in the form XX)
		}else{ 
				$category = ''; 
		}
		
		if($up_incomplete){
				$com .= '<p class="incompleteFile"><span class="warning">'._T('notice_incompletefile').'</span></p>'; // Tips for uploading incomplete additional image files
		}
	
		// Password and time style
		if($pwd==''){
				$pwd = ($pwdc=='') ? substr(rand(),0,8) : $pwdc;
		}

		$pass = $pwd ? substr(md5($pwd), 2, 8) : '*'; // Generate a password for true storage judgment (the 8 characters at the bottom right of the imageboard where it says Password ******** SUBMIT for deleting posts)
		$now = $postDateFormatter->format($time);
		$now .= $postIdGenerator->generate($email, $now, $time, $thread_uid);

		$this->postValidator->validateForDatabase($pwdc, $com, $time, $pass, $ip,  $upfile, $md5chksum, $dest, $this->PIO, $roleLevel);
		if($thread_uid){
				$ThreadExistsBefore = $this->threadSingleton->isThread($thread_uid);
		}
	
		$this->postValidator->pruneOld($this->moduleEngine, $this->PIO, $this->FileIO);
		$this->postValidator->threadSanityCheck($chktime, $flgh, $thread_uid, $this->PIO, $dest, $ThreadExistsBefore);
	

		// apply age/sage
		$agingHandler->apply($thread_uid, $time, $chktime, $email, $name, $age);
	
		// noko
		$redirect = $this->config['PHP_SELF2'].'?'.$tim;
		if (strstr($email, 'noko') && !strstr($email, 'nonoko')) {
				$redirect = $this->config['PHP_SELF'].'?res='.($resno?$resno:$no);
				if (!strstr($email, 'dump')){
						$redirect.= "#p".$this->board->getBoardUID()."_$no";
				}
		}
		$email = preg_replace('/^(no)+ko\d*$/i', '', $email);
	
		// Get number of pages to rebuild
		$threads = $this->threadSingleton->getThreadListFromBoard($this->board);
		$threads_count = count($threads);
		$page_end = ($thread_uid ? floor(array_search($thread_uid, $threads) / $this->config['PAGE_DEF']) : ceil($threads_count / $this->config['PAGE_DEF']));
		$this->moduleEngine->useModuleMethods('RegistBeforeCommit', array(&$name, &$email, &$sub, &$com, &$category, &$age, $file, $thread_uid, array($thumbWidth, $thumbHeight, $imgW, $imgH, $tim, $ext), &$status)); // "RegistBeforeCommit" Hook Point
		$this->PIO->addPost($this->board, $no, $thread_uid, $md5chksum, $category, $tim, $fileName, $ext, $imgW, $imgH, $imgSize, $thumbWidth, $thumbHeight, $pass, $now, $name, $email, $sub, $com, $ip, $age, $status);
		
		$this->actionLogger->logAction("Post No.$no registered", $this->board->getBoardUID());
		// Formal writing to storage
		$lastno = $this->board->getLastPostNoFromBoard() - 1; // Get this new article number
		$this->moduleEngine->useModuleMethods('RegistAfterCommit', array($lastno, $thread_uid, $name, $email, $sub, $com)); // "RegistAfterCommit" Hook Point
	
		// Cookies storage: password and e-mail part, for one week
		setcookie('pwdc', $pwd, time()+7*24*3600);
		setcookie('emailc', htmlspecialchars_decode($email), time()+7*24*3600);
		
		// dispatch notifcation to post notifcation discord/IRC server
		$webhookDispatcher->dispatch($resno, $no, $sub);

		$this->board->rebuildBoard(0, -1, false, $page_end);
		redirect($redirect, 0);
	}
}