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
        return $board->getBoardUploadedFilesURL();
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

    protected function getDirectoryTotalSize($dirIterator) {
		$dirSize = 0;
		foreach (new RecursiveIteratorIterator($dirIterator) as $file) {
			$dirSize += $file->getSize();
		}
		return $dirSize;
	}
    
    //get board storage size in KB
    public function getCurrentStorageSize($board) {
		$config = $board->loadBoardConfig();

        if(!file_exists($board->getBoardUploadedFilesDirectory().$config['THUMB_DIR'])) throw new Exception("Thumb directory not found or created ".$board->getBoardUploadedFilesDirectory().$config['THUMB_DIR']);
        if(!file_exists($board->getBoardUploadedFilesDirectory().$config['IMG_DIR'])) throw new Exception("Image directory not found or created ".$board->getBoardUploadedFilesDirectory().$config['IMG_DIR']);
		
        $totalSize = 0;
		$dirs = array(
			new RecursiveDirectoryIterator($board->getBoardUploadedFilesDirectory().$config['IMG_DIR']),
			new RecursiveDirectoryIterator($board->getBoardUploadedFilesDirectory().$config['THUMB_DIR'])
		);

		foreach ($dirs as $dir) {
			$totalSize += $this->getDirectoryTotalSize($dir);
		}
		return $totalSize;
    }

}
