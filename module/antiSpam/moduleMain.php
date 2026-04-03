<?php

namespace Kokonotsuba\Modules\antiSpam;

require_once __DIR__ . '/antiSpamRepository.php';
require_once __DIR__ . '/antiSpamService.php';
require_once __DIR__ . '/antiSpamLib.php';

use Kokonotsuba\error\BoardException;
use Kokonotsuba\ip\IPAddress;
use Kokonotsuba\module_classes\abstractModuleMain;
use Kokonotsuba\module_classes\listeners\RegistBeginListenerTrait;
use Kokonotsuba\Modules\antiSpam\antiSpamService;

use function Kokonotsuba\libraries\_T;
use function Kokonotsuba\Modules\antiSpam\getAntiSpamService;
use function Puchiko\json\renderJsonErrorPage;
use function Puchiko\json\sendJsonResponse;
use function Puchiko\request\redirect;

class moduleMain extends abstractModuleMain {
	use RegistBeginListenerTrait;

	private antiSpamService $antiSpamService;
	private string $globalBans;
	private string $globalBansPath;

	public function getName(): string {
		return 'Anti-spam checking system';
	}

	public function getVersion(): string {
		return 'NEW YEARZ';
	}

	public function initialize(): void {
		// add to the regist before commit hook point
		// this is ran before a post is inserted
		$this->listenRegistBegin(function (&$registInfo) {
			$this->onBeforeCommit(
				$registInfo['name'],
				$registInfo['com'],
				$registInfo['email'],
				$registInfo['sub'],
				$registInfo['files'] ?? [],
				!empty($registInfo['isThreadSubmit'])
			); 
		});

		// set antispam service instance
		$this->antiSpamService = getAntiSpamService();

		// get global bans path
		$this->globalBansPath = getBackendGlobalDir() . $this->getConfig('GLOBAL_BANS');
	}
	
	private function onBeforeCommit(?string $name, ?string $comment, ?string $email, ?string $subject, array $files = [], bool $isOp = false): void{
		// Extract file names from attachments
		$fileNames = $this->extractFileNames($files); 

		// Fetch all active spam string rules
		$spamRules = $this->antiSpamService->getActiveSpamStringRules($subject, $comment, $name, $email, !empty($fileNames), $isOp);

		// Normalize all input fields into a single iterable array
		$fields = [
			'subject' => $subject ?? '',
			'comment' => html_entity_decode($comment) ?? '',
			'name' => $name ?? '',
			'email' => $email ?? ''
		];

		// Iterate through every spam rule
		foreach($spamRules as $rule){
			// Skip inactive rules (extra safety)
			if(!$rule['is_active']){
				continue;
			}

			// Check rule against each enabled field
			foreach($fields as $field => $value){
				// Skip empty input values
				if($value === ''){
					continue;
				}

				// Skip fields this rule is not configured to apply to
				if(
					($field === 'subject' && !$rule['apply_subject']) ||
					($field === 'comment' && !$rule['apply_comment']) ||
					($field === 'name' && !$rule['apply_name']) ||
					($field === 'email' && !$rule['apply_email'])
				){
					continue;
				}

				// Check if the field value matches the spam rule
				if($this->matchesRule($value, $rule)){
					$this->executeRuleAction($rule);
				}
			}

			// Check rule against file names if enabled
			if(!empty($rule['apply_filename'])){
				foreach($fileNames as $fileName){
					if($this->matchesRule($fileName, $rule)){
						$this->executeRuleAction($rule);
					}
				}
			}
		}
	}

	private function extractFileNames(array $files): array {
		$fileNames = [];
		foreach ($files as $file) {
			if (isset($file['fileName']) && is_string($file['fileName'])) {
				$fileNames[] = $file['fileName'];
			}
		}
		return $fileNames;
	}

	private function executeRuleAction(array $rule): void {
		// Use custom user message if provided, otherwise fallback
		$message = $rule['user_message'] ?: _T('anti_spam_message');

		// Execute rule action
		switch($rule['action']){
			case 'mute':
				// get the mute time config value
				// measured in minutes - defaults to 20 minutes
				$muteTime = $this->getConfig('ModuleSettings.JANIMUTE_LENGTH', 20);

				// Mute the user (short-term ban)
				$this->banUser($message, $muteTime * 60);

				// reject the submission
				$this->rejectSubmission($message, !empty($rule['silent_reject']));
			case 'ban':
				// get the ban time config value
				// measured in hours - defaults to 24 hours
				$banTime = $this->getConfig('ModuleSettings.FILTER_BAN_TIME', 24);

				// Ban the current user
				$this->banUser($message, $banTime * 3600);

				// reject the submission
				$this->rejectSubmission($message, !empty($rule['silent_reject']));
			case 'reject':
			default:
				// reject the submission
				$this->rejectSubmission($message, !empty($rule['silent_reject']));
			break;
		}
	}

	private function rejectSubmission(string $message, bool $silent): void {
		if($silent){
			$this->silentReject();
		}

		$this->loudReject($message);
	}

	private function silentReject(): void {
		$boardUrl = $this->moduleContext->board->getBoardURL();

		// for JS requests, send a JSON response that mimics a successful post
		// the client will redirect to the board index as if nothing happened
		if($this->moduleContext->request->isAjax()){
			sendJsonResponse(['redirectUrl' => $boardUrl]);
			exit;
		}

		// for normal requests, just redirect
		redirect($boardUrl);
	}

