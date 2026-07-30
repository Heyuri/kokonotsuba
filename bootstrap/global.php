<?php

/**
 * Variables injected from the calling bootstrap context (e.g. koko.php).
 *
 * @var \Kokonotsuba\cookie\cookieService $cookieService
 */

use Kokonotsuba\account\staffAccountFromSession;
use Kokonotsuba\background\rebuildBoardsTask;
use Kokonotsuba\policy\postPolicy;
use Kokonotsuba\policy\postRenderingPolicy;
use Puchiko\background\BackgroundTaskDispatcher;
use Puchiko\background\BackgroundTaskRegistry;

BackgroundTaskDispatcher::setContext(($kokoInstanceRoot ?? __DIR__ . '/../') . 'bootstrap/background.php');
BackgroundTaskDispatcher::setAppRoot($kokoInstanceRoot ?? __DIR__ . '/../');

// Core background tasks. Registered here rather than by a module so that core routes (the board
// and global config editors) can dispatch a rebuild whether or not the rebuild module is enabled.
BackgroundTaskRegistry::register('rebuild_boards', rebuildBoardsTask::class);

// Global configuration file
$globalConfig = getGlobalConfig();

// ───────────────────────────────────────
// Session & Validation
// ───────────────────────────────────────
$staffAccountFromSession = new staffAccountFromSession;
$currentUserId = $staffAccountFromSession->getUID();

// ───────────────────────────────────────
// Policies
// ───────────────────────────────────────
$postPolicy = new postPolicy(
    $globalConfig['AuthLevels'], 
    $staffAccountFromSession->getRoleLevel(),
    $currentUserId);
    
$postRenderingPolicy = new postRenderingPolicy(
    $globalConfig['AuthLevels'], 
    $staffAccountFromSession->getRoleLevel(), 
    $currentUserId,
	$cookieService);