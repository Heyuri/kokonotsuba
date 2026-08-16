<?php

use function Puchiko\createDirectory;
use function Puchiko\createFileAndWriteText;
use function Puchiko\request\redirect;

function getRootPath() {
    $kokoFile = __DIR__ . DIRECTORY_SEPARATOR . 'koko.php';
    if (!file_exists($kokoFile)) {
        die(
            "The file <i>" . __DIR__ . DIRECTORY_SEPARATOR . "koko.php</i> couldn't be found. Please create it with the following code:<br>" .
            "<code>&lt;?php require_once '/path/to/kokonotsuba/koko.php'; ?&gt;</code>"
        );
    }

    $fileHandle = fopen($kokoFile, 'r');
    if (!$fileHandle) {
        die("Error: Unable to open <i>koko.php</i>.");
    }

    while (($line = fgets($fileHandle)) !== false) {
        if (preg_match("/require(?:_once)? ['\"](.*?koko\.php)['\"];/", $line, $matches)) {
            fclose($fileHandle);
            // Use dirname to extract the directory path from the matched file
            return dirname($matches[1]);
        }
    }

    fclose($fileHandle);
    return __DIR__;
}


define('ROOTPATH', getRootPath());

require ROOTPATH . '/autoload.php';
require ROOTPATH . '/code/Puchiko/includes.php';
require ROOTPATH . '/code/Kokonotsuba/constants.php';
require ROOTPATH . '/code/Kokonotsuba/userRole.php';

use Kokonotsuba\database\databaseConnection;
use Kokonotsuba\migrations\migrationLedger;
use Kokonotsuba\migrations\migrationRunner;
use Kokonotsuba\migrations\schemaInspector;
use const Kokonotsuba\GLOBAL_BOARD_UID;

$extensions = [
    'mbstring',
    'pdo',
    'gd',
    'bcmath',
];

$commands = [
    'ffmpeg',
    'exiftool'
];

function checkExtensions(array $extensions) {
    $results = [];
    foreach ($extensions as $extension) {
        $results[$extension] = extension_loaded($extension);
    }
    return $results;
}

function checkCommands(array $commands) {
    $results = [];
    foreach ($commands as $command) {
        $results[$command] = isCommandAvailable($command);
    }
    return $results;
}

function isCommandAvailable(string $command): bool {
    $output = null;
    $status = null;
    exec("which " . escapeshellarg($command), $output, $status);
    return $status === 0 && !empty($output);
}

function getGlobalConfig(): array {
    return require ROOTPATH . '/global/globalconfig.php';
}

function getBoardStorageDir() {
    return ROOTPATH.'/global/board-storages/';
}

// Function to sanitize table names using regular expression validation
// Set a value at a dot-path within a nested config array (installer-local helper).
function setNestedInstallConfig(array &$config, string $dotpath, $value): void {
    $segments = explode('.', $dotpath);
    $cursor =& $config;
    foreach ($segments as $i => $segment) {
        if ($i === array_key_last($segments)) {
            $cursor[$segment] = $value;
            return;
        }
        if (!isset($cursor[$segment]) || !is_array($cursor[$segment])) {
            $cursor[$segment] = [];
        }
        $cursor =& $cursor[$segment];
    }
}

// Build the board-agnostic default config: globalconfig.php base + the editable configs/
// core schema defaults + each module's own module/{name}/config.php defaults.
function getTemplateConfigArray() {
    $config = getGlobalConfig();

    // Core config files: keys are full config dot-paths.
    foreach (glob(ROOTPATH . '/configs/*.php') ?: [] as $schemaFile) {
        // Files beginning with "_" are shared helpers (e.g. _fieldTypes.php), not groups.
        if (str_starts_with(basename($schemaFile), '_')) {
            continue;
        }
        $definition = require $schemaFile;
        if (!is_array($definition)) {
            continue;
        }
        unset($definition['_group'], $definition['_module']);
        foreach ($definition as $dotpath => $meta) {
            $default = (is_array($meta) && array_key_exists('default', $meta)) ? $meta['default'] : $meta;
            setNestedInstallConfig($config, (string) $dotpath, $default);
        }
    }

    // Per-module config files: bare keys prefixed with "modules.{name}.".
    foreach (glob(ROOTPATH . '/module/*/config.php') ?: [] as $moduleFile) {
        $moduleName = basename(dirname($moduleFile));
        $definition = require $moduleFile;
        if (!is_array($definition)) {
            continue;
        }
        unset($definition['_group'], $definition['_module']);
        foreach ($definition as $key => $meta) {
            $default = (is_array($meta) && array_key_exists('default', $meta)) ? $meta['default'] : $meta;
            setNestedInstallConfig($config, "modules.{$moduleName}.{$key}", $default);
        }
    }

    return $config;
}

