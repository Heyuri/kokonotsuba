<?php
/*
* This file is for board-specific configurations. Make sure that values in globalconfig.php are set correctly.
*/

$config['GLOBAL_PATH'] = './global/'; //Directory for files used by all boards. Include trailing '/'
$config['STORAGE_PATH'] = './dat/'; // Board storage directory, needs to have 777 permissions. Include trailing '/'

require $config['GLOBAL_PATH'].'globalconfig.php';


//Board database info
$config['DATABASE_DBNAME'] =  'database';
$config['DATABASE_TABLENAME'] = 'sometable';

$config['TITLE'] = 'Kokonotsuba Board'; // Board Title
$config['TITLESUB'] = ''; // Board Title
$config['TITLEIMG'] = ''; // Board Title Image (url)

$config['IMG_DIR'] = 'src/'; // Image Directory
$config['THUMB_DIR'] = 'src/'; // Thumbnail Directory
$config['CDN_DIR'] = ''; // absolute path to the folder for storing imgs & thumbs (excluding IMG_DIR, e.g. /var/www/cdn/heyuri/)
$config['CDN_URL'] = ''; // img/thumb CDN url (without IMG_DIR directory, e.g. https://h.kncdn.org/b/). Set to blank for locally hosted files


/* 
* Board modules - you can adjust module settings specifically for this board by overwriting the value of the module to false or 0
* e.g $config['ModuleList']['mod_showip'] = false; 
*/
