<?php
require_once __DIR__ .'/post.php';
require_once __DIR__ .'/hook.php';
require_once __DIR__ .'/auth.php';
require_once __DIR__ .'/fileHandler.php';
require_once __DIR__ .'/repos/repoPost.php';

class threadClass{
	private $conf;
	private $posts = [];
    private $lastPosts = []; //flipped array. last post is first index
    private $threadID;
    private $lastBumpTime;
    private $OPPostID;
    private $postCount;
    private $postsFullyLoaded=false;
	private $postRepo;
	public function __construct($conf, $lastBumpTime, $threadID = -1, $OPPostID = -1, $postCount = -1){
		$this->conf = $conf;
        $this->threadID = $threadID;
        $this->lastBumpTime = $lastBumpTime;
        $this->OPPostID = $OPPostID;
        $this->postCount = $postCount;
		$this->postRepo = PostRepoClass::getInstance();
	}

    public function getLastBumpTime(){
        return $this->lastBumpTime;
    }
    public function getThreadID(){
        return $this->threadID;
    }
    public function getPostCount(){
        return $this->postCount;
    }
    public function getOPPostID(){  
        return $this->OPPostID;
    }
	/* build postObj from postrequest -> validate postObj -> save postObj to database */ // -> redraw pages -> redirect user */
    public function getPosts(){
        if($this->postsFullyLoaded != true){
            $this->posts = $this->postRepo->loadPostsByThreadID($this->conf, $this->threadID);
            $this->postFullyLoaded = true;
        }
        return $this->posts;
    }
    public function getPostByID($postID){
        if($this->postsFullyLoaded != true && !isset($this->posts[$postID])){
            $this->posts[$postID] = $this->postRepo->loadPostByThreadID($this->conf, $this->threadID ,$postID);
        }
        return $this->posts[$postID];
    }
    public function getLastNPost($num){
        if ($this->postsFullyLoaded != true && !count($this->lastPosts) >= $num) {
            $this->lastPosts[] = $this->postRepo->loadLastPostByThreadID($this->conf, $this->threadID);
        }
        return $this->posts;
    }
    public function setOPPostID($postID){
        $this->OPPostID = $postID;
    }
    public function setThreadID($threadID){
        $this->threadID = $threadID;
    }
    public function setPostCount($postCount){
        $this->postCount = $postCount;
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
