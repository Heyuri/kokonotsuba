<?php

namespace Kokonotsuba;

use Kokonotsuba\lang\LanguageLoader;
use Kokonotsuba\logger\SimpleLogger;

/**
 * Access point for the two libraries that predate the DI container. Both are shared for
 * the life of the request rather than rebuilt per call.
 */
class kokoLibrary {
	/**
	 * Get a logger, one per name.
	 *
	 * @param string $logfile Path the logger appends to
	 * @param string $name    Identifier the entries are tagged with
	 */
	public static function getLoggerInstance($logfile, $name = 'Global') {
		static $instLogger = array();

		if (!array_key_exists($name, $instLogger)) {
			$instLogger[$name] = new SimpleLogger($logfile, $name);
		}

		return $instLogger[$name];
	}

	/**
	 * Get the language loader.
	 *
	 * @return LanguageLoader
	 */
	public static function getLanguageInstance() {
		static $instLanguage = null;

		if ($instLanguage == null) {
			$instLanguage = LanguageLoader::getInstance();
		}

		return $instLanguage;
	}

}
