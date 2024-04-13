<?php

require_once __DIR__ .'/classes/board.php';
require_once __DIR__ .'/classes/repos/repoBoard.php';
$BOARDREPO = BoardRepoClass::getInstance();

$name =  uniqid();

copy(__DIR__ . "/boardConfigs/baseConf.php", __DIR__ . "/boardConfigs/". $name . ".php");

$board = new boardClass(__DIR__ . "/boardConfigs/" . $name . ".php",0);

$BOARDREPO->createBoard($board, function($str){
    echo $str;
});

