<?php
/***************
 * Display IP Migration CLI
 *
 * Backfills the display_ip table for posts that were created before the
 * displayIp module started storing partial IP strings at registration time.
 *
 * Usage:
 *   php Utilities/migrateDisplayIp-cli.php [--batch-size=500] [--dry-run]
 *
 * Options:
 *   --batch-size=N   Number of posts to process per batch (default: 500)
 *   --dry-run        Print what would be inserted without writing to the DB
 ****************/

$rootDir = __DIR__ . '/../';

require $rootDir . 'autoload.php';

require_once $rootDir . 'module/displayIp/displayIpRepository.php';
require_once $rootDir . 'module/displayIp/moduleMain.php';
require_once $rootDir . 'code/Kokonotsuba/constants.php';
require $rootDir . 'paths.php';
require $rootDir . 'bootstrap/libraryIncludes.php';

use Kokonotsuba\containers\appContainer;
use Kokonotsuba\cookie\cookieService;
use Kokonotsuba\request\request;
use Kokonotsuba\account\staffAccountFromSession;
use Kokonotsuba\policy\postPolicy;
use Kokonotsuba\policy\postRenderingPolicy;
use Kokonotsuba\Modules\displayIp\displayIpRepository;
use Kokonotsuba\Modules\displayIp\moduleMain as displayIpModule;

// ─── Parse CLI options ───────────────────────────────────────────────────────

$options    = getopt('', ['batch-size::', 'dry-run']);
$batchSize  = isset($options['batch-size']) ? max(1, (int)$options['batch-size']) : 500;
$dryRun     = array_key_exists('dry-run', $options);

// ─── Bootstrap ───────────────────────────────────────────────────────────────

$request                = new request();
$globalConfig           = getGlobalConfig();
$cookieService          = new cookieService([]);
$staffAccountFromSession = new staffAccountFromSession();
$currentUserId          = $staffAccountFromSession->getUID();
$postPolicy             = new postPolicy(
    $globalConfig['AuthLevels'],
    $staffAccountFromSession->getRoleLevel(),
    $currentUserId
);
$postRenderingPolicy    = new postRenderingPolicy(
    $globalConfig['AuthLevels'],
    $staffAccountFromSession->getRoleLevel(),
    $currentUserId,
    $cookieService
);

require $rootDir . 'bootstrap/database.php';

/** @var \Kokonotsuba\database\databaseConnection $databaseConnection */
/** @var \Kokonotsuba\database\transactionManager $transactionManager */
/** @var array $dbSettings */

$container = new appContainer();
$container->set('request',                  $request);
$container->set('cookieService',            $cookieService);
$container->set('staffAccountFromSession',  $staffAccountFromSession);
$container->set('currentUserId',            $currentUserId);
$container->set('postPolicy',               $postPolicy);
$container->set('postRenderingPolicy',      $postRenderingPolicy);
$container->set('globalConfig',             $globalConfig);
$container->set('databaseConnection',       $databaseConnection);
$container->set('transactionManager',       $transactionManager);

require $rootDir . 'bootstrap/repositories.php';

// ─── Setup ───────────────────────────────────────────────────────────────────

$postTable      = $dbSettings['POST_TABLE'];
$displayIpTable = $dbSettings['DISPLAY_IP_TABLE'];

$displayIpRepo = new displayIpRepository($databaseConnection, $displayIpTable);

// Use the module's own compute method via a throwaway instance
// We call the private method reflectively to avoid duplicating the IP logic.
$moduleReflection = new ReflectionClass(displayIpModule::class);
$computeMethod    = $moduleReflection->getMethod('computeDisplayFragment');
$computeMethod->setAccessible(true);
$moduleInstance   = $moduleReflection->newInstanceWithoutConstructor();

// Count posts that have no display_ip entry yet
$totalQuery  = "SELECT COUNT(*) FROM {$postTable} p
                WHERE NOT EXISTS (
                    SELECT 1 FROM {$displayIpTable} dip WHERE dip.post_uid = p.post_uid
                )";
$total = (int)$databaseConnection->fetchColumn($totalQuery);

if ($total === 0) {
    echo "Nothing to migrate — all posts already have a display_ip entry.\n";
    exit(0);
}

echo "Posts without a display_ip entry: {$total}\n";
if ($dryRun) {
    echo "[DRY RUN] No changes will be written.\n";
}
echo "Batch size: {$batchSize}\n\n";

// ─── Migrate in batches ───────────────────────────────────────────────────────

$fetchQuery = "SELECT post_uid, host FROM {$postTable} p
               WHERE NOT EXISTS (
                   SELECT 1 FROM {$displayIpTable} dip WHERE dip.post_uid = p.post_uid
               )
               ORDER BY p.post_uid ASC
               LIMIT :limit OFFSET :offset";

$processed = 0;
$offset    = 0;
$errors    = 0;

while ($offset < $total) {
    $rows = $databaseConnection->fetchAllAsArray($fetchQuery, [':limit' => $batchSize, ':offset' => $offset]);

    if (empty($rows)) {
        break;
    }

    foreach ($rows as $row) {
        $postUid  = (int)$row['post_uid'];
        $ip       = (string)$row['host'];
        $fragment = $computeMethod->invoke($moduleInstance, $ip);

        if (!$dryRun) {
            try {
                $displayIpRepo->insertIpPart($postUid, $fragment);
            } catch (Exception $e) {
                echo "  ERROR inserting post_uid={$postUid}: " . $e->getMessage() . "\n";
                $errors++;
            }
        }

        $processed++;
    }

    $pct = round($processed / $total * 100);
    echo "\r  {$processed}/{$total} ({$pct}%)   ";

    $offset += $batchSize;
}

echo "\n\nDone.\n";
echo "  Processed : {$processed}\n";
if ($errors > 0) {
    echo "  Errors    : {$errors}\n";
}
if ($dryRun) {
    echo "  (dry run — nothing was written)\n";
}
