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

require $config['ROOTPATH'].'lib/interfaces.php';
require $config['ROOTPATH'].'lib/lib_simplelogger.php';
require $config['ROOTPATH'].'lib/lib_loggerinterceptor.php';

class PMCLibrary {
	/**
	 * 取得 PIO 函式庫物件
	 *
	 * @return IPIO PIO 函式庫物件
	 */
	public static function getPIOInstance() {
		global $PIOEnv, $config;
		static $instPIO = null;
		if ($instPIO == null) {
			require $config['ROOTPATH'].'lib/lib_pio.php';
			$pioExactClass = 'PIO'.$config['DATABASE_DRIVER'];
			$instPIO = new LoggerInjector(
				new $pioExactClass($config['TITLE'], $PIOEnv),
				new LoggerInterceptor(PMCLibrary::getLoggerInstance($pioExactClass))
			);
		}
		return $instPIO;
	}

	/**
	 * 取得 PTE 函式庫物件
	 *
	 * @return PTELibrary PTE 函式庫物件
	 */
	public static function getPTEInstance() {
		global $config;
		static $instPTE = null;
		if ($instPTE == null) {
			require $config['ROOTPATH'].'lib/lib_pte.php';
			if (isset($_GET["res"]))
				$instPTE = new PTELibrary($config['REPLY_TEMPLATE_FILE']);
			else
				$instPTE = new PTELibrary($config['TEMPLATE_FILE']);
		}
		return $instPTE;
	}

	/**
	 * 取得 PMS 函式庫物件
	 *
	 * @return PMS PMS 函式庫物件
	 */
	public static function getPMSInstance() {
		global $config;
		static $instPMS = null;
		if ($instPMS == null) {
			require $config['ROOTPATH'].'lib/lib_pms.php';
			$instPMS = new PMS(array( // PMS 環境常數
				'MODULE.PATH' => $config['ROOTPATH'].'module/',
				'MODULE.PAGE' => $config['PHP_SELF'].'?mode=module&amp;load=',
				'MODULE.LOADLIST' => $config['ModuleList'],
			));
		}
		return $instPMS;
	}

	/**
	 * 取得 FileIO 函式庫物件
	 *
	 * @return IFileIO FileIO 函式庫物件
	 */
	public static function getFileIOInstance() {
		global $config;
		static $instFileIO = null;
		if ($instFileIO == null) {
			require $config['ROOTPATH'].'lib/lib_fileio.php';
			$fileIoExactClass = 'FileIO'.$config['FILEIO_BACKEND'];
			$imgDir = $config['IMG_DIR'];
			$thumbDir = $config['THUMB_DIR'];
			if (!empty($config['CDN_DIR'])) {
				$imgDir = $config['CDN_DIR'].$imgDir;
				$thumbDir = $config['CDN_DIR'].$thumbDir;
			}
			$instFileIO = new $fileIoExactClass(
                                unserialize($config['FILEIO_PARAMETER']),
				array( // FileIO 環境常數
					'IFS.PATH' => $config['ROOTPATH'].'lib/fileio/ifs.php',
					'IFS.LOG' => $config['STORAGE_PATH'].$config['FILEIO_INDEXLOG'],
					'IMG' => $imgDir,
					'THUMB' => $thumbDir
				)
			);
		}
		return $instFileIO;
	}

	/**
	 * 取得 Logger 函式庫物件
	 *
	 * @param string $name 識別名稱
	 * @return ILogger Logger 函式庫物件
	 */
	public static function getLoggerInstance($name = 'Global') {
		global $config;
		static $instLogger = array();
		if (!array_key_exists($name, $instLogger)) {
			$instLogger[$name] = new SimpleLogger($name, $config['STORAGE_PATH'] .'error.log');
		}
		return $instLogger[$name];
	}

	/**
	 * 取得語言函式庫物件
	 *
	 * @return LanguageLoader Language 函式庫物件
	 */
	public static function getLanguageInstance() {
		global $config;
		static $instLanguage = null;
		if ($instLanguage == null) {
			require $config['ROOTPATH'].'lib/lib_language.php';
			$instLanguage = LanguageLoader::getInstance();
		}
		return $instLanguage;
	}
	
	/**
	* Get account instance
	*/
	public static function getAccountIOInstance() {
		global $config;
		static $instAccount = null;
		if ($instAccount == null) {
			require $config['ROOTPATH'].'lib/accountIO.php';
			$instAccount = new AccountIO();
		}
		return $instAccount;
	}

}