function createBoardAndFiles($boardTable) {
    //create board
    $board_identifier = $_POST['board-identifier'] ?? '';
    $board_title = $_POST['board-title'] ?? '';
    $board_sub_title = $_POST['board-sub-title'] ?? '';
    $board_path = $_POST['board-path'] ?? '';


    $globalConfig = getGlobalConfig();
    $mockConfig = getTemplateConfigArray();

    $nextBoardUID = $boardTable->getLastBoardUID() + 1;

    $dataDirName = 'storage-'.$nextBoardUID;
    $dataDir = getBoardStorageDir().'/'.$dataDirName;
    //create physical board files
    $fileUploadedImgDirectory = $globalConfig['USE_CDN']
        ? $globalConfig['CDN_DIR'].$board_identifier.'/'.$mockConfig['IMG_DIR'].'/'
        : $board_path . $mockConfig['IMG_DIR'].'/';
    $fileUploadedThumbDirectory = $globalConfig['USE_CDN']
        ? $globalConfig['CDN_DIR'].$board_identifier.'/'.$mockConfig['THUMB_DIR'].'/'
        : $board_path.$mockConfig['THUMB_DIR'].'/';

    //create upload dirs
    createDirectory($fileUploadedImgDirectory);
    createDirectory($fileUploadedThumbDirectory);
    //create dat
    createDirectory($dataDir);

    // Board config is stored in the board_configs table (created on first edit via the admin
    // board configuration editor). No per-board PHP config file is generated.
    $boardTable->addFirstBoard($board_identifier, $board_title, $board_sub_title, $dataDirName);
    $boardUIDforBootstrapFile = $boardTable->getLastBoardUID();
    createFileAndWriteText($board_path, 'boardUID.ini', "board_uid = $boardUIDforBootstrapFile");
}

class html {
    private $dbSettings;

    public function __construct($dbSettings) {
        $this->dbSettings = $dbSettings;
    }

    public function drawHeader() {
        echo '<head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">

            <!-- Prevent caching -->
            <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, proxy-revalidate">
            <meta http-equiv="Pragma" content="no-cache">
            <meta http-equiv="Expires" content="0">

            <!-- Prevent archiving by search engines -->
            <meta name="robots" content="noarchive, noindex, nofollow">
            <meta http-equiv="X-Robots-Tag" content="noindex, nofollow">

            <title>Kokonotsuba Installer</title>
        </head>
        <h1 class="page-head-title">Kokonotsuba Installer</h1>';
    }

    public function drawStyle() {
        echo '<style>
            .postblock {
                border: 1px solid #800043;
                background: #eeaa88;
            }
            .notice-text {
                padding-bottom: 20px;
                text-align:center;
            }

            body {
                background-color: #ffffee;
                color: #880000;
                font-size: 16px;
            }
        </style>';
    }

    public function drawInstallNotice() {
        echo '<div class="notice-text">
            <h2>Notice!</h2>
            <p>Kokonotsuba is a BBS software in active development.</p>
            <p>Read the instructions, other documentation or open an Issue on the <a href="https://github.com/Heyuri/kokonotsuba">repo</a> if there are any problems</p>
            <p>For more info: <a href="https://kokonotsuba.github.io/">see here</a></p>
        </div><hr size=1>';
    }

