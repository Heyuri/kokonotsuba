<?php

use Kokonotsuba\account\staffAccountFromSession;
use Kokonotsuba\policy\postPolicy;
use Kokonotsuba\policy\postRenderingPolicy;

// Global configuration file
$globalConfig = getGlobalConfig();

// ───────────────────────────────────────
// Session & Validation
// ───────────────────────────────────────
$staffAccountFromSession = new staffAccountFromSession;

// ───────────────────────────────────────
// Policies
// ───────────────────────────────────────
$postPolicy = new postPolicy($globalConfig['AuthLevels'], $staffAccountFromSession->getRoleLevel());
$postRenderingPolicy = new postRenderingPolicy($globalConfig['AuthLevels'], $staffAccountFromSession->getRoleLevel());
