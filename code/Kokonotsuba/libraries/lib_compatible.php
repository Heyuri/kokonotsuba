<?php
/**
 * Pixmicat! compatible components
 *
 * @package PMCLibrary
 * @version $Id$
 */

namespace Kokonotsuba\libraries;

use Kokonotsuba\PMCLibrary;

/**
 * Look up the matching string from the translation resource file.
 *
 * @param args Translation resource index, followed by any additional variables
 * @see LanguageLoader->getTranslation
 */
function _T(string $translationLable) {
	// Prior to PHP 5.3, func_get_args() could not be assigned directly, so its result must be stored in $args first.
	$args = func_get_args();
	return call_user_func_array(
		array(PMCLibrary::getLanguageInstance(), 'getTranslation'),
		$args);
}

/**
 * Dynamically attach additional translation resources. This function has been
 * superseded by {@link #LanguageLoader->attachLanguage}.
 *
 * @deprecated 7th.Release. Use LanguageLoader->attachLanguage instead.
 * @param callable $fcall Function that appends translation resource strings
 */
function AttachLanguage($fcall){
	$GLOBALS['language'] = array();
	call_user_func($fcall);
	PMCLibrary::getLanguageInstance()->attachLanguage($GLOBALS['language']);
}
