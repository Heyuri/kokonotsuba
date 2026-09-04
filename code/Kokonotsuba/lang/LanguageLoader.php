<?php
/**
 * Loads a locale's strings and looks translations up in them, falling back to another
 * locale for keys the chosen one does not carry.
 */

namespace Kokonotsuba\lang;

use InvalidArgumentException;

class LanguageLoader {
	private $locale;
	private $language;
	private $languageFallback;
	private $hasFallback;

	private function __construct($locale, array $language) {
		$this->locale = $locale;
		$this->language = $language;
	}

	/**
	 * Get the shared loader, built from PIXMICAT_LANGUAGE on first call.
	 *
	 * @throws InvalidArgumentException If the configured locale has no file
	 */
	public static function getInstance() {
		static $inst = null;
		$globalConfig = getGlobalConfig();
		if ($inst == null) {
			$locale = $globalConfig['PIXMICAT_LANGUAGE'];
			$langFile = __DIR__ . "/{$locale}.php";
			if (file_exists($langFile)) {
				require $langFile;
			} else {
				throw new InvalidArgumentException(
					sprintf('Assigned locale: %s not found.', $locale)
				);
			}
			$inst = new LanguageLoader($locale, $language);
			$inst->setFallback('en_US');
		}
		return $inst;
	}

	/**
	 * Set the locale consulted for keys the current one is missing.
	 *
	 * @param string $localeFallback
	 */
	public function setFallback($localeFallback = 'en_US') {
		if ($localeFallback != $this->getLocale()) {
			require getBackendCodeDir()."lang/{$localeFallback}.php";
			$this->hasFallback = true;
			$this->languageFallback = $language;
		} else {
			// nothing to fall back to when it is the locale we are already using
			$this->hasFallback = false;
		}
	}

	/**
	 * The locale currently in use.
	 *
	 * @return string Locale identifier, e.g. "en_US"
	 */
	public function getLocale() {
		return $this->locale;
	}

	/**
	 * All loaded strings for the current locale.
	 *
	 * @return array
	 */
	public function getLanguage() {
		return $this->language;
	}

	/**
	 * Look a key up, trying the fallback locale before giving up and returning the key.
	 *
	 * @param  string $index Translation key
	 * @return string
	 */
	private function getTranslationBody($index) {
		$str = $index;
		if (array_key_exists($index, $this->language)) {
			$str = $this->language[$index];
		} else if ($this->hasFallback && array_key_exists($index, $this->languageFallback)) {
			$str = $this->languageFallback[$index];
		}
		return $str;
	}

	/**
	 * Translate a key and run the result through sprintf with any further arguments.
	 *
	 * @param string arg1 Translation key
	 * @param mixed  arg2 Values to substitute
	 * @return string
	 */
	public function getTranslation(/*args[]*/) {
		if (!func_num_args()) {
			return '';
		}
		$argList = func_get_args();
		$argList[0] = $this->getTranslationBody($argList[0]);
		return call_user_func_array('sprintf', $argList);
	}

	/**
	 * Merge in extra strings. Keys already loaded keep their existing value.
	 *
	 * @param array $language
	 */
	public function attachLanguage(array $language) {
		$this->language = $this->language + $language;
	}
}