    public function drawRequiredExtentions() {
        global $extensions, $commands;
        $extentionResults = checkExtensions($extensions);
        $commandResults = checkCommands($commands);

        echo '<h3>Required extensions</h3>
        <p>These are the extensions required for Kokonotsuba to work fully:</p>
        <ul>';
        foreach ($extentionResults as $extension => $isEnabled) {
            echo "<li>$extension: " . ($isEnabled ? 'enabled' : 'not enabled') . '</li>';
        }
        echo '</ul>';
        echo '<h3>Required commands</h3>
        <p>These are the commands that are required for certain features in Kokonotsuba';
        foreach($commandResults as $command => $isInstalled) {
            echo '<li>' . $command . ': ' . ($isInstalled ? 'enabled' : 'not enabled') . '</li>';
        }
        echo '</ul>';
    }

    public function drawImportantConfigValuesPreview() {
        $globalConfig = getGlobalConfig();

        $websiteURL = $globalConfig['WEBSITE_URL'];
        $staticURL = $globalConfig['STATIC_URL']; // eg. 'https://static.example.com/'
        $staticPath = $globalConfig['STATIC_PATH']; // eg. '/home/example/web/static/'

        echo '<h3>Config</h3>
        <p>Ensure these values are correctly set in global/globalconfig.php:</p>
        <table>
            <tr>
                <td>Static Path:</td>
                <td>' . htmlspecialchars($staticPath) . '</td>
            </tr>
            <tr>
                <td>Static URL:</td>
                <td>' . htmlspecialchars($staticURL) . '</td>
            </tr>
            <tr>
                <td>Website URL:</td>
                <td>' . htmlspecialchars($websiteURL) . '</td>
            </tr>
        </table>';
    }

    public function drawInstallForm() {
        echo '<form id="installation-form" action="' . htmlspecialchars($_SERVER['PHP_SELF']) . '" method="POST">
            <input type="hidden" name="action" value="install">
            <h3>Admin Account</h3>
        <p>The username and password of the admin account, it can be changed at any time</p>
            <table id="installation-form-admin-account-table">
                <tr>
                    <td class="postblock"> <label for "admin-username-input" >Admin username</label></td>
                    <td> <input id="admin-username-input" name="admin-username" required> </td>
                </tr>
                <tr>
                    <td class="postblock"> <label for "admin-password-input">Admin password</label></td>
                    <td> <input type="password" id="admin-password-input" name="admin-password" required> </td>
                </tr>
            </table>
            <h3>First Board</h3>
        <p>This will be the first board on your kokonotsuba instance</p>
            <table id="installation-form-admin-account-table">
                <tr> 
                    <td class="postblock"> <label for "first-board-identifier-input" >Board identifier</label></td>
                    <td> <input id="first-board-identifier-input" name="board-identifier" placeholder="b" value="'.basename(__DIR__).'"> </td>
                    <td> (leave blank if the board is in web root) </td>
                </tr>
                <tr> 
                    <td class="postblock"> <label for "first-board-title-input" >Board title</label></td>
                    <td> <input id="first-board-title-input" name="board-title" placeholder="board@example.net" required> </td>
                </tr>
                <tr> 
                    <td class="postblock"> <label for "first-board-sub-title-input" >Board sub-title</label></td>
                    <td> <input id="first-board-sub-title-input" name="board-sub-title" placeholder="an example board" required> </td>
                </tr>
                <tr> 
                    <td class="postblock"> <label for "first-board-path-input" >Board path</label></td>
                    <td> <input id="first-board-path-input" name="board-path" placeholder="an example board" value="'.dirname(__FILE__).'/'.'" required> </td>
                </tr>
            </table>
            <input type="submit" value="Install">
        </form>';
    }

    public function drawFooter() {
        echo '<hr>';
    }
}


class accountTable {
    private $db, $accountTableName;

    public function __construct($pdoConnection, $accountTableName) {	
        $this->db = $pdoConnection;
        $this->accountTableName = $accountTableName;
    }

    public function addAdminAccount($username, $unhashedPassword, $role) {
        $hashedPassword = password_hash($unhashedPassword, PASSWORD_DEFAULT);
        $query = "INSERT INTO {$this->accountTableName} (username, password_hash, role) VALUES(:username, :password_hash, :role)";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':password_hash', $hashedPassword);
        $stmt->bindParam(':role', $role);
        return $stmt->execute();
    }

    // Returns true if the accounts table already holds at least one account.
    // Used to refuse re-running the installer (which would create a new admin) on an
    // already-provisioned instance, even if the .installed marker is missing.
    public function anyAccountExists() {
        try {
            $stmt = $this->db->query("SELECT 1 FROM {$this->accountTableName} LIMIT 1");
            return $stmt !== false && $stmt->fetchColumn() !== false;
        } catch (PDOException $e) {
            // Table doesn't exist yet (fresh DB) - no accounts.
            return false;
        }
    }
}


