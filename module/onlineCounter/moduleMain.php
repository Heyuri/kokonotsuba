<?php

namespace Kokonotsuba\Modules\onlineCounter;

use IPAddress;
use Kokonotsuba\ModuleClasses\abstractModuleMain;

class moduleMain extends abstractModuleMain {
	private $modulePageUrl, $usercounter, $timeout, $staticUrl;

	public function getName(): string {
		return 'Online user count module';
	}

	public function getVersion(): string  {
		return 'Kokonotsuba 2024';
	}

	public function initialize(): void {
		$this->timeout = $this->getConfig('ModuleSettings.USER_COUNT_TIMEOUT');
		
		$this->staticUrl = $this->getConfig('STATIC_URL');
		
		$this->usercounter = $this->moduleContext->board->getBoardStoragePath().$this->getConfig('ModuleSettings.USER_COUNT_DAT_FILE');
		
		$this->modulePageUrl = $this->getModulePageURL();

		$this->moduleContext->moduleEngine->addListener('PostInfo', function(string &$hookPostInfoHtml) {
			$this->onRenderPostInfo($hookPostInfoHtml);
		});

		$this->moduleContext->moduleEngine->addListener('ModuleHeader', function(string &$moduleHeader) {
			$this->onGenerateModuleHeader($moduleHeader);
		});
	}

