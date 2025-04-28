<?php
/*
* Board creation object for Kokonotsuba!
* Handles board creationss
*/


class boardCreator {
    private $globalHTML;
    private $config;
    private $boardIO;
    private $boardPathCachingIO;

    public function __construct($globalHTML, $config, $boardIO, $boardPathCachingIO) {
        $this->globalHTML = $globalHTML;
        $this->config = $config;
        $this->boardIO = $boardIO;
        $this->boardPathCachingIO = $boardPathCachingIO;
    }

    public function createNewBoard(string $boardTitle, string $boardSubTitle, string $boardIdentifier, bool $boardListed, string $boardPath): board|null {
        $fullBoardPath = $boardPath . $boardIdentifier . '/';
        $mockConfig = getTemplateConfigArray();
        $backendDirectory = getBackendDir();
        $cdnDir = $this->config['CDN_DIR'] . $boardIdentifier . '/';

        $createdPaths = [];

        try {
            // Create full board path
            $createdPaths[] = $this->createDirectory($fullBoardPath);

            // Create image storage paths
            $imgDir = $this->config['USE_CDN'] ? $cdnDir . $mockConfig['IMG_DIR'] : $fullBoardPath . $mockConfig['IMG_DIR'];
            $thumbDir = $this->config['USE_CDN'] ? $cdnDir . $mockConfig['THUMB_DIR'] : $fullBoardPath . $mockConfig['THUMB_DIR'];
            $createdPaths[] = $this->createDirectory($imgDir);
            $createdPaths[] = $this->createDirectory($thumbDir);

            // Create koko.php in the directory
            $requireString = "\"$backendDirectory{$this->config['PHP_SELF']}\"";
            $this->createFileAndWriteText($fullBoardPath, $mockConfig['PHP_SELF'], "<?php require_once {$requireString}; ?>");

            // Create board-storage directory
            $boardStorageDirectoryName = 'storage-' . $this->boardIO->getNextBoardUID();
            $dataDir = getBoardStoragesDir() . $boardStorageDirectoryName;
            $createdPaths[] = $this->createDirectory($dataDir);

            // Generate config file
            $boardConfigName = generateNewBoardConfigFile();

            // Add board to database
            $this->boardIO->addNewBoard($boardIdentifier, $boardTitle, $boardSubTitle, $boardListed, $boardConfigName, $boardStorageDirectoryName);

            // Initialize and create boardUID.ini
            $newBoardUID = $this->boardIO->getLastBoardUID();
            $this->createFileAndWriteText($fullBoardPath, 'boardUID.ini', "board_uid = $newBoardUID");

            // Add the board's physical path to the path cache table
            $this->boardPathCachingIO->addNewCachedBoardPath($newBoardUID, $fullBoardPath);

            // Rebuild the new board
            $newBoardFromDatabase = $this->boardIO->getBoardByUID($newBoardUID);
            $newBoardFromDatabase->rebuildBoard();

            return $newBoardFromDatabase;
        } catch (Exception $e) {
            $this->rollbackCreatedPaths($createdPaths);
            $this->globalHTML->error($e->getMessage());
        }
        return null;
    }

    private function createDirectory(string $path) {
        return createDirectoryWithErrorHandle($path, $this->globalHTML);
    }

    private function createFileAndWriteText(string $directory, string $fileName, string $content) {
        createFileAndWriteText($directory, $fileName, $content);
    }

    private function rollbackCreatedPaths(array $createdPaths) {
        rollbackCreatedPaths($createdPaths);
    }
}
