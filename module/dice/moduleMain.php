<?php
namespace Kokonotsuba\Modules\dice;

use Kokonotsuba\error\BoardException;
use Kokonotsuba\module_classes\abstractModuleMain;
use Kokonotsuba\module_classes\traits\listeners\PostCommentListenerTrait;
use Kokonotsuba\module_classes\traits\listeners\RegistBeforeCommitListenerTrait;
use Kokonotsuba\post\commentMarker;
use Kokonotsuba\post\Post;

use function Puchiko\strings\sanitizeStr;

class moduleMain extends abstractModuleMain {
	use PostCommentListenerTrait;
	use RegistBeforeCommitListenerTrait;

	/** Marker kind for a roll made from the email field. */
	private const MARKER_EMAIL = 'diceemail';

	/** Marker kind for a futaba-style roll made inside the comment. */
	private const MARKER_COMMENT = 'dice';

	private int $dieAmountLimit, $dieFaceLimit, $emailDiceRoll, $commentDiceRoll;

	public function getName(): string {
		return 'Kokonotsuba dice roll module';
	}

	public function getVersion(): string  {
		return 'Koko 2025';
	}

	public function initialize(): void {
		// get the die Amount config value and set it
		$this->dieAmountLimit = $this->getModuleConfig('DICE_AMOUNT_LIMIT', 30);

		// get the die face config value and set it
		$this->dieFaceLimit = $this->getModuleConfig('DICE_FACE_LIMIT', 9999);

		// get the email dice roll config value and set it
		$this->emailDiceRoll = $this->getModuleConfig('EMAIL_DICE_ROLL', false);

		// get the comment dice roll config value and set it
		$this->commentDiceRoll = $this->getModuleConfig('COMMENT_DICE_ROLL', true);

		$this->listenRegistBeforeCommit('onBeforeCommit');

		// The roll happens once, at post time; this draws it on every render.
		$this->listenPostComment('onRenderComment');
	}

	/**
	 * Expand the markers left by a roll.
	 *
	 * Runs on the escaped comment, which the marker syntax passes through untouched.
	 */
	private function onRenderComment(string &$comment, ?Post $post = null, bool $isThreadView = false): void {
		$comment = commentMarker::expand($comment, self::MARKER_EMAIL,
			fn(string $payload): string => $this->renderEmailRoll($payload));

		$comment = commentMarker::expand($comment, self::MARKER_COMMENT,
			fn(string $payload): string => $this->renderCommentRoll($payload));
	}

	/**
	 * Draw an email-field roll from its stored values.
	 *
	 * @param string $payload Comma-separated roll values.
	 */
	private function renderEmailRoll(string $payload): string {
		$values = $this->parseValues($payload);

		if (empty($values)) {
			return '';
		}

		$label = count($values) === 1
			? '[NUMBER: ' . $values[0] . ']'
			: '[NUMBERS: ' . implode(', ', $values) . ']';

		return $this->rollEmailHtmlTag($label);
	}

	/**
	 * Draw a comment roll from its stored notation and values.
	 *
	 * @param string $payload '<amount>d<faces>[+-<modifier>]:<v1>,<v2>,...'
	 */
	private function renderCommentRoll(string $payload): string {
		[$notation, $rolled] = array_pad(explode(':', $payload, 2), 2, '');
		$values = $this->parseValues($rolled);

		if ($notation === '' || empty($values)) {
			return '';
		}

		$modifier = 0;
		if (preg_match('/([+-]\d+)$/', $notation, $m)) {
			$modifier = (int)$m[1];
		}

		$dicePrefix = 'dice' . $notation . '=';
		$diceContent = implode(', ', $values);

		// A single die only shows a total when a modifier moves it; multiple dice always do.
		if (count($values) === 1) {
			if ($modifier !== 0) {
				$diceContent .= ' (' . ($values[0] + $modifier) . ')';
			}
		} else {
			$diceContent .= ' (' . (array_sum($values) + $modifier) . ')';
		}

		return $this->rollCommentHtmlTag($dicePrefix, $diceContent);
	}

	/**
	 * Read the roll values out of a marker payload, dropping anything non-numeric.
	 *
	 * @return int[]
	 */
	private function parseValues(string $payload): array {
		$values = [];

		foreach (explode(',', $payload) as $value) {
			$value = trim($value);
			if ($value !== '' && ctype_digit($value)) {
				$values[] = (int)$value;
			}
		}

		return $values;
	}

	public function onBeforeCommit(&$name, string &$email, string &$emailForInsertion, &$sub, string &$com): void {
		
		// Handle email-field dice rolling
		if($this->emailDiceRoll) {
			$this->handleEmailDiceRoll($email, $emailForInsertion, $com);
		}
		
		// Handle futaba-style comment rolling
		if($this->commentDiceRoll) {
			$this->handleCommentDiceRoll($com);
		}

	}

