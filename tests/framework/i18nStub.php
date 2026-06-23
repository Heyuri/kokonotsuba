<?php

namespace Kokonotsuba\libraries;

/**
 * Test-only stub of the application's _T() translation helper.
 *
 * The real _T() (code/Kokonotsuba/libraries/lib_compatible.php) resolves a label
 * against the booted language instance. Unit tests don't boot i18n, so this echoes
 * the label back (interpolating any extra printf-style args), which is enough for
 * code under test that builds or throws translated messages.
 *
 * Defined only if the real one hasn't been loaded, so this never shadows the app.
 */
if (!function_exists('Kokonotsuba\\libraries\\_T')) {
	function _T(string $translationLabel) {
		$args = array_slice(func_get_args(), 1);
		if (!$args) {
			return $translationLabel;
		}
		// Best-effort interpolation; fall back to the raw label if the key isn't
		// actually a printf format string.
		$formatted = @vsprintf($translationLabel, $args);
		return $formatted === false ? $translationLabel : $formatted;
	}
}
