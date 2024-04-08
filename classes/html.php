<?php

require_once __DIR__ .'/classes/hook.php';
$HOOK = HookClass::getInstance();

class htmlclass {
    private string $html;
    private array $conf;
    public function __construct(array $conf) {
        $this->conf = $conf;
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
    private function drawNavGroup($URL){
        $this->html .= "[";
        foreach ($URL as $key => $value) {
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
        $res = $HOOK->executeHook("navLinksRight");// HOOK drawing to right side of nav
        foreach ($res as $urlGroup) {
            $this->drawNavGroup($urlGroup);
        }
        $this->drawNavGroup($conf['drawNavRight']);
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
        <div class="postForm">
        <form>

        </form>
        </div>';
    }

    private function drawFormNewPost(){
        $this->html .= "";

    }
    private function drawFormModifyPost(){
        $this->html .= "";
    }
    private function drawModuelList(){
        $this->html .= "";
    }

    private function drawTable(){
        $this->html .= "";
    }

    private function drawPosts(){
        $this->html .= "";
    }
    private function drawPageList(){
        $this->html .= "";
    }

    private function clearDraw(){
        $this->html = "";
    }

}