	private function loudReject(string $message): void {
		// for JS requests, send a JSON error response
		if($this->moduleContext->request->isAjax()){
			renderJsonErrorPage(strip_tags($message));
			exit;
		}

		// for normal requests, throw a board exception (caught by the global handler)
		throw new BoardException($message);
	}

	private function banUser(string $reason, int $durationSeconds): void {
		// get IP from request
		// this is the user who made the regist request
		$ipAddress = $this->moduleContext->request->userIp();

		$ip = (string)$ipAddress;

		// calculate start time
		$startTime = $_SERVER['REQUEST_TIME'];

		// calculate end time
		$expires = $startTime + $durationSeconds;

		// sanitize reason for flat-file storage (commas break the CSV format)
		$reason = str_replace(',', '&#44;', $reason);

		// build ban entry
		$banEntry = "{$ip},{$startTime},{$expires},{$reason}";

		// append to global bans file
		$needsNewline = file_exists($this->globalBansPath) && filesize($this->globalBansPath) > 0;

		$f = fopen($this->globalBansPath, 'a');
		if ($f) {
			if ($needsNewline) {
				fwrite($f, "\n");
			}
			fwrite($f, $banEntry);
			fclose($f);
		}
	}

	private function matchesRule(string $value, array $rule): bool {
		// Extract pattern from rule
		$pattern = $rule['pattern'];

		// Normalize case if rule is not case-sensitive
		if(!$rule['case_sensitive']){
			$value = mb_strtolower($value);
			$pattern = mb_strtolower($pattern);
		}

		// normalize spacing for non-regex rules
		if ($rule['match_type'] !== 'regex') {
			$value = $this->normalizeField($value);
		}

		// Apply matching strategy
		switch($rule['match_type']){
			case 'exact':
				// Value must exactly match the pattern
				return $value === $pattern;
				break;
			case 'regex':
				// wrapped pattern with delimiters
				$pattern = '/' . str_replace('/', '\/', $pattern) . '/u';

				// Treat pattern as raw regex (admin responsibility)
				return preg_match($pattern, $value) === 1;
				break;
			case 'fuzzy':
				// Fuzzy matching requires a maximum allowed distance
				if($rule['max_distance'] === null){
					return false;
				}

				// length of the spam pattern
				$patternLen = mb_strlen($pattern);

				// length of the value being checked
				$valueLen = mb_strlen($value);

				// hard safety limits
				// empty patterns are invalid
				// overly long strings make Levenshtein expensive and noisy
				if ($patternLen === 0 || $patternLen > 64 || $valueLen > 64) {
					return false;
				}

				// maximum meaningful distance relative to pattern length
				// distances larger than half the pattern length become too permissive
				$maxAllowed = (int)floor($patternLen / 2);

				// clamp the configured distance to a safe upper bound
				$distance = min((int)$rule['max_distance'], $maxAllowed);

				// distance of zero or less makes fuzzy matching pointless
				if ($distance < 1) {
					return false;
				}

				// Hard safety limits to prevent expensive comparisons
				if(mb_strlen($value) > 64 || mb_strlen($pattern) > 64){
					return false;
				}

				// Length difference alone can disqualify the match
				if(abs(mb_strlen($value) - mb_strlen($pattern)) > (int)$rule['max_distance']){
					return false;
				}

				// Fast bounded-distance check
				return levenshtein($pattern, $value) <= (int)$rule['max_distance'];
				break;
			case 'contains':
			default:
				// Simple substring match
				return mb_strpos($value, $pattern) !== false;
		}
	}

	private function normalizeField(string $text): string {
		// normalize whitespace
		$text = $this->normalizeWhitespace($text);

		// collapse spaced letters
		$text = $this->collapseSpacedLetters($text);

		// normalize obfuscated URLs
		$text = $this->normalizeObfuscatedUrls($text);

		// strip zero width spaces
		$text = $this->stripZeroWidthSpaces($text);

		return $text;
	}

	private function normalizeWhitespace(string $text): string {
		// collapse all whitespace into single spaces
		$text = preg_replace('/\s+/u', ' ', $text);

		// trim leading and trailing space
		return trim($text);
	}

	private function collapseSpacedLetters(string $text): string {
		// collapse sequences like "n e t" or "v i a g r a"
		return preg_replace_callback(
			'/\b(?:[a-zA-Z]\s+){2,}[a-zA-Z]\b/u',
			function ($m) {
				return str_replace(' ', '', $m[0]);
			},
			$text
		);
	}

	private function normalizeObfuscatedUrls(string $text): string {
		// collapse spaces around URL-relevant characters
		$text = preg_replace(
			'/\s*([:\/\.\-\?_=&%#])\s*/u',
			'$1',
			$text
		);

		// collapse spaced letters inside URL-like strings
		$text = preg_replace_callback(
			'/\b(?:https?|ftp)\s*(?:[:\/\.a-z0-9]\s*){5,}/iu',
			function ($m) {
				return preg_replace('/\s+/u', '', $m[0]);
			},
			$text
		);

		return $text;
	}

	private function stripZeroWidthSpaces(string $text): string {
		// remove zero-width space characters
		return str_replace("\u{200B}", '', $text);
	}

}