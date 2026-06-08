<?php
/**
 * Pixmicat! Language module loader
 *
 * @package PMCLibrary
 * @version $Id$
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
	 * Get the singleton instance of the language object.
	 *
	 * @return LanguageLoader Language object
	 * @throws InvalidArgumentException If the configured language cannot be found
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
	 * Set the fallback locale.
	 *
	 * @param string $localeFallback Fallback locale
	 */
	public function setFallback($localeFallback = 'en_US') {
		if ($localeFallback != $this->getLocale()) {
			require getBackendCodeDir()."lang/{$localeFallback}.php";
			$this->hasFallback = true;
			$this->languageFallback = $language;
		} else {
			// Fallback is invalid (same as current locale)
			$this->hasFallback = false;
		}
	}

	/**
	 * Get the current locale setting.
	 *
	 * @see PIXMICAT_LANGUAGE
	 * @return string Locale identifier string
	 */
	public function getLocale() {
		return $this->locale;
	}

	/**
	 * Get the array of translated resource strings.
	 *
	 * @return array Array of translation strings
	 */
	public function getLanguage() {
		return $this->language;
	}

	/**
	 * Look up the matching text from the array of translation resource strings.
	 *
	 * @param  string $index Translation resource index
	 * @return string        Corresponding translated text
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
	 * Get the translation for the specified item and perform variable substitution.
	 *
	 * @param string arg1 Translation resource index string
	 * @param mixed  arg2 Variables to substitute
	 * @return string The translated string
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
	 * Append additional translation resource strings.
	 *
	 * @param  array  $language Array of translation resource strings
	 */
	public function attachLanguage(array $language) {
		$this->language = $this->language + $language;
	}
}
