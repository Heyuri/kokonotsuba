<?php
/*
* This file is for board-specific configurations. Make sure that values in globalconfig.php are set correctly.
*/

$config['STORAGE_PATH'] = 'dat/'; // Storage directory, needs to have 777 permissions. Include trailing '/'
require $config['STORAGE_PATH'].'globalconfig.php';

//Board database info
$config['DATABASE_DBNAME'] =  'database';
$config['DATABASE_TABLENAME'] = 'sometable';

$config['TITLE'] = 'Kokonotsuba Board'; // Board Title
$config['TITLESUB'] = ''; // Board Title
$config['TITLEIMG'] = ''; // Board Title Image (url)
