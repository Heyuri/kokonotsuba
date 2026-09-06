<?php

namespace Kokonotsuba\Modules\antiSpam;

require_once __DIR__ . '/antiSpamRepository.php';
require_once __DIR__ . '/antiSpamService.php';
require_once __DIR__ . '/antiSpamLib.php';

use Kokonotsuba\action_log\actionLogReferences;
use Kokonotsuba\ban\banCheckpoint;
use Kokonotsuba\error\BoardException;
use Kokonotsuba\module_classes\traits\AuditableTrait;
use Kokonotsuba\module_classes\traits\BanCheckpointTrait;
use Kokonotsuba\ip\IPAddress;
use Kokonotsuba\module_classes\abstractModuleMain;
use Kokonotsuba\Modules\antiSpam\antiSpamService;

use function Kokonotsuba\libraries\_T;
use function Kokonotsuba\Modules\antiSpam\getAntiSpamService;
use function Puchiko\json\renderJsonErrorPage;
use function Puchiko\json\sendJsonResponse;
use function Puchiko\request\redirect;

use const Kokonotsuba\GLOBAL_BOARD_UID;

class moduleMain extends abstractModuleMain {
	use AuditableTrait;
	use BanCheckpointTrait;

	/** Action log type a tripped filter is recorded under. */
	public const FILTER_ACTION_TYPE = 'spam.filter';

	private antiSpamService $antiSpamService;

	public function getName(): string {
		return 'Anti-spam checking system';
	}

	public function getVersion(): string {
		return 'NEW YEARZ';
	}

	public function initialize(): void {
		// a tripped filter is its own kind of event, so it gets its own checkbox on the action
		// log filter form
		$this->registerActionType(self::FILTER_ACTION_TYPE, 'Spam filter tripped');

		// add to the regist before commit hook point
		// this is ran before a post is inserted
		$this->moduleContext->moduleEngine->addListener('RegistBegin', function (&$registInfo) {
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
					$this->executeRuleAction($rule, $field);
				}
			}

			// Check rule against file names if enabled
			if(!empty($rule['apply_filename'])){
				foreach($fileNames as $fileName){
					if($this->matchesRule($fileName, $rule)){
						$this->executeRuleAction($rule, 'filename');
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

	private function executeRuleAction(array $rule, string $field): void {
		// Use custom user message if provided, otherwise fallback
		$message = $rule['user_message'] ?: _T('anti_spam_message');

		// Execute rule action
		switch($rule['action']){
			case 'mute':
				// get the mute time config value
				// measured in minutes - defaults to 20 minutes
				$muteTime = $this->getConfig('modules.adminDel.JANIMUTE_LENGTH', 20);

				// Mute the user (short-term ban)
				$banId = $this->banUser($message, $muteTime * 60, true);
				break;
			case 'ban':
				// get the ban time config value
				// measured in hours - defaults to 24 hours
				$banTime = $this->getModuleConfig('FILTER_BAN_TIME', 24);

				// Ban the current user
				$banId = $this->banUser($message, $banTime * 3600);
				break;
			case 'reject':
			default:
				// nothing is filed against the poster, the post just does not go through
				$banId = null;
			break;
		}

		// record the hit while the ban it filed is still to hand - the rejection below ends the
		// request either way
		$this->logRuleHit($rule, $field, $banId);

		// reject the submission
		$this->rejectSubmission($message, !empty($rule['silent_reject']));
	}

	/**
	 * Note in the action log that a rule matched.
	 *
	 * Logged as the poster rather than as staff, and referencing the rule itself so the entry
	 * links straight to it.
	 */
	private function logRuleHit(array $rule, string $field, ?int $banId = null): void {
		$reference = actionLogReferences::reference('spamrule', (int)$rule['id'], 'Spam filter #' . (int)$rule['id']);

		$outcome = match ($rule['action']) {
			'ban' => 'poster banned',
			'mute' => 'poster muted',
			default => 'post rejected',
		};

		// the ban the rule just filed, so the entry links to it as well as to the rule
		if ($banId !== null) {
			$outcome .= ' (' . actionLogReferences::reference('ban', $banId, 'ban #' . $banId) . ')';
		}

		$this->logAction(
			"{$reference} matched on {$field}, {$outcome}",
			$this->moduleContext->board->getBoardUID(),
			self::FILTER_ACTION_TYPE,
			true
		);
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

	/**
	 * Ban the poster who tripped a rule.
	 *
	 * Posting only: a spam filter should stop the flood, not silently cut the poster off from
	 * reporting or appealing. A rule's 'mute' action files a mute, which is swept out of the ban
	 * table once it lapses - a filter firing on a flood would otherwise bury the real bans.
	 */
	private function banUser(string $reason, int $durationSeconds, bool $isMute = false): ?int {
		$expires = $this->moduleContext->request->getRequestTime() + $durationSeconds;

		$banId = $this->getBanService()->fileBan(
			(string) $this->moduleContext->request->userIp(),
			GLOBAL_BOARD_UID,
			[banCheckpoint::POST->value],
			$expires,
			$reason,
			null,
			null,
			false,
			false,
			$isMute
		);

		if ($isMute) {
			$this->getBanService()->pruneExpiredMutes();
		}

		return $banId;
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