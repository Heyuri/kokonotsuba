<?php
/*
* This file is for board-specific configurations
*/

$config['STORAGE_PATH'] = 'dat/'; // Storage directory, needs to have 777 permissions. Include trailing '/'
require $config['STORAGE_PATH'].'instance-config.php';

//Board database info
$config['DATABASE_DBNAME'] =  'database';
$config['DATABASE_TABLENAME'] = 'sometable';

$config['TITLE'] = 'Kokonotsuba Board'; // Board Title
$config['TITLESUB'] = ''; // Board Title
$config['TITLEIMG'] = ''; // Board Title Image (url)
