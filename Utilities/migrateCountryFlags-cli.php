<?php
/***************
 * Country Flags Migration CLI
 *
 * Backfills the country_flags table for posts that were created before the
 * countryFlags module started storing flags at registration time.
 *
 * Usage:
 *   php Utilities/migrateCountryFlags-cli.php [--batch-size=500] [--dry-run]
 *
 * Options:
 *   --batch-size=N   Number of posts to process per batch (default: 500)
 *   --dry-run        Print what would be inserted without writing to the DB
 ****************/

$rootDir = __DIR__ . '/../';

require_once $rootDir . 'module/countryFlags/geoip/geoip2.phar';
require_once $rootDir . 'module/countryFlags/countryFlagRepository.php';

require $rootDir . 'autoload.php';
require_once $rootDir . 'code/Kokonotsuba/constants.php';
require $rootDir . 'paths.php';
require $rootDir . 'bootstrap/libraryIncludes.php';

use GeoIp2\Database\Reader;
use Kokonotsuba\containers\appContainer;
use Kokonotsuba\cookie\cookieService;
use Kokonotsuba\request\request;
use Kokonotsuba\account\staffAccountFromSession;
use Kokonotsuba\policy\postPolicy;
use Kokonotsuba\policy\postRenderingPolicy;
use Kokonotsuba\Modules\countryFlags\countryFlagRepository;

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

$postTable        = $dbSettings['POST_TABLE'];
$countryFlagTable = $dbSettings['COUNTRY_FLAG_TABLE'];

$flagRepo = new countryFlagRepository($databaseConnection, $countryFlagTable);
$reader   = new Reader($rootDir . 'module/countryFlags/geoip/GeoLite2-Country.mmdb');

// Count posts that have no flag entry yet
$totalQuery  = "SELECT COUNT(*) FROM {$postTable} p
                WHERE NOT EXISTS (
                    SELECT 1 FROM {$countryFlagTable} cf WHERE cf.post_uid = p.post_uid
                )";
$totalResult = $databaseConnection->getPdo()->query($totalQuery)->fetchColumn();
$total       = (int)$totalResult;

if ($total === 0) {
    echo "Nothing to migrate — all posts already have a country flag entry.\n";
    exit(0);
}

echo "Posts without a flag entry: {$total}\n";
if ($dryRun) {
    echo "[DRY RUN] No changes will be written.\n";
}
echo "Batch size: {$batchSize}\n\n";

// ─── Migrate in batches ───────────────────────────────────────────────────────

$fetchQuery = "SELECT post_uid, host FROM {$postTable} p
               WHERE NOT EXISTS (
                   SELECT 1 FROM {$countryFlagTable} cf WHERE cf.post_uid = p.post_uid
               )
               ORDER BY p.post_uid ASC
               LIMIT :limit OFFSET :offset";

$pdo        = $databaseConnection->getPdo();
$fetchStmt  = $pdo->prepare($fetchQuery);

$processed  = 0;
$offset     = 0;
$errors     = 0;

while ($offset < $total) {
    $fetchStmt->bindValue(':limit',  $batchSize, PDO::PARAM_INT);
    $fetchStmt->bindValue(':offset', $offset,    PDO::PARAM_INT);
    $fetchStmt->execute();
    $rows = $fetchStmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($rows)) {
        break;
    }

    foreach ($rows as $row) {
        $postUid = (int)$row['post_uid'];
        $ip      = (string)$row['host'];

        // Resolve country code via GeoIP2
        $countryCode = 'XX';
        try {
            $resolvedIp = gethostbyname($ip);
            $record     = $reader->country($resolvedIp);
            $code       = $record->country->isoCode;
            if ($code !== null && $code !== '') {
                $countryCode = $code;
            }
        } catch (Exception $e) {
            // Unknown / private IP — leave as XX
        }

        if (!$dryRun) {
            try {
                $flagRepo->insertFlag($postUid, $countryCode);
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
