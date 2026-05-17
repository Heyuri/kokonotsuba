<?php

/**
 * Variables injected from the calling bootstrap context (e.g. koko.php).
 *
 * @var \Kokonotsuba\board\boardService $boardService
 */

// Get the board that's currently being accessed by the request
$board = $boardService->getBoardFromBootstrapFile('./boardUID.ini');

// ───────────────────────────────────────
// Validate Early
// ───────────────────────────────────────
if (!file_exists($board->getFullConfigPath()) || !file_exists($board->getBoardStoragePath())) {
	die("Invalid board setup");
}

$config = $board->loadBoardConfig();