class boardTable {
    private $db, $boardTableName, $databaseName;

    // Constructor to initialize the PDO connection, table name, and database name
    public function __construct($pdoConnection, $boardTableName, $databaseName) {
        $this->db = $pdoConnection;
        $this->boardTableName = $boardTableName;
        $this->databaseName = $databaseName;
    }

    // Method to create a global board if it doesn't exist
    public function createGlobalBoard() {
        // Check if the global board already exists
        $query = "SELECT COUNT(*) FROM {$this->boardTableName} WHERE board_uid = :global_board_uid";
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            ':global_board_uid' => GLOBAL_BOARD_UID
        ]);
        $count = $stmt->fetchColumn();

        // If global board doesn't exist, insert it
        if ($count == 0) {
            // Insert the global board with a reserved UID
            $query = "INSERT INTO {$this->boardTableName}
                        (board_uid, board_identifier, board_title, board_sub_title, storage_directory_name, listed, date_added)
                      VALUES
                        (:board_uid, :board_identifier, :board_title, :board_sub_title, :storage_directory_name, :listed, :date_added)";

            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':board_uid', GLOBAL_BOARD_UID);
            $stmt->bindValue(':board_identifier', 'GLOBAL');
            $stmt->bindValue(':board_title', 'GLOBAL');
            $stmt->bindValue(':board_sub_title', 'Global board scope');
            $stmt->bindValue(':storage_directory_name', '');
            $stmt->bindValue(':listed', 0, PDO::PARAM_INT);
            $stmt->bindValue(':date_added', date('Y-m-d'));
            
            return $stmt->execute(); // Return true if successful
        }

        // If the global board exists, return false or a message (optional)
        return false; // Board already exists
    }

    // Method to add the first board to the system (example for initial setup)
    public function addFirstBoard($board_identifier, $board_title, $board_sub_title, $storage_directory_name) {
        $query = "INSERT INTO {$this->boardTableName}
                    (board_identifier, board_title, board_sub_title, storage_directory_name)
                  VALUES
                    (:board_identifier, :board_title, :board_sub_title, :storage_directory_name)";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':board_identifier', $board_identifier);
        $stmt->bindParam(':board_title', $board_title);
        $stmt->bindParam(':board_sub_title', $board_sub_title);
        $stmt->bindParam(':storage_directory_name', $storage_directory_name);

        return $stmt->execute(); // Return true if successful
    }

    // Method to fetch the last board UID (useful for inserting new boards)
    public function getLastBoardUID() {
        $query = "SELECT MAX(board_uid) AS max_uid FROM {$this->boardTableName}";
        $stmt = $this->db->query($query);
        $board_uid = $stmt->fetchColumn();
        return $board_uid ?? 0;
    }

    // Method to get the next AUTO_INCREMENT value for a table
    public function getNextAutoIncrement($tableName) {
        try {
            // Query to get the AUTO_INCREMENT value from information_schema
            $query = "SELECT AUTO_INCREMENT 
                      FROM information_schema.TABLES 
                      WHERE TABLE_SCHEMA = :databaseName 
                      AND TABLE_NAME = :tableName";
    
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                ':databaseName' => $this->databaseName,
                ':tableName' => $tableName,
            ]);
    
            // Fetch the result
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
            if ($result && isset($result['AUTO_INCREMENT'])) {
                return (int)$result['AUTO_INCREMENT'];
            }
    
            // Return null if AUTO_INCREMENT value is not found
            return null;
        } catch (PDOException $e) {
            // Handle exceptions by logging or re-throwing
            error_log("Error fetching AUTO_INCREMENT value: " . $e->getMessage());
            return null;
        }
    }
}

// Main execution
$dbSettings = require ROOTPATH . '/databaseSettings.php';
$tableNames = require ROOTPATH . '/tables.php';
$html = new html($dbSettings);