	/**
	 * Tracks active users based on their IP addresses and a timeout period.
	 * The method logs each user's activity, removes inactive ones, and returns the count of active users.
	 */
	private function getUserCount() {
		// Ensure the counter file exists
		touch($this->usercounter);

		// Open file for both reading and writing; "c+" creates it if missing
		$fp = fopen($this->usercounter, "c+");
		if (!$fp) return 0;

		// Lock file to prevent concurrent access (important under high traffic)
		flock($fp, LOCK_EX);

		// Read all lines from the counter file (ignore empty lines)
		$usr_arr = file($this->usercounter, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

		$currentTime = time();

		// get IP object
		$addr = new IPAddress;
		
		// get addr string
		$addrString = $addr->__toString();

		// Clean and rebuild the list of active users
		$activeUsers = $this->filterActiveUsers($usr_arr, $currentTime, $addrString);

		// Add or update the current user's timestamp
		$activeUsers[$addrString] = $currentTime;

		// Rewrite the entire file with the updated list
		$this->writeActiveUsers($activeUsers);

		// Release file lock and close handle
		flock($fp, LOCK_UN);
		fclose($fp);

		// Return total active user count
		return count($activeUsers);
	}

	/**
	 * Filters the list of user entries, keeping only those still active within the timeout.
	 * 
	 * @param array $usr_arr Lines read from the user counter file.
	 * @param int $currentTime Current timestamp in minutes.
	 * @param string $currentAddr IP address of the current user.
	 * @return array Array of active users in [ip => timestamp] format.
	 */
	private function filterActiveUsers($usr_arr, $currentTime, $currentAddr) {
		$activeUsers = [];

		foreach ($usr_arr as $line) {
			$line = trim($line);
			if (empty($line)) continue;

			// Safely split the line into IP and timestamp
			list($ip_addr, $stamp) = explode("|", $line) + [null, null];

			// Validate and retain only active, non-duplicate IPs
			if (is_numeric($stamp) && ($currentTime - $stamp) < ($this->timeout * 60) && $ip_addr != $currentAddr) {
				$activeUsers[$ip_addr] = $stamp;
			}
		}

		return $activeUsers;
	}

	/**
	 * Safely writes the current list of active users to the counter file.
	 *
	 * This function performs an atomic write operation to prevent file corruption
	 * or data loss during concurrent access or unexpected process termination.
	 * It first writes the updated user data to a temporary file, then replaces
	 * the original counter file using a rename operation — which is atomic
	 * on most file systems.
	 *
	 * Each user entry is written in the format "ip|timestamp", one per line.
	 * The timestamp should represent the user's last active time in seconds.
	 *
	 * @param array $activeUsers Associative array of active users in [ip => timestamp] format.
	 *                           Keys are IP addresses, and values are integer timestamps.
	 *
	 * @return void
	 */
	private function writeActiveUsers(array $activeUsers): void {
		// Create a temporary file for atomic replacement
		$tempFile = $this->usercounter . '.tmp';
		$fpTmp = fopen($tempFile, 'w');

		// Write all active users to the temporary file
		foreach ($activeUsers as $ip => $stamp) {
			fputs($fpTmp, "$ip|$stamp\n");
		}

		// Close temporary file handle
		fclose($fpTmp);

		// Atomically replace the original file with the new one
		rename($tempFile, $this->usercounter);
	}
	
	private function onRenderPostInfo(string &$hookPostInfoHtml): void {
		$userCount = $this->getUserCount();

		// Handle pluralization
		$userWord = $userCount === 1 ? 'user' : 'users';
		$minuteWord = $this->timeout === 1 ? 'minute' : 'minutes';

		$userCounterHTML = '
			<li id="counterListItemJS" class="hidden">
				<div data-timeout="'.$this->timeout.'" data-modurl="'.$this->modulePageUrl.'&usercountjson" id="usercounter">
					<span id="countnumber">' . $userCount . '</span> unique ' . $userWord . ' in the last ' . $this->timeout . ' ' . $minuteWord . ' (including lurkers)
				</div>
			</li>';

		$noScriptHtml = '
			<li id="counterListItemNoJS" class="">
				<noscript>
					<iframe id="counterIframe" title="User counter" src="'.$this->modulePageUrl.'"></iframe>
				</noscript>
			</li>';

		$hookPostInfoHtml .= $noScriptHtml . $userCounterHTML;
	}
	
	private function onGenerateModuleHeader(string &$moduleHeader): void {
		// generate online counter js script tag
		$jsHtml = $this->generateScriptHeader('onlineCounterUpdater.js', true);

		// append to module header
		$moduleHeader .= $jsHtml;
	}

	/**
	 * Outputs the current user count as a JSON response.
	 * 
	 * This helper ensures consistent JSON formatting and prevents any
	 * further processing or template rendering after sending the response.
	 */
	private function outputUserCountJson() {
		// Get the current user count (integer value)
		$userCount = $this->getUserCount();

		// Build the JSON response structure
		$response = [
			'success' => true,
			'active_users' => $userCount,
			'timestamp' => time() // optional metadata for debugging or caching
		];

		// Set header to indicate JSON output
		header('Content-Type: application/json; charset=utf-8');

		// Output the JSON-encoded response safely
		echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

		// Stop execution to avoid sending additional output
		exit;
	}

	public function ModulePage() {
		// If the "usercountjson" parameter is set, output the user count as JSON and stop execution
		if (isset($_GET['usercountjson'])) {
			$this->outputUserCountJson();
			return;
		}

		$pageHTML = '<!DOCTYPE html><html>';
		
		// Add CSS so it appears properly inside the iframe
		$pageHTML .= '
			<head>
				<meta charset="utf-8">
				<meta name="viewport" content="width=device-width, initial-scale=1.0">
				<link rel="stylesheet" href="' . $this->staticUrl . 'css/kokoimg/base.css">
			</head>
			<body id="counterIframeBody">';

		$userCount = $this->getUserCount();
		$userWord = $userCount === 1 ? 'user' : 'users';
		$minuteWord = $this->timeout === 1 ? 'minute' : 'minutes';

		$userCounterHTML = '<div id="usercounter" value="'.$this->timeout.'"><span id="countnumber">' . $userCount . '</span> unique ' . $userWord . ' in the last ' . $this->timeout . ' ' . $minuteWord . ' (including lurkers)</div>';
		$pageHTML .= $userCounterHTML;
		$pageHTML .= '</body></html>';
		
		echo $pageHTML;
	}
}
