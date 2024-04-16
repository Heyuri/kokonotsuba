<?php

require_once __DIR__ .'/hook.php';
require_once __DIR__ .'/repos/repoThread.php';
$HOOK = HookClass::getInstance();
$THREADREPO = ThreadRepoClass::getInstance();


class htmlclass {
    private string $html = "";
    private array $conf;
    private boardClass $board;
    public function __construct(array $conf, boardClass $board) {
        $this->conf = $conf;
        $this->board = $board;
    }
    private function drawHead(){
        $staticPath = $this->conf['staticPath'];
        $this->html .= '
        <!--drawHead() Hello!! If you are looking to modify this webapge. please check out kotatsu github and look in /classes/html.php-->
        <head>
            <!--tell browsers to use UTF-8-->
            <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
            <!--always get newest content-->
            <meta http-equiv="cache-control" content="max-age=0">
            <meta http-equiv="cache-control" content="no-cache">
            <meta http-equiv="expires" content="0">
            <meta http-equiv="expires" content="Tue, 01 Jan 1980 1:00:00 GMT">
            <meta http-equiv="pragma" content="no-cache">
            <!--mobile view-->
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <!--tell bots its ok to scrape the whole site. disallowing this wont stop bots FYI-->
            <meta name="robots" content="follow,archive">
            <!--board specific stuff-->
            <title>' . $this->conf['boardTitle'] . '</title>
            <link class="linkstyle" rel="stylesheet" type="text/css" href="'. $staticPath .'css/default.css" title="defaultcss">
            <link rel="shortcut icon" href="'. $staticPath .'image/favicon.png">';

            if($this->conf['allowRuffle']){
                $this->html .= '<script src="https://unpkg.com/@ruffle-rs/ruffle"></script>';
            }
            
            $this->html .= 
            //'<link rel="alternate" type="application/rss+xml" title="RSS 2.0 Feed" href="//nashikouen.net/main/koko.php?mode=module&amp;load=mod_rss">
        '</head>';
    }
    /*drawNavGroup expects a key,value pair. where key is displayname and value is url*/
    private function drawNavGroup($URLPair){
        //this is what i mean by grouping [ webpage1 / webpage2 / webpage3 / etc.. ]
        $this->html .= "[";
        foreach ($URLPair as $key => $value) {
            $this->html .= '<a class="navLink" href="'.$value.'">'.$key.'</a>';
            $this->html .= "/";
        }
        $this->html = substr($this->html, 0, -1);
        $this->html .= "]";
    }
    private function drawNavBar(){
        global $HOOK;
        $conf = $this->conf;


        $this->html .= '
        <!--drawNavBar()-->
        <div class="navBar">
        <div class="navLeft">';
        //$this->drawNavGroup($boardList);
        $this->drawNavGroup($conf['navLinksLeft']);
        $res = $HOOK->executeHook("onDrawNavLeft");// HOOK drawing to left side of nav
        foreach ($res as $urlGroup) {
            $this->drawNavGroup($urlGroup);
        }
        $this->html .= '</div>

        <div class="navRight">';
        $res = $HOOK->executeHook("onDrawNavRight");// HOOK drawing to right side of nav
        foreach ($res as $urlGroup) {
            $this->drawNavGroup($urlGroup);
        }
        $this->drawNavGroup($conf['navLinksRight']);
        //$this->drawNavGroup($adminStuff);
        $this->html .= '
        </div>
        </div>';
    }
    private function drawBoardTitle(){
        $conf = $this->conf;
        $conf['boardTitle'];
        $conf['boardSubTitle'];
        $conf['boardLogoPath'];
        $this->html .= '
        <!--drawBoardTitle()-->
        <div class="boardTitle">';
        if ($conf['boardLogoPath'] != ""){
            $this->html .= '<img class="logo" src="'.$conf['boardLogoPath'].'">';
        }
        $this->html .= '<h1 class="boardTitle">'.$conf['boardTitle'].'</h1>';
        $this->html .= '<h5 class="boardSubTitle">'.$conf['boardSubTitle'].'</h5>
        </div>';
    }
    private function drawFormNewThread(){
        $this->html .= '
        <!--drawFormNewThread()-->
        <!--set constraints based on board conf-->
        <div id="postForm class="postForm">';//id is used so we can jump to it from a url example.com#postForm
        $this->html .= '
        <form class="formThread" action="/bbs.php" method="post" enctype="multipart/form-data">
        <input type="hidden" name="action" value="postNewThread">
        <input type="hidden" name="boardID" value="'. $this->board->getBoardID() . '">

        <table>
        <tr>
            <td><label for="name">Name</label></td>
            <td><input type="text" id="name" name="name"></td>
        </tr>
        <tr>
            <td><label for="email">Email</label></td>
            <td>
                <input type="text" id="email" name="email">
                <button type="submit">Post</button>
            </td>
        </tr>
        <tr>
            <td><label for="subject">Subject</label></td>
            <td><input type="text" id="subject" name="subject"></td>
        </tr>
        <tr>
            <td><label for="comment">Comment</label></td>
            <td><textarea type="text" id="comment" name="comment"></textarea></td>
        </tr>
        <tr>
            <td><label for="password">Password</label></td>
            <td><input type="text" id="password" name="password"></td>
        </tr>
        </table>
        </form>
        </div>';
    }
    private function drawFormNewPost($threadID){
        $this->html .= '
        <!--drawFormNewPost()-->
        <!--set constraints based on $this->conf-->
        <div class="postForm">
        <form class="formThread" action="/bbs.php" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="threadID" value="'.$threadID.'">
        <table>
        <tr>
            <td><label for="name">Name</label></td>
            <td><input type="text" id="name" name="name"></td>
        </tr>
        <tr>
            <td><label for="email">Email</label></td>
            <td>
                <input type="text" id="email" email="email">
                <button type="submit" name="action" value="postToThread">Post</button>
            </td>
        </tr>
        <tr>
            <td><label for="subject">Subject</label></td>
            <td><input type="text" id="subject" name="subject"></td>
        </tr>
        <tr>
            <td><label for="comment">Comment</label></td>
            <td><input type="text" id="comment" name="comment"></td>
        </tr>
        <tr>
            <td><label for="subject">Subject</label></td>
            <td><input type="text" id="subject" name="subject"></td>
        </tr>
        <tr>
            <td><label for="password">Password</label></td>
            <td><input type="password" id="password" name="password"></td>
        </tr>
        </form>
        </div>';
    }
    private function drawFormManagePostsOpen(){
        $this->html .= '
        <!--drawFormManagePostsOpen()-->
        <form name="managePost" id="managePost" action="/bbs.php" method="post">';
    }
    private function drawFormManagePostsClosed(){
        // make this have a drop down of options, not just delete file.
        $this->html .= '
        <!--drawFormManagePostsClosed()-->
        <table><tr><td>
			<input type="hidden" name="action" value="deletePosts">
            Delete Post: [<label><input type="checkbox" name="fileOnly" id="fileOnly" value="on">File only</label>]<br>
			Password: <input type="password" name="password" size="8" value="">
            <input type="submit" value="Submit">
        /td></tr></table>
        </form>';
    }
    private function drawFooter(){
        $this->html .= '';
    }
    private function drawOPPostAsListing($post, $omitedPosts){
        $postID = $post->getPostID();
        $threadID = $post->getThreadID();
        $email = $post->getEmail();
        $this->html .= '
        <!--drawOPPostAsListing()-->
        <div class="post op" id="'.$postID.'">
            <div class="postinfo">
                <input type="checkbox" name="'.$postID.'">
                <span class="bigger"><b class="subject">'.$post->getSubject().'</b></span>
                <spanclass="name"';
                if($email != ""){
                    $this->html .= '<a href="mailto:'.$email.'"><b>'.$post->getName().'</b></a>';
                }else{
                    $this->html .= '<b>'.$email.'</b>';
                }
                $this->html .= '
                <span class="time">'.date('Y-m-d H:i:s', $post->getUnixTime()).'</span>
                <span class="postnum">
                    <a href="/bbs.php?boardID='.$this->conf['boardID'].'&threadID='.$threadID.'#p'.$postID.'" class="no">No.</a>
                    <a href="/bbs.php?boardID='.$this->conf['boardID'].'&threadID='.$threadID.'#postForm" title="Quote">'.$postID.'</a>
                </span>
                [
                    <a href="/bbs.php?boardID='.$this->conf['boardID'].'&threadID='.$threadID.'" class="no">Reply</a>
                ]
            </div>
            <blockquote class="comment">'.$post->getComment().'</blockquote>';
            if($omitedPosts > 0){
                $this->html .= '<span class="omittedposts">'.$omitedPosts.' posts omitted. Click Reply to view.</span>';
            }
        $this->html .= '</div>';
    }
    private function drawPosts($thread, $posts){
        $this->html .= '
        <!--drawPosts()-->';
        foreach($posts as $post){
            $postID = $post->getPostID();
            $type = "reply";
            if($postID == $thread->getOPPostID()){
                $type = "op";
            }
            $threadID = $post->getThreadID();
            $email = $post->getEmail();
            $this->html .= '
            <div class="post '.$type.'" id="'.$postID.'">
                <div class="postinfo">
                    <input type="checkbox" name="'.$postID.'">
                    <span class="bigger"><b class="subject">'.$post->getSubject().'</b></span>
                    <spanclass="name"';
                    if($email != ""){
                        $this->html .= '<a href="mailto:'.$email.'"><b>'.$post->getName().'</b></a>';
                    }else{
                        $this->html .= '<b>'.$email.'</b>';
                    }
                    $this->html .= '
                    <span class="time">'.date('Y-m-d H:i:s', $post->getUnixTime()).'</span>
                    <span class="postnum">
				        <a href="/bbs.php?boardID='.$this->conf['boardID'].'&threadID='.$threadID.'#p'.$postID.'" class="no">No.</a>
                        <a href="/bbs.php?boardID='.$this->conf['boardID'].'&threadID='.$threadID.'#postForm" title="Quote">'.$postID.'</a>
                    </span>
                </div>
                <blockquote class="comment">'.$post->getComment().'</blockquote>
            </div>';
        }
    }
    private function drawThread($thread){
        $posts = $thread->getPosts();

        $this->html .='
        <!--drawThreads()-->
        <div id="t'.$thread->getThreadID().'" class="thread>';
        $this->drawPosts($thread, $posts);
        $this->html .='
        </div>';
    }

    private function drawThreadListing($threads){
        //encapsalate all of the threads into a form
        $this->drawFormManagePostsOpen();

        $this->html .='
        <!--drawThreadListing()-->
        <div class="threadListing">';
        
        foreach ($threads as $thread) {
            $opPost = $thread->getPostByID($thread->getOPPostID());
            $posts = $thread->getLastNPost($this->conf['postPerThreadListing']);

            $omitedPost = sizeof($posts) - $thread->getPostCount() - 1;//-1 for op post

            $this->drawOPPostAsListing($opPost, $omitedPost);
            $this->drawPosts($thread, $posts);
        }
        $this->html .='</div>';

        $this->drawFormManagePostsOpen();

    }
    public function drawPage($pageNumber = 0){
        global $THREADREPO;
        $threads = $THREADREPO->loadThreadsByPage($this->conf, $pageNumber);
        
        $this->html .='
        <!DOCTYPE html>
        <html lang="en-US">';
        $this->drawHead();
        $this->html .= '<body>';
        $this->drawNavBar();
        $this->drawBoardTitle();
        $this->drawFormNewThread();
        $this->drawThreadListing($threads);

        $this->html .= '</body>';
        echo $this->html;
    }
}