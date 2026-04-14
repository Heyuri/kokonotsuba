<?php

namespace Kokonotsuba\libraries;

use Kokonotsuba\account\staffAccountFromSession;
use Kokonotsuba\account\staffAccount;
use Kokonotsuba\account\accountRepository;
use Kokonotsuba\log_in\loginSessionHandler;
use Kokonotsuba\userRole;

use function Puchiko\strings\sanitizeStr;

//This file contains functions for koko management mode and related features
/**
* Check if the account session role is at least a janitor
*/
function isActiveStaffSession(): bool {
	$staffSession = new staffAccountFromSession;
	$roleLevel = $staffSession->getRoleLevel();
	
	return $roleLevel->isStaff();
}

/**
 * Check if account session has a valid user role
 */

function isLoggedIn(): bool {
	$staffSession = new staffAccountFromSession;
	$roleLevel = $staffSession->getRoleLevel();
	
	return $roleLevel->isAtLeast(userRole::LEV_USER);
}

function getRoleLevelFromSession(): userRole {
	$staffSession = new staffAccountFromSession;
	$roleLevel = $staffSession->getRoleLevel();

	return $roleLevel;
}

function getUsernameFromSession(): ?string {
	$staffSession = new staffAccountFromSession;
	$username = $staffSession->getUsername();

	return $username;
}

function getIdFromSession(): ?int {
	$staffSession = new staffAccountFromSession;
	$accountUid = $staffSession->getUID();

	return $accountUid;
}

function updateAccountSession(accountRepository $accountRepository, loginSessionHandler $loginSessionHandler): void {
	// don't bother if the user isn't logged in
	if(!isLoggedIn()) {
		return;
	}

	$staffSession = new staffAccountFromSession;

	$accountId = $staffSession->getUID();

	$account = $accountRepository->getAccountByID($accountId);

	// just destroy the session if the account no longer exists
	if(!$account || !($account instanceof staffAccount)) {
		$loginSessionHandler->logout();
	}

	// now update the session
	$loginSessionHandler->updateSessionData($account);
}

function generateModerateButton(
	string $buttonUrl,  
	string $label, 
	string $title, 
	string $class,
	bool $isNoScript = false,
): string {
	// generate the html for a moderate button with the given url, label, title and class
	$buttonSpan = '<span class="adminFunctions ' . htmlspecialchars($class) . '">[<a href="' . htmlspecialchars($buttonUrl) . '" title="' . htmlspecialchars($title) . '">' . htmlspecialchars($label) . '</a>]</span>';

	// if the button is meant to be used in a no script context, wrap it in a noscript tag
	if($isNoScript) {
		$buttonSpan = '<noscript>' . $buttonSpan . '</noscript>';
	}

	return $buttonSpan;
}

/**
 * Get or create a CSRF token stored in the session.
 */
function getOrCreateCsrfToken(): string {
	if (empty($_SESSION['csrf_token'])) {
		$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
	}
	return $_SESSION['csrf_token'];
}

/**
 * Validate a submitted CSRF token against the session token.
 */
function validateCsrfToken(string $submittedToken): bool {
	$sessionToken = getOrCreateCsrfToken();
	return hash_equals($sessionToken, $submittedToken);
}

/**
 * Require that the request is POST and has a valid CSRF token.
 * Throws BoardException on failure.
 */
function requirePostWithCsrf(\Kokonotsuba\request\request $request): void {
	if (!$request->isPost()) {
		throw new \Kokonotsuba\error\BoardException('ERROR: Invalid request method.');
	}
	$submittedToken = $request->getParameter('csrf_token', 'POST', '');
	if (!validateCsrfToken($submittedToken)) {
		throw new \Kokonotsuba\error\BoardException('ERROR: CSRF validation failed.');
	}
}

/**
 * Generate a hidden <input> element containing the CSRF token.
 * Used inside <form> elements for POST submission.
 */
function getCsrfHiddenInput(): string {
	return '<input type="hidden" name="csrf_token" value="' . sanitizeStr(getOrCreateCsrfToken()) . '">';
}

/**
 * Generate a <meta> tag for the CSRF token (for JS to read).
 * Only outputs once per request.
 */
function getCsrfMetaTag(): string {
	static $added = false;
	if ($added) return '';
	$added = true;
	$token = sanitizeStr(getOrCreateCsrfToken());
	return '<meta name="csrf-token" content="' . $token . '">';
}

