<?php

namespace Kokonotsuba\Modules\tripcode;

use actionLoggerService;
use BoardException;
use capcodeService;
use Exception;
use Throwable;

use const Kokonotsuba\Root\Constants\GLOBAL_BOARD_UID;

require __DIR__ . '/capcodeLib.php';

class capcodeModuleRequestHandler {
	public function __construct(
		private capcodeService $capcodeService,
		private actionLoggerService $actionLoggerService
	) {}

	public function handleModPageRequests(
		int $accountId
	): void {
		// get the action from post
		// this determins how the request will be handled
		$action = $_POST['action'] ?? null;

		// delete a capcode by id
		if($action === 'deleteCapcode') {
			// handle capcode deletion request
			$this->handleCapcodeDeletion();
		}

		// create a capcode
		elseif($action === 'createCapcode') {
			// handle capcode creation request
			$this->handleCapcodeCreate($accountId);
		}

		// modify a capcode
		elseif($action) {
			// handle capcode modification request
			$this->handleCapcodeModify();
		}
	}

	private function handleCapcodeDeletion(): void {
		$this->handleCapcodeOperation('deleting', function() {
			// get the capcode id from POST
			$capcodeId = $_POST['capcodeId'] ?? null;

			// validate the capcode id
			validateCapcodeId($capcodeId);

			// delete the capcode by its ID
			// a transaction is ran internally
			$this->capcodeService->removeCapcode($capcodeId);

			// log the deletion
			$this->actionLoggerService->logAction("Deleted capcode ($capcodeId)", GLOBAL_BOARD_UID);
		});
	}
	
	private function handleCapcodeCreate(int $accountId): void {
		$this->handleCapcodeOperation('creating', function() use ($accountId) {
			// who is adding the capcode
			$addedBy = $accountId;

			// get the input from POST
			[$tripcode, $isSecure, $colorHex, $capcodeText] = $this->getCapcodeInputFromRequest();

			// add the capcode to the database
			// also fetch the Id
			$capcodeId = $this->capcodeService->addCapcode(
				$tripcode,
				$isSecure,
				$addedBy,
				$colorHex,
				$capcodeText
			);

			// log the creation
			$this->actionLoggerService->logAction("Created capcode ($capcodeId)", GLOBAL_BOARD_UID);
		});
	}

	private function getCapcodeInputFromRequest(): array {
		// the tripcode/secure tripcode itself. Included with the diamond or star
		$rawTripcode = $_POST['rawTripcode'] ?? '';

		// validate the tripcode input
		$this->validateTripcode($rawTripcode);

		// whether its a secure tripcode or not - based on the trip symbol
		$isSecure = $this->isSecureTripcode($rawTripcode);

		// trim trip key from tripcode now that we know if its secure or not
		$tripcode = mb_substr($rawTripcode, 1);

		// the hexadecimal color
		$colorHex = $_POST['capcodeColorHex'] ?? '';

		// validate color
		$this->validateColorHexadecimal($colorHex);

		// the text that comes after the tripcode. e.g "## Pezident"
		$capcodeText = $_POST['capcodeText'] ?? '';

		return [$tripcode, $isSecure, $colorHex, $capcodeText];
	}

	private function validateTripcode(string $tripcode): void {
		// throw an exception if the tripcode is empty
		// as thats not a valid tripcode
		if(empty($tripcode)) {
			// throw the exception
			throw new BoardException("Tripcode not set!");
		}
		
		// get the trip key character
		$tripKey = _T('trip_pre');

		// get the secure trip code character
		$secureTripKey = _T('cap_char');

		// tripcode contains the tripkey character
		$containsTripkey = str_contains($tripcode, $tripKey);

		// contains the secure trip key character
		$containsSecureTripkey = str_contains($tripcode, $secureTripKey);

		// throw exception if neither of the tripkeys are present, or if both are present (both of the cases being invalid)
		if(($containsTripkey && $containsSecureTripkey) || (!$containsTripkey && !$containsSecureTripkey)) {
			// throw the exception
			throw new BoardException("Invalid tripkeys in tripcode!");
		}

		// throw exception if tripcode does not begin with a valid trip key
		if (!(str_starts_with($tripcode, $tripKey) || str_starts_with($tripcode, $secureTripKey))) {
			throw new BoardException("Tripcode must begin with a valid trip key!");
		}

		// get the length of the tripcode for validation
		$tripcodeLength = mb_strlen($tripcode);
		
		// throw an exception for an invalid tripcode if isn't 11 chars long
		// 1 trip key (either a ◆ or ★) + 10 code characters
		if($tripcodeLength !== 11) {
			// throw the exception
			throw new BoardException("Invalid tripcode entered! Make sure it includes the tripkey + code.");
		}

		// no exceptions tripped - tripcode valid. Continue.
	}

	private function isSecureTripcode(string $tripcode): bool {
		// get the secure trip code character
		$secureTripKey = _T('cap_char');

		// contains the secure trip key character
		$containsSecureTripkey = str_contains($tripcode, $secureTripKey);

		// tripcode contains the secure tripkey - then its a secure tripcode 
		if($containsSecureTripkey) {
			// return true since its a secure tripcode
			return true;
		} 
		// if it doesn't contain the secure tripkey - then its a regular tripcode
		else {
			// return false since its a regular tripcode. Meaning its not 'secure'
			return false;
		}
	}

	private function validateColorHexadecimal(string $color): bool {
		// ensure input is 3 or 6 hexadecimal characters, no '#'
		if (!preg_match('/^#(?:[A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/', $color)) {
			throw new BoardException("Invalid hexadecimal color value: $color");
		}

		return true;
	}

	private function handleCapcodeModify(): void {
        $this->handleCapcodeOperation('modifying', function() {
			// get the capcode id from POST
			$capcodeId = $_POST['capcodeId'] ?? null;

			// validate the id
			validateCapcodeId($capcodeId);

			// fetch the row
			$capcode = $this->capcodeService->getCapcode($capcodeId);

			// check if its not empty (meaning that it exists) 
			if(empty($capcode)) {
				// throw an exception to the user
				throw new BoardException("Capcode not found when modifying!");
			}

			// get the input from POST
			[$tripcode, $isSecure, $colorHex, $capcodeText] = $this->getCapcodeInputFromRequest();

			// get the fields from POST and assemble into an assoc array
			// each field corrosponds to a database column
			$data = [
				'tripcode' => $tripcode,
				'is_secure' => (int)$isSecure,
				'color_hex' => $colorHex,
				'cap_text' => $capcodeText
			];

			// pass the id and data array to a modify method that UPDATEs the row in the database
			$this->capcodeService->editCapcode($capcodeId, $data);

			// log the modification
			$this->actionLoggerService->logAction("Modified capcode ($capcodeId)", GLOBAL_BOARD_UID);
		});
	}

	private function handleCapcodeOperation(string $action, callable $callback): void {
		// init try-catch loop
		try {
			$callback();
		} 
		// catch and throw readable error
		catch (Throwable $e) {
			throw new BoardException($e->getMessage());
		}
	}
}