<?php

require_once __DIR__ .'/hook.php';
$HOOK = HookClass::getInstance();

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
        $this->html .= "[";
        foreach ($URLPair as $key => $value) {
            $this->html .= '<a class="navLink" href="'.$value.'">'.$key.'</a>"';
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
        <!--set constraints based on $this->conf-->
        <div class="postForm">
        <form class="formThread" action="/bbs.php" method="POST" enctype="multipart/form-data">
        <table>
        <tr>
            <td><label for="name">Name</label></td>
            <td><input type="text" id="name" name="name"></td>
        </tr>
        <tr>
            <td><label for="email">Email</label></td>
            <td>
                <input type="text" id="email" email="email">
                <button type="submit" name="action" value="postNewThread">Post</button>
            </td>
        </tr>
        <tr>
            <td><label for="subject">Subject</label></td>
            <td><input type="text" id="subject" subject="subject"></td>
        </tr>
        <tr>
            <td><label for="comment">Comment</label></td>
            <td><input type="text" id="comment" subject="comment"></td>
        </tr>
        <tr>
            <td><label for="subject">Subject</label></td>
            <td><input type="text" id="subject" subject="subject"></td>
        </tr>
        <tr>
            <td><label for="password">Password</label></td>
            <td><input type="text" id="password" password="password"></td>
        </tr>
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
    private function drawPostOP($post){
        $this->html .= '';
    }
    private function drawPosts($posts){
        $this->html .= '';
    }
    public function drawPage($pageNumber = 1){
        $this->html .='
        <!DOCTYPE html>
        <html lang="en-US">';
        $this->drawHead();
        $this->html .= '<body>';
        $this->drawNavBar();
        $this->drawBoardTitle();
        $this->drawFormNewThread();

        $this->html .= '</body>';
    }
    public function drawThread($threadID){

    }
}