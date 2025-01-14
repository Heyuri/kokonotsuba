<?php
/*
* This file is for board-specific configurations. Make sure that values in globalconfig.php are set correctly.
*/

require __DIR__.'/../globalBoardConfig.php';

$config['TITLEIMG'] = ''; // Board Title Image (url)

$config['IMG_DIR'] = 'src/'; // Image Directory
$config['THUMB_DIR'] = 'src/'; // Thumbnail Directory

/* 
* Board modules - you can adjust module settings specifically for this board by overwriting the value of the module to false or 0
* e.g $config['ModuleList']['mod_showip'] = false; 
*/
