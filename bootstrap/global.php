<?php

/**
 * Variables injected from the calling bootstrap context (e.g. koko.php).
 *
 * @var \Kokonotsuba\cookie\cookieService $cookieService
 */

use Kokonotsuba\account\staffAccountFromSession;
use Kokonotsuba\policy\postPolicy;
use Kokonotsuba\policy\postRenderingPolicy;
use Puchiko\background\BackgroundTaskDispatcher;

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