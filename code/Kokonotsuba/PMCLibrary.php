<?php
/**
 * Pixmicat! Library Singleton Factory
 *
 * Centralized library access for convenient calls; returns singleton instances.
 *
 * @package PMCLibrary
 * @version $Id$
 * @since 7th.Release
 */

namespace Kokonotsuba;

use Kokonotsuba\logger\SimpleLogger;
use Kokonotsuba\lang\LanguageLoader;

class PMCLibrary {
	/**
	 * Get a Logger library instance.
	 *
	 * @param string $name Identifier name
	 * @return ILogger Logger library instance
	 */
	public static function getLoggerInstance($logfile, $name = 'Global') {
		static $instLogger = array();
		if (!array_key_exists($name, $instLogger)) {
			$instLogger[$name] = new SimpleLogger($logfile, $name);
		}
		return $instLogger[$name];
	}

	/**
	 * Get the Language library instance.
	 *
	 * @return LanguageLoader Language library instance
	 */
	public static function getLanguageInstance() {
		static $instLanguage = null;
		if ($instLanguage == null) {
			$instLanguage = LanguageLoader::getInstance();
		}
		return $instLanguage;
	}

}