/**
 * Generate a POST form that looks like a moderate button [label].
 * Uses the buttonLink CSS class to make the submit button resemble a link.
 *
 * When $isNoScript is true the output lives inside delform, so a nested
 * <form> would be invalid HTML.  Instead we emit a <button> with
 * formaction/formmethod attributes that override the parent form.
 * The parent delform must contain a csrf_token hidden input.
 */
function generateModerateForm(
	string $buttonUrl,
	string $label,
	string $title,
	string $class,
	bool $noScript = false,
	bool $useFormAction = true
): string {
	if ($useFormAction) {
		// Inside delform — use formaction to avoid nested <form> tags.
		// The CSRF token is already present as a hidden input in delform.
		$html = '<span class="adminFunctions ' . htmlspecialchars($class) . '">'
			. '[<button type="submit" class="buttonLink"'
			. ' formaction="' . htmlspecialchars($buttonUrl) . '"'
			. ' formmethod="POST"'
			. ' title="' . htmlspecialchars($title) . '">'
			. htmlspecialchars($label)
			. '</button>]'
			. '</span>';

		if($noScript) {
			return '<noscript>' . $html . '</noscript>';
		}
		else {
			return $html;
		}
	}

	// Standalone context (admin management pages) — wrap in its own <form>.
	$csrfToken = sanitizeStr(getOrCreateCsrfToken());

	return '<span class="adminFunctions ' . htmlspecialchars($class) . '">'
		. '<form method="POST" action="' . htmlspecialchars($buttonUrl) . '" style="display:inline">'
		. '<input type="hidden" name="csrf_token" value="' . $csrfToken . '">'
		. '[<button type="submit" class="buttonLink" title="' . htmlspecialchars($title) . '">' . htmlspecialchars($label) . '</button>]'
		. '</form>'
		. '</span>';
}

/**
 * Generate a POST button for an attachment action.
 * Always rendered inside delform, so uses formaction/formmethod
 * to avoid nested <form> tags.  The parent delform must contain
 * a csrf_token hidden input.
 */
function generateAttachmentForm(
	string $url,
	string $functionClass,
	string $title,
	string $label,
): string {
	return ' <span class="adminFunctions admin' . $functionClass . 'Function attachmentButton">'
		. '[<button type="submit" class="buttonLink"'
		. ' formaction="' . htmlspecialchars($url) . '"'
		. ' formmethod="POST"'
		. ' title="' . htmlspecialchars($title) . '">'
		. $label
		. '</button>]'
		. '</span>';
}

/**
 * Generate a hex color code based on a moderator ID using the golden angle method for good distribution of colors
 * 
 * This function takes an integer ID and converts it to a unique color by calculating a hue value using the golden angle, then converting that hue to an RGB color with fixed saturation and lightness for good visibility
 * 
 * @param int $id The moderator ID to generate the color for
 * @return string A hex color code in the format #RRGGBB that is unique to the given ID
 */
function modIdToColorHex(int $id): string {
    // Ensure $id is an integer
    $id = intval($id);

    // Golden angle in degrees
    $golden_angle = 137.508;

    // Calculate hue (0-360)
    $hue = fmod($id * $golden_angle, 360);

    // Fixed saturation and lightness for good contrast
    $saturation = 0.55; // 0..1
    $lightness = 0.4;  // 0..1

    // Convert HSL to RGB
    $c = (1 - abs(2 * $lightness - 1)) * $saturation;
    $x = $c * (1 - abs(fmod($hue / 60, 2) - 1));
    $m = $lightness - $c / 2;

    if ($hue < 60) {
        $r = $c; $g = $x; $b = 0;
    } elseif ($hue < 120) {
        $r = $x; $g = $c; $b = 0;
    } elseif ($hue < 180) {
        $r = 0; $g = $c; $b = $x;
    } elseif ($hue < 240) {
        $r = 0; $g = $x; $b = $c;
    } elseif ($hue < 300) {
        $r = $x; $g = 0; $b = $c;
    } else {
        $r = $c; $g = 0; $b = $x;
    }

    // Convert to 0-255 and add m
    $r = intval(round(255 * ($r + $m)));
    $g = intval(round(255 * ($g + $m)));
    $b = intval(round(255 * ($b + $m)));

    // Return as hex string
    return sprintf("#%02X%02X%02X", $r, $g, $b);
}