<?php

/**
 * Variables injected from the calling bootstrap context (e.g. koko.php).
 *
 * @var \Kokonotsuba\request\request                    $request
 * @var \Kokonotsuba\cookie\cookieService               $cookieService
 * @var \Kokonotsuba\account\staffAccountFromSession    $staffAccountFromSession
 * @var int|null                                        $currentUserId
 * @var \Kokonotsuba\policy\postPolicy                  $postPolicy
 * @var \Kokonotsuba\policy\postRenderingPolicy         $postRenderingPolicy
 * @var array                                           $globalConfig
 * @var \Kokonotsuba\database\databaseConnection        $databaseConnection
 * @var \Kokonotsuba\database\transactionManager        $transactionManager
 * @var array                                           $dbSettings
 */

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
$container->set('dbSettings', $dbSettings);
