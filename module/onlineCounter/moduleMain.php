<?php

namespace Kokonotsuba\Modules\onlineCounter;

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

		$currentTimeInMinutes = floor(time() / 60);
		$addr = $_SERVER['REMOTE_ADDR'];

		// Clean and rebuild the list of active users
		$activeUsers = $this->filterActiveUsers($usr_arr, $currentTimeInMinutes, $addr);

		// Add or update the current user's timestamp
		$activeUsers[$addr] = $currentTimeInMinutes;

		// Rewrite the entire file with the updated list
		$this->writeActiveUsers($fp, $activeUsers);

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
	 * @param int $currentTimeInMinutes Current timestamp in minutes.
	 * @param string $currentAddr IP address of the current user.
	 * @return array Array of active users in [ip => timestamp] format.
	 */
	private function filterActiveUsers($usr_arr, $currentTimeInMinutes, $currentAddr) {
		$activeUsers = [];

		foreach ($usr_arr as $line) {
			$line = trim($line);
			if (empty($line)) continue;

			// Safely split the line into IP and timestamp
			list($ip_addr, $stamp) = explode("|", $line) + [null, null];

			// Validate and retain only active, non-duplicate IPs
			if (is_numeric($stamp) && ($currentTimeInMinutes - $stamp) < $this->timeout && $ip_addr != $currentAddr) {
				$activeUsers[$ip_addr] = $stamp;
			}
		}

		return $activeUsers;
	}

	/**
	 * Writes the current list of active users back to the file.
	 * 
	 * @param resource $fp File pointer (already open and locked).
	 * @param array $activeUsers Array of active users in [ip => timestamp] format.
	 */
	private function writeActiveUsers($fp, $activeUsers) {
		// Clear file contents before writing
		ftruncate($fp, 0);
		rewind($fp);

		// Write each active user entry as "ip|timestamp"
		foreach ($activeUsers as $ip_addr => $stamp) {
			fputs($fp, $ip_addr . '|' . $stamp . "\n");
		}
	}

	
	private function onRenderPostInfo(string &$hookPostInfoHtml): void {
		$userCount = $this->getUserCount();
		$userCounterHTML = '
			<li id="counterListItemJS" class="hidden">
				<div data-timeout="'.$this->timeout.'" data-modurl="'.$this->modulePageUrl.'&usercountjson" id="usercounter">
					<span id="countnumber">' . $userCount . '</span> unique user' . ($userCount > 1 ? 's' : '') . ' in the last '.$this->timeout.' minute'.($this->timeout > 1 ? 's' : '').' (including lurkers)
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
		
		//add css so it appears properly inside iframe
		$pageHTML .= '
			<head>
				<meta charset="utf-8">
				<meta name="viewport" content="width=device-width, initial-scale=1.0">
				<link rel="stylesheet" href="' . $this->staticUrl . 'css/kokoimg/base.css">
			</head>
			<body id="counterIframeBody">';
		$userCount = $this->getUserCount();
		$userCounterHTML = '<div id="usercounter" value="'.$this->timeout.'"><span id="countnumber">' . $userCount . '</span> unique user' . ($userCount > 1 ? 's' : '') . ' in the last '.$this->timeout.' minutes (including lurkers)</div>';
		$pageHTML .= $userCounterHTML;
		$pageHTML .= '</body></html>';
		
		echo $pageHTML;
	}

}
