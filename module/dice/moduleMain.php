<?php
namespace Kokonotsuba\Modules\dice;

use BoardException;
use Kokonotsuba\ModuleClasses\abstractModuleMain;

class moduleMain extends abstractModuleMain {
	private int $dieAmountLimit, $dieFaceLimit, $emailDiceRoll, $commentDiceRoll;

	public function getName(): string {
		return 'Kokonotsuba dice roll module';
	}

	public function getVersion(): string  {
		return 'Koko 2025';
	}

	public function initialize(): void {
		// get the die Amount config value and set it
		$this->dieAmountLimit = $this->getConfig('ModuleSettings.DICE_AMOUNT_LIMIT', 30);

		// get the die face config value and set it
		$this->dieFaceLimit = $this->getConfig('ModuleSettings.DICE_FACE_LIMIT', 9999);

		// get the email dice roll config value and set it
		$this->emailDiceRoll = $this->getConfig('ModuleSettings.EMAIL_DICE_ROLL', false);

		// get the comment dice roll config value and set it
		$this->commentDiceRoll = $this->getConfig('ModuleSettings.COMMENT_DICE_ROLL', true);

		$this->moduleContext->moduleEngine->addListener('RegistBeforeCommit', function ($name, &$email, &$emailForInsertion, &$sub, &$com, &$category, &$age, $file, $isReply, &$status, $thread, &$poster_hash) {
			$this->onBeforeCommit($email, $emailForInsertion, $com);
		});

	}

	public function onBeforeCommit(string &$email, string &$emailForInsertion, string &$comment): void {
		
		// Handle email-field dice rolling
		if($this->emailDiceRoll) {
			$this->handleEmailDiceRoll($email, $emailForInsertion, $comment);
		}
		
		// Handle futaba-style comment rolling
		if($this->commentDiceRoll) {
			$this->handleCommentDiceRoll($comment);
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

		// generate dice text
		$diceText = $this->generateDiceText($dieAmount, $dieFaces);

		// remove it from the insertion email
		$emailForInsertion = $this->removeDiceRollText($emailForInsertion);

		// append dice text to comment
		$comment .= $diceText;
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

	private function generateDiceText(int $dieAmount, int $dieFaces): string {
		// generate the die values
		$diceValues = $this->generateDiceArray($dieAmount, $dieFaces);

		// generate the die text
		$diceText = $this->generateDieHtml($diceValues);
		
		// now return the diceText
		return $diceText;
	}

	private function generateDiceArray(int $dieAmount, int $dieFaces): array {
		// int dice number array
		$diceValues = [];

		// loop and append random dice values
		for($i = 0; $i < $dieAmount; $i++) {
			// generate individual roll number
			$rollNumber = rand(1, $dieFaces);

			// append to array
			$diceValues[] = $rollNumber;
		}

		// return the die array
		return $diceValues;
	}

	private function generateDieHtml(array $diceValues): string {
		// Generate a single dice number HTML if there's only one value
		if (count($diceValues) === 1) {
			$diceNumber = (string)$diceValues[0];
			return $this->rollEmailHtmlTag('[NUMBER: ' . $diceNumber . ']');
		}

		// If there are multiple dice values, join them with commas and return the HTML
		$separatedDiceValues = implode(', ', $diceValues);
		return $this->rollEmailHtmlTag('[NUMBERS: ' . $separatedDiceValues . ']');
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
		// Find and replace futaba-style dice roll tokens, but ignore ones escaped with a leading "!"
		$comment = preg_replace_callback(
			'/(?:^|<br\s*\/?>)\K\s*(?<!\!)dice(\d+)d(\d+)([+-]\d+)?=/i',
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
		return $this->formatCommentDiceRoll($dieAmount, $dieFaces, $values, $modifier);
	}

	private function formatCommentDiceRoll(int $dieAmount, int $dieFaces, array $values, int $modifier = 0): string {
		// For single dice rolls
		if (count($values) === 1) {
			// Dice prefix
			$dicePrefix = 'dice' . $dieAmount . 'd' . $dieFaces . ($modifier !== 0 ? ($modifier > 0 ? '+' . $modifier : (string)$modifier) : '') . '=';

			// Single die: 4
			$diceContent = (string)$values[0];

			// If there is a modifier, show final result in parentheses
			if ($modifier !== 0) {
				$final = $values[0] + $modifier;
				$diceContent .= ' (' . $final . ')';
			}

			// generate the html span
			$diceHtml = $this->rollCommentHtmlTag($dicePrefix, $diceContent);

			// return it
			return $diceHtml;
		}

		// Multiple dice: dice3d6=2, 5, 3 ( 10)
		$diceSum = array_sum($values);

		// dice prefix
		$dicePrefix = 'dice' . $dieAmount . 'd' . $dieFaces . ($modifier !== 0 ? ($modifier > 0 ? '+' . $modifier : (string)$modifier) : '') . '=';

		// build the dice content
		$diceContent = implode(', ', $values);
		if ($modifier !== 0) {
			$final = $diceSum + $modifier;
			$diceContent .= ' (' . $final . ')';
		} else {
			$diceContent .= ' (' . $diceSum . ')';
		}

		// generate the html span
		$diceHtml = $this->rollCommentHtmlTag($dicePrefix, $diceContent);

		// return it
		return $diceHtml;
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
