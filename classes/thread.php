<?php
require_once './postData.php';
require_once './fileHandler.php';
require_once './hook.php';
require_once './auth.php';
require_once './repos/postRepo.php';

class threadClass{
	private $conf = require './conf.php'; // board configs.
	private $posts = [];
    private $threadID;
    private $postsFullyLoaded=false;
    private $repo = postRepoClass::getInstance();

	public function __construct($conf, $threadID){
		$this->conf = $conf;
        $this->threadID = $threadID;
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


		$auth = AuthClass::getInstance(); // singleton to check session and see if the user's role.
		$hookObj = HookClass::getInstance(); // singelton to manage hooks so moduels are easy to write.

		$fileHandler = new fileHandlerClass($conf->fileConf);
		$postData = new PostDataClass(	$conf, $_POST['name'], $_POST['email'], $_POST['subject'], 
										$_POST['comment'], $_POST['password'], $_SERVER['REMOTE_ADDR'], time());


		// get the uploaded files and put them inside the post object.
		$uploadFiles = $fileHandler->getFilesFromPostRequest();
		foreach ($uploadFiles as $file) {
			$postData->addFile($file);
		}
		
        // do file procssesing like make thumbnails. make hash. etc.
		$postData->procssesFiles(); 

		// if we are not admin or mod, remove any html tags.
		if( !$auth->isAdmin() || !$auth->isMod()){ 	
			$postData->stripHtml();
		}

		//if the board lets you tripcode, apply tripcode to name.
		if($conf['canTripcode']){
			$postData->applyTripcode();	
		}

		$hookObj->executeHook("onUserPostToBoard", $postData, $fileHandler);// HOOK base post fully loaded

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
		$hookObj->executeHook("onPostPrepForDrawing", $postData);// HOOK post with html fully loaded

		// save post to data base
        $this->repo->savePost($this->conf ,$this->threadID, $postData);

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
