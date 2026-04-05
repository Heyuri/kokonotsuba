<?php

namespace Kokonotsuba\libraries;

use Kokonotsuba\account\staffAccountFromSession;
use Kokonotsuba\account\staffAccount;
use Kokonotsuba\account\accountRepository;
use Kokonotsuba\log_in\loginSessionHandler;
use Kokonotsuba\userRole;

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