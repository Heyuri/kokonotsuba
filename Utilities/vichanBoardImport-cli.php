
<?php
/***************
 * !NOTICE!
 * 
 * This script outlived its use and isn't maintained. If you want to use it, move it back into the root directory of koko and run it 
 *
****************/

// init regular dependencies
// aside from session.php since thats exclusively a request
require __DIR__ . '/paths.php';
require __DIR__ . '/includes.php';
require __DIR__ . '/bootstrap/database.php';
require __DIR__ . '/bootstrap/repositories.php';
require __DIR__ . '/bootstrap/board.php';

// get sql dump path from the first db argument
$sqlDump = $argv[1] ?? null;

// the base boards path
$boardPath = $argv[2] ?? null;

if (!$sqlDump || !$boardPath) {
	echo "Usage: php " . __FILE__ . " /path/to/vichan.sql /boards/path\n";
	exit(1);
}

$boardCreator = new boardCreator($boardService);

// init vichanBoardImporter class
$vichanBoardImporter = new vichanBoardImporter(
    $databaseConnection, 
    $boardCreator,
    $boardService,
    $postRepository,
    $threadRepository,
    $fileService,
    $transactionManager,
    $quoteLinkRepository);


$vichanBoardImporter->importVichanInstance($sqlDump, $boardPath);