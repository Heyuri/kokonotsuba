<?php
require_once './post.php';
require_once './hook.php';
require_once './auth.php';
require_once './fileHandler.php';
require_once './repos/postRepo.php';

class threadClass{
	private $conf = require './conf.php'; // board configs.
	private $posts = [];
    private $threadID;
    private $lastBumpTime;
    private $OPPostID;
    private $postsFullyLoaded=false;
    private $repo = postRepoClass::getInstance();
    private $auth = AuthClass::getInstance();
    private $hookObj = HookClass::getInstance();

	public function __construct($conf, $threadID, $lastBumpTime, $OPPostID){
		$this->conf = $conf;
        $this->threadID = $threadID;
        $this->lastBumpTime = $lastBumpTime;
        $this->OPPostID = $OPPostID;
	}

    public function getLastBumpTime(){
        return $this->lastBumpTime;
    }
    public function getThreadID(){
        return $this->threadID;
    }
    public function getOPPostID(){  
        return $this->OPPostID;
    }
	/* build postObj from postrequest -> validate postObj -> save postObj to database */ // -> redraw pages -> redirect user */
	public function postToThread(){
		$conf = $this->conf;

		//gen post password if none is provided
		if($_POST['password'] == ''){
			$hasinput = $_SERVER['REMOTE_ADDR'] . time() . $conf['passwordSalt'];
			$hash = hash('sha256', $hasinput);
			$_POST['password'] = substr($hash, -8); 
		}

		setrawcookie('passwordc', $_POST['password'], $conf->cookieExpireTime);
		setrawcookie('namec', $_POST['name'], $conf->cookieExpireTime);

		$fileHandler = new fileHandlerClass($conf->fileConf);
		$postData = new PostDataClass(	$conf, $_POST['name'], $_POST['email'], $_POST['subject'], 
										$_POST['comment'], $_POST['password'], time(), $_SERVER['REMOTE_ADDR'],$this->threadID );


		// get the uploaded files and put them inside the post object.
		$uploadFiles = $fileHandler->getFilesFromPostRequest();
		foreach ($uploadFiles as $file) {
			$postData->addFile($file);
		}
		
        // do file procssesing like make thumbnails. make hash. etc.
		$postData->procssesFiles(); 

		// if we are not admin or mod, remove any html tags.
		if( !$this->auth->isAdmin() || !$this->auth->isMod()){ 	
			$postData->stripHtml();
		}

		//if the board lets you tripcode, apply tripcode to name.
		if($conf['canTripcode']){
			$postData->applyTripcode();	
		}

		$this->hookObj->executeHook("onUserPostToBoard", $postData, $fileHandler);// HOOK base post fully loaded

		/* prep post for db and drawing */

		// if the board allows embeding of links
		if($conf['autoEmbedLinks']){
			$postData->embedLinks();
		}
		// if board allows post to link to other post.
		if($conf['allowQuoteLinking']){
			$postData->quoteLinks();
		}

        // stuff like bb code, emotes, capcode, ID, should all be handled in moduels.
		$this->hookObj->executeHook("onPostPrepForDrawing", $postData);// HOOK post with html fully loaded

		// save post to data base
		$postData->setThreadID($this->threadID);
        $this->repo->createPost($this->conf, $postData);

        return;
	}

    public function getPosts(){
        if($this->postsFullyLoaded == false){
            $this->posts = $this->repo->loadPosts($this->conf, $this->threadID);
            $this->postFullyLoaded = true;
        }
        return $this->posts;
    }

    public function getPostByID($postID){
        if(!isset($this->posts[$postID])){
            $this->posts[$postID] = $this->repo->loadPostsByID($this->conf ,$this->threadID, $postID);
        }
        return $this->posts[$postID];
    }
}
