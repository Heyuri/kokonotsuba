<?php

/*
  FileIO - Pixmicat! File I/O
  FileIO Kernel Switcher
 */

/**
 * 抽象 FileIO，預先實作好本地圖檔相關方法。
 */
abstract class AbstractFileIO implements IFileIO {
    /** @public ILogger */
    public $LOG;
    

    public function __construct() {
    	$globalconfig = getGlobalConfig();
        $this->LOG = PMCLibrary::getLoggerInstance($globalconfig['ERROR_HANDLER_FILE'], 'AbstractFileIO');
    }

    private function getAbsoluteUrl($board) {
        return $board->getBoardCdnUrl();
    }
    protected function getImageLocalURL($imgname, $board) {
        $config = $board->loadBoardConfig();
        return $this->getAbsoluteUrl($board) .
                (strpos($imgname, 's.') !== false ? basename($config['THUMB_DIR']) : basename($config['IMG_DIR'])).'/'.
                $imgname;
    }

    protected function remoteImageExists($img) {
        try {
            $result = file_get_contents($img, false, null, 0, 1);
        } catch (Exception $ignored) {
            $this->LOG->error("remoteImageExists -> file_get_contents failed");
            return false;
        }

        return ($result !== false);
    }

    //get board storage size in KB
    public function getCurrentStorageSize($board) {
		$config = $board->loadBoardConfig();
        $globalHTML = new globalHTML($board);

        if(!file_exists($board->getBoardCdnDir().$config['THUMB_DIR'])) $globalHTML->error("Thumb directory not found or created ".$board->getBoardCdnDir().$config['THUMB_DIR']);
        if(!file_exists($board->getBoardCdnDir().$config['IMG_DIR'])) $globalHTML->error("Image directory not found or created ".$board->getBoardCdnDir().$config['IMG_DIR']);
		
        $totalSize = 0;
		$dirs = array(
			new RecursiveDirectoryIterator($board->getBoardCdnDir().$config['IMG_DIR']),
			new RecursiveDirectoryIterator($board->getBoardCdnDir().$config['THUMB_DIR'])
		);

		foreach ($dirs as $dir) {
			$totalSize += $this->getDirectoryTotalSize($dir);
		}
		return $totalSize;
    }
}

/**
 * 抽象 FileIO + IFS。
 */
abstract class AbstractIfsFileIO extends AbstractFileIO {
    /** @var IndexFS */
    protected $IFS;

    public function __construct($parameter, $ENV) {
        parent::__construct();

        require($ENV['IFS.PATH']);
        $this->IFS = new IndexFS($ENV['IFS.LOG']);
        $this->IFS->openIndex();
        register_shutdown_function(array($this, 'saveIndex'));
    }

    /**
     * 儲存索引檔
     */
    public function saveIndex() {
        $this->IFS->saveIndex();
    }

    public function imageExists($imgname, $board) {
        return $this->IFS->beRecord($imgname);
    }

    public function getImageFilesize($imgname, $board) {
        $rc = $this->IFS->getRecord($imgname);
        if (!is_null($rc)) {
            return $rc['imgSize'];
        }
        return 0;
    }

    public function resolveThumbName($thumbPattern, $board) {
        return $this->IFS->findThumbName($thumbPattern, $board);
    }

    protected function getCurrentStorageSizeNoCache($board) {
        return $this->IFS->getCurrentStorageSize($board);
    }
}

// 引入實作
require __DIR__.'/fileio/fileio.local.php';
