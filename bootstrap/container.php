<?php

use Kokonotsuba\containers\appContainer;

// ───────────────────────────────────────
// Application Service Container
// ───────────────────────────────────────
$container = new appContainer();

// Register pre-database services (from session, cookies, global bootstrap)
$container->set('request', $request);
$container->set('cookieService', $cookieService);
$container->set('staffAccountFromSession', $staffAccountFromSession);
$container->set('currentUserId', $currentUserId);
$container->set('postPolicy', $postPolicy);
$container->set('postRenderingPolicy', $postRenderingPolicy);
$container->set('globalConfig', $globalConfig);

// Register database services
$container->set('databaseConnection', $databaseConnection);
$container->set('transactionManager', $transactionManager);
