<?php
/**
 * Pixmicat! Library Singleton Factory
 *
 * 集中函式庫以方便呼叫，並可回傳單例物件。
 *
 * @package PMCLibrary
 * @version $Id$
 * @since 7th.Release
 */

class PMCLibrary {
	private static $instFileIO;

	/**
	 * 取得 FileIO 函式庫物件
	 *
	 * @return IFileIO FileIO 函式庫物件
	 */
	public static function getFileIOInstance() {
		if(!self::$instFileIO) die("Tried to get FileIO instance on null.");
		return self::$instFileIO;
	}
	
	public static function createFileIOInstance($board) {
		if (self::$instFileIO == null) {
			$config = $board->loadBoardConfig();
			$fileIoExactClass = 'FileIO'.$config['FILEIO_BACKEND'];
			$imgDir = $config['IMG_DIR'];
			$thumbDir = $config['THUMB_DIR'];
			if ($config['USE_CDN']) {
				$imgDir = $config['CDN_DIR'].$imgDir;
				$thumbDir = $config['CDN_DIR'].$thumbDir;
			}
			self::$instFileIO = new $fileIoExactClass(unserialize($config['FILEIO_PARAMETER']),
				array( // FileIO 環境常數
					'IFS.PATH' => __DIR__.'/fileio/ifs.php',
					'IFS.LOG' => $board->getBoardStoragePath().$config['FILEIO_INDEXLOG'],
					'IMG' => $imgDir,
					'THUMB' => $thumbDir
				)
			);
		}
	}
	
	/**
	 * 取得 Logger 函式庫物件
	 *
	 * @param string $name 識別名稱
	 * @return ILogger Logger 函式庫物件
	 */
	public static function getLoggerInstance($logfile, $name = 'Global') {
		static $instLogger = array();
		if (!array_key_exists($name, $instLogger)) {
			$instLogger[$name] = new SimpleLogger($logfile, $name);
		}
		return $instLogger[$name];
	}

	/**
	 * 取得語言函式庫物件
	 *
	 * @return LanguageLoader Language 函式庫物件
	 */
	public static function getLanguageInstance() {
		static $instLanguage = null;
		if ($instLanguage == null) {
			$instLanguage = LanguageLoader::getInstance();
		}
		return $instLanguage;
	}

}
