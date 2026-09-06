<?php
/**
 * Translation shorthand used throughout the codebase.
 */

namespace Kokonotsuba\libraries;

use Kokonotsuba\kokoLibrary;

/**
 * Look up a translated string by key.
 *
 * @param string $translationLable Translation key, followed by any sprintf arguments
 * @see LanguageLoader::getTranslation
 */
function _T(string $translationLable) {
	$args = func_get_args();
	return call_user_func_array(
		array(kokoLibrary::getLanguageInstance(), 'getTranslation'),
		$args);
}

/**
 * Attach extra translation strings at runtime.
 *
 * @deprecated Call LanguageLoader::attachLanguage directly instead.
 * @param callable $fcall Function that fills $GLOBALS['language'] with the extra strings
 */
function AttachLanguage($fcall){
	$GLOBALS['language'] = array();
	call_user_func($fcall);
	kokoLibrary::getLanguageInstance()->attachLanguage($GLOBALS['language']);
}