// Anchor the install marker to the application root, NOT the (SAPI-dependent) CWD.
// A relative './.installed' could be written/checked in the wrong directory, silently
// re-enabling the unauthenticated installer.
define('INSTALLED_MARKER', ROOTPATH . '/.installed');

if (file_exists(INSTALLED_MARKER)) {
    $html->drawHeader();
    $html->drawStyle();
    $html->drawInstallNotice();
    echo "Kokonotsuba has been installed!";
    $html->drawFooter();
    exit;
}

$action = $_REQUEST['action'] ?? '';
switch ($action) {
    case 'install':
        try {
            $dsn = "{$dbSettings['DATABASE_DRIVER']}:host={$dbSettings['DATABASE_HOST']};port={$dbSettings['DATABASE_PORT']};dbname={$dbSettings['DATABASE_NAME']};charset={$dbSettings['DATABASE_CHARSET']}";
            $pdoConnection = new PDO($dsn, $dbSettings['DATABASE_USERNAME'], $dbSettings['DATABASE_PASSWORD'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            $globalConfig = getGlobalConfig();

            // The schema is built by the migration runner, so a fresh install and an upgraded
            // one go through exactly the same code. See Utilities/migrate-cli.php.
            databaseConnection::createInstance($dbSettings);
            $databaseConnection = databaseConnection::getInstance();
            $ledger = new migrationLedger($databaseConnection, $tableNames['SCHEMA_MIGRATION_TABLE']);

            $runner = new migrationRunner(
                $databaseConnection,
                $ledger,
                new schemaInspector($databaseConnection, $dbSettings['DATABASE_NAME']),
                $tableNames,
                ROOTPATH,
                Kokonotsuba\KOKO_VERSION,
                static function (string $message, string $level): void {
                    if ($level === 'migration') {
                        error_log('install: '.$message);
                    }
                }
            );

            $runner->withLock(static fn (): array => $runner->up());

            $boardTable = new boardTable($pdoConnection, $tableNames['BOARD_TABLE'], $dbSettings['DATABASE_NAME']);
            $accountTable = new accountTable($pdoConnection, $tableNames['ACCOUNT_TABLE']);

            // Refuse to provision a new admin on an already-installed instance, even if the
            // .installed marker is absent (e.g. CLI/SQL install, backup restore, wrong CWD).
            if ($accountTable->anyAccountExists()) {
                touch(INSTALLED_MARKER);
                http_response_code(403);
                exit('Installation aborted: accounts already exist. Delete install.php.');
            }

            $boardTable->createGlobalBoard(); // create global dummy board

            createBoardAndFiles($boardTable);

            $username = $_POST['admin-username'] ?? '';
            $password = $_POST['admin-password'] ?? '';
            $accountTable->addAdminAccount($username, $password, Kokonotsuba\userRole::LEV_ADMIN->value);

            
            touch(INSTALLED_MARKER);

            if(file_exists(dirname(__FILE__) . '/' .$globalConfig['STATIC_INDEX_FILE'])) {

                unlink('./'.$globalConfig['STATIC_INDEX_FILE']);
                createFileAndWriteText(dirname(__FILE__) . '/', $globalConfig['STATIC_INDEX_FILE'], '
                    <!DOCTYPE html>
                    <html lang="en">
                        <head>
                            <meta charset="UTF-8">
                            <meta http-equiv="refresh" content="url='.$globalConfig['LIVE_INDEX_FILE'].'">
                            <title>Redirecting...</title>
                        </head>
                        <body>
                            <p>If you are not redirected automatically, follow this <a href="'.$globalConfig['LIVE_INDEX_FILE'].'">link</a>.</p>
                        </body>
                    </html>
                ');
            }
            
            redirect($globalConfig['LIVE_INDEX_FILE']);
        } catch (Exception $e) {
            // Log the detail server-side; never expose stack traces / SQL / paths to the client.
            error_log('Installer error: ' . $e->getMessage());
            http_response_code(500);
            exit('Installation failed. Check the server error log for details.');
        }
        break;

    //default main
    default:
        $html->drawHeader();
        $html->drawStyle();
        $html->drawInstallNotice();
        $html->drawRequiredExtentions();
        $html->drawImportantConfigValuesPreview();
        $html->drawInstallForm();
        $html->drawFooter();
    break;
}