	private function handleEmailDiceRoll(string &$email, string &$emailForInsertion, string &$comment): void {
		// return early if the email doesn't contain 'dice'
		if(!str_contains($email, 'dice')) {
			return;
		}

		// check if its a valid dice text
		if(!$this->isValidDice($email)) {
			return;
		}

		// get amount and faces of die
		[$dieAmount, $dieFaces] = $this->getDieDetails($email);

		// validate dice details
		// return if either are non-integers, null, 0, or invalid
		if(!$this->validateDiceDetails($dieAmount, $dieFaces)) {
			return;
		}

		// roll now, draw later
		$diceValues = $this->generateDiceArray($dieAmount, $dieFaces);

		// remove it from the insertion email
		$emailForInsertion = $this->removeDiceRollText($emailForInsertion);

		// append the roll's marker to the comment
		$comment .= commentMarker::make(self::MARKER_EMAIL, implode(',', $diceValues));
	}

	private function getDieDetails(string $diceInput) {
		return $this->extractDieDetails($diceInput);
	}

	private function validateDiceDetails(int $dieAmount, int $dieFaces): bool {
		// Check if the number of dice is a positive integer
		if ($dieAmount <= 0) {
			return false;  // Invalid dice amount
		}

		// Check if the number of faces is a valid integer (e.g., 4, 6, 8, 10, 12, 20, etc.)
		if ($dieFaces <= 0) {
			return false;  // Invalid dice faces
		}

		// check if either is above the limits and return early
		if($dieAmount > $this->dieAmountLimit || $dieFaces > $this->dieFaceLimit) {
			return false;
		}

		// All checks passed, return true
		return true;
	}

	private function isValidDice(string $diceInput): bool {
		// Look for "dice" followed by optional +NdM, anywhere in the string
		return preg_match('/dice(?:\+?(\d+)d(\d+))?/', $diceInput) === 1;
	}

	private function extractDieDetails(string $diceInput): array {
		// Extract "dice" with optional +NdM
		preg_match('/dice(?:\+?(\d+)d(\d+))?/', $diceInput, $matches);

		if (isset($matches[1]) && isset($matches[2])) {
			return [
				(int)$matches[1], // Number of dice
				(int)$matches[2]  // Faces per die
			];
		}

		if (!empty($matches[0])) {
			// Just "dice" with no NdM → default 1d6
			return [1, 6];
		}

		throw new BoardException("Invalid dice format.");
	}

	private function generateDiceArray(int $dieAmount, int $dieFaces): array {
		// int dice number array
		$diceValues = [];

		// loop and append random dice values
		for($i = 0; $i < $dieAmount; $i++) {
			// generate individual roll number
			$rollNumber = random_int(1, $dieFaces);

			// append to array
			$diceValues[] = $rollNumber;
		}

		// return the die array
		return $diceValues;
	}

	private function rollEmailHtmlTag(string $contents): string {
		return '
			<div class="rollContainer">
				<p class="roll" title="This is a dice roll">' . sanitizeStr($contents) . '</p>
			</div>';
	}

	private function removeDiceRollText(string $input): string {
		// Remove any token that starts with "dice" and may have +NdM or other letters/numbers after it
		$output = preg_replace('/dice[+\w\d]*/i', '', $input);

		// Trim extra spaces left behind
		return preg_replace('/\s+/', ' ', trim($output));
	}

	private function handleCommentDiceRoll(string &$comment): void {
		// Find and replace futaba-style dice roll tokens
		$comment = preg_replace_callback(
			'/(?:^|\n)\K\s*(?<!\!)dice(\d+)d(\d+)([+-]\d+)?=/i',
			fn($m) => $this->processCommentDiceMatch($m),
			$comment
		);
	}

	private function processCommentDiceMatch(array $matches): string {
		$dieAmount = (int)$matches[1];
		$dieFaces = (int)$matches[2];
		$modifier = 0;

		if (isset($matches[3]) && $matches[3] !== '') {
			$modifier = (int)$matches[3];
			if (!$this->validateModifier($modifier)) {
				// Keep the original text if modifier is unreasonable
				return $matches[0];
			}
		}

		if (!$this->validateDiceDetails($dieAmount, $dieFaces)) {
			// Keep the original text if invalid
			return $matches[0];
		}

		$values = $this->generateDiceArray($dieAmount, $dieFaces);

		$notation = $dieAmount . 'd' . $dieFaces
			. ($modifier !== 0 ? ($modifier > 0 ? '+' . $modifier : (string)$modifier) : '');

		return commentMarker::make(self::MARKER_COMMENT, $notation . ':' . implode(',', $values));
	}

	private function rollCommentHtmlTag(string $dicePrefix, string $content): string {
		// keep the prefix "dice2d6=" part just in the container
		// the content (the dice numbers + sum) in the roll span
		return '
			<span class="rollContainer">' . sanitizeStr($dicePrefix) . '<span class="roll" title="This is a dice roll">' . sanitizeStr($content) . '</span></span>';
	}

	private function validateModifier(int $modifier): bool {
		// prevent pathological values
		return $modifier >= -100000 && $modifier <= 100000;
	}

}
