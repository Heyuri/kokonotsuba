<?php
/*
* Helper html functions for Kokonotsuba!
* General-use helper functions to do with html output and string manipulation
*/

namespace Kokonotsuba\libraries\html;

use function Puchiko\strings\sanitizeStr;

/**
 * Adds hidden input fields for each GET parameter at the top of the provided form HTML.
 *
 * @param string $formHtml  The original HTML form markup.
 * @param array $getValues  An associative array of GET parameters to inject as hidden inputs.
 * @return string           The modified form HTML with hidden inputs included.
 */
function addHiddenGetParamsToForm(string $formHtml, array $getValues): string {
	$hiddenInputs = '';

	foreach ($getValues as $name => $value) {
		$nameEscaped = sanitizeStr($name);
		$valueEscaped = sanitizeStr($value);
		$hiddenInputs .= "<input type=\"hidden\" name=\"{$nameEscaped}\" value=\"{$valueEscaped}\">\n";
	}

	// Inject the hidden inputs right after the opening <form> tag
	return preg_replace('/<form[^>]*>/i', '$0' . "\n" . $hiddenInputs, $formHtml);
}

/* Add quote class to quoted text */
function quote_unkfunc(string $comment): string {
	$comment = preg_replace('/(^|<br\s*\/?>)((?:&gt;|＞).*?)(?=<br\s*\/?>|$)/ui', '$1<span class="unkfunc">$2</span>', $comment);
	$comment = preg_replace('/(^|<br\s*\/?>)((?:&lt;).*?)(?=<br\s*\/?>|$)/ui', '$1<span class="unkfunc2">$2</span>', $comment);
	return $comment;
}
