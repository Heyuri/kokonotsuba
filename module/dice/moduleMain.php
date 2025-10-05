<?php
namespace Kokonotsuba\Modules\dice;

use BoardException;
use Kokonotsuba\ModuleClasses\abstractModuleMain;

class moduleMain extends abstractModuleMain {
	private int $dieAmountLimit, $dieFaceLimit;

	public function getName(): string {
		return 'Kokonotsuba dice roll module';
	}

	public function getVersion(): string  {
		return 'Koko 2025';
	}

	public function initialize(): void {
		// get the die Amount config value and set it
		$this->dieAmountLimit = $this->getConfig('ModuleSettings.DICE_AMOUNT_LIMIT', 10);

		// get the die face config value and set it
		$this->dieFaceLimit = $this->getConfig('ModuleSettings.DICE_FACE_LIMIT', 50);

		$this->moduleContext->moduleEngine->addListener('RegistBeforeCommit', function ($name, &$email, &$emailForInsertion, &$sub, &$com, &$category, &$age, $file, $isReply, &$status, $thread, &$poster_hash) {
			$this->onBeforeCommit($email, $emailForInsertion, $com);
		});

	}

	public function onBeforeCommit(string &$email, string &$emailForInsertion, string &$comment): void {
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

		// remove the dice text from the email field
		$email = $this->removeDiceRollText($email);

		// remove it from the insertion email too
		$emailForInsertion = $this->removeDiceRollText($emailForInsertion);

		// append dice text to comment
		$comment .= $diceText;
	}

	public function getDieDetails(string $diceInput) {
		if ($this->isValidDice($diceInput)) {
			return $this->extractDieDetails($diceInput);
		} else {
			throw new BoardException("Invalid dice format.");
		}
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
		// Search for the dice pattern anywhere in the string
		return preg_match('/dice\+(\d+)d(\d+)/', $diceInput) === 1;
	}

	private function extractDieDetails(string $diceInput): array {
	    // Match the dice+NdM pattern anywhere in the string (ignoring surrounding text)
	    preg_match('/dice\+(\d+)d(\d+)/', $diceInput, $matches);

	    // Ensure we have valid matches before proceeding
	    if (isset($matches[1]) && isset($matches[2])) {
	        return [
	            (int)$matches[1], // Number of dice
	            (int)$matches[2]  // Faces on each die
	        ];
	    }

	    // If no valid dice pattern is found, throw an error
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
	        return '<p class="roll" title="This is a dice roll">[NUMBER: ' . sanitizeStr($diceNumber) . ']</p>';
	    }

	    // If there are multiple dice values, join them with commas and return the HTML
	    $separatedDiceValues = implode(', ', array_map('sanitizeStr', $diceValues)); // Apply sanitizeStr to each value for safety
	    return '<p class="roll" title="This is a dice roll">[NUMBERS: ' . $separatedDiceValues . ']</p>';
	}

	private function removeDiceRollText(string $input): string {
	    // Regex to match and remove 'dice+NdM' pattern
	    $output = preg_replace('/dice\+\d+d\d+/', '', $input);

	    // Trim any extra spaces left behind after removal
	    return preg_replace('/\s+/', ' ', trim($output));
	}

}
