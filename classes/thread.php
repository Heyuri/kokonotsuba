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
	private $postRepo;
	public function __construct($conf, $lastBumpTime, $threadID = -1, $OPPostID = -1){
		$this->conf = $conf;
        $this->threadID = $threadID;
        $this->lastBumpTime = $lastBumpTime;
        $this->OPPostID = $OPPostID;
		$this->postRepo = PostRepoClass::getInstance();
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
    public function getPosts(){
        if($this->postsFullyLoaded == false){
            $this->posts = $this->postRepo->loadPostsFromThreadID($this->conf, $this->threadID);
            $this->postFullyLoaded = true;
        }
        return $this->posts;
    }
    public function getPostByID($postID){
        if(!isset($this->posts[$postID])){
            $this->posts[$postID] = $this->postRepo->loadPostByThreadID($this->conf, $this->threadID ,$postID);
        }
        return $this->posts[$postID];
    }

}
/*new thread 
$repoP = postRepoClass::getInstance();
$repoT = ThreadRepoClass::getInstance();

$t = new threadClass([],4);
$p = new PostDataClass([],0,0,0,0,0,0,0,$t->getThreadID());
$repoP->createPost([], $p);
$repoT->createThread([], $t,$p->getPostID());
$repoP->updatePost([], $p);
*/
