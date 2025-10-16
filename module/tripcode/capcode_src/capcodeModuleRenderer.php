<?php

namespace Kokonotsuba\Modules\tripcode;

use board;
use BoardException;
use capcodeService;
use moduleEngine;
use pageRenderer;

class capcodeModuleRenderer {
	public function __construct(
		private moduleAdmin $moduleAdmin,
		private board $board,
		private moduleEngine $moduleEngine,
		private capcodeService $capcodeService,
		private pageRenderer $adminPageRenderer,
		private string $modulePageUrl
	) {}

	public function drawModPage(): void {
		// get pageName
		$pageName = $_GET['pageName'] ?? '';

		// handle capcode index
		// it can either be blank (default page) or be specified
		if($pageName === 'capcodeIndex' || $pageName === '') {
			$this->drawCapcodeIndex();
		}

		// handle capcode entry page 
		elseif($pageName === 'viewCapcode') {
			// get the capcode entry ID
			$capcodeId = $_GET['capcodeId'] ?? null;

			// cast as int
			$capcodeId = (int) $capcodeId;

			// validate capcode id
			validateCapcodeId($capcodeId);
			
			// draw capcode entry
			$this->drawCapcodeEntry($capcodeId);
		}

		// otherwise throw an exception for invalid page
		else {
			throw new BoardException("Page not found!");
		}
	}

	private function drawCapcodeIndex(): void {
		// generate all capcode placeholders
		$capcodeIndexPlaceholders = $this->generateCapcodeIndexPlaceholders();

		// generate the html
		$capcodeIndexHtml = $this->adminPageRenderer->ParseBlock('CAPCODE_INDEX', $capcodeIndexPlaceholders);
	
		// echo full page content
		$this->outputFinalPage($capcodeIndexHtml);
	}

	private function generateCapcodeIndexPlaceholders(): array {
		// get all capcodes
		$allCapcodes = $this->capcodeService->listCapcodes();

		// generate capcode table placeholders
		$capcodeTablePlaceholders = $this->generateCapcodeTablePlaceholders($allCapcodes);

		// get staff capcodes from config
		$staffCapcodes = $this->board->getConfigValue('staffCapcodes', []);

		// generate staff capcode placeholders
		$staffCapcodeTablePlaceholders = $this->generateStaffCapcodePlaceholders($staffCapcodes);

		// build index placeholders 
		$indexPlaceholders = [
			'{$CAPCODES}' => $capcodeTablePlaceholders,
			'{$STAFF_CAPCODES}' => $staffCapcodeTablePlaceholders,
			'{$ARE_NO_CAPCODES}' => empty($allCapcodes),
			'{$MODULE_URL}' => $this->modulePageUrl,
			'{$REGULAR_TRIP_KEY}' => _T('trip_pre'),
			'{$SECURE_TRIP_KEY}' => _T('cap_char')
		];

		// return table/index placeholders
		return $indexPlaceholders;
	}

	private function generateCapcodeTablePlaceholders(array $capcodes): array {
		// init capcode row placeholders array
		$capcodeTablePlaceholders = [];

		// loop over and build placeholder list
		foreach($capcodes as $cap) {
			// generate placeholders
			$placeholders = $this->generateCapcodePlaceholder($cap);

			// add to capcode table placeholders array
			$capcodeTablePlaceholders[] = $placeholders;
		}

		// return placeholders
		return $capcodeTablePlaceholders;
	}

	private function generateStaffCapcodePlaceholders(array $staffCapcodes): array {
		// init capcode row placeholders array
		$capcodeTablePlaceholders = [];

		// loop over and build placeholder list
		foreach($staffCapcodes as $key => $capcode) {
			// generate placeholders
			$placeholders = $this->generateStaffCapcodePlaceholder($capcode, $key);

			// add to capcode table placeholders array
			$capcodeTablePlaceholders[] = $placeholders;
		}

		// return placeholders
		return $capcodeTablePlaceholders;
	}

	private function generateStaffCapcodePlaceholder(array $staffCapcode, string $capcodeKey): array {
		// name placeholder for preview
		$namePlaceholder = $this->board->getConfigValue('DEFAULT_NONAME', 'System-chan');

		// generate capcode preview html
		$staffCapcodePreview = $this->generateStaffPreviewHtml($namePlaceholder, $capcodeKey);
		
		// readable indicator of the role required to use this capcode
		$requiredRole = $staffCapcode['requiredRole']->displayRoleName();

		// build staff capcode entry placeholders
		$staffCapcodePlaceholder = [
			'{$STAFF_CAPCODE_LABEL}' => htmlspecialchars($capcodeKey),
			'{$STAFF_CAPCODE_PREVIEW}' => $staffCapcodePreview,
			'{$STAFF_CAPCODE_REQUIRED_ROLE}' => $requiredRole,
		];

		// return placeholders
		return $staffCapcodePlaceholder;
	}

	private function generateStaffPreviewHtml(string $name, string $capcodeKey): string {
		// generate name html
		$postNameHtml = generatePostNameHtml(
			$this->moduleEngine,
			$name,
			'',
			'',
			$capcodeKey,
			''
		);

		// return the name html
		return $postNameHtml;
	}

	private function drawCapcodeEntry(int $capcodeId): void {
		// get capcode entry placeholders
		$capcodePlaceholders = $this->generateCapcodeEntryPlaceholders($capcodeId);

		// generate the html
		$capcodeEntryHtml = $this->adminPageRenderer->ParseBlock('CAPCODE_ENTRY', $capcodePlaceholders);
	
		// echo full page content
		$this->outputFinalPage($capcodeEntryHtml);
	}

	private function generateCapcodeEntryPlaceholders(int $capcodeId): array {
		// fetch the capcode row
		$capcode = $this->capcodeService->getCapcode($capcodeId);

		// throw error if it doesn't exist
		if(empty($capcode)) {
			throw new BoardException("Capcode not found!");
		}

		// get placeholders
		$capcodePlaceholder = $this->generateCapcodePlaceholder($capcode);

		// return placeholders
		return $capcodePlaceholder;
	}

	private function generateCapcodePlaceholder(array $capcode): array {
		// capcode id
		$id = $capcode['id'];

		// capcode added by
		$addedByUsername = $capcode['added_by_username'];

		// capcode date added
		$dateAdded = $capcode['date_added'];

		// capcode is secure
		$isSecure = $capcode['is_secure'];

		// generate trip key
		$tripKey = $isSecure ? _T('cap_char') : _T('trip_pre');

		// capcode tripcode
		$tripcode = $capcode['tripcode'];

		// capcode color
		$capcodeColor = $capcode['color_hex'];

		// capcode text
		$capcodeText = $capcode['cap_text'];

		// get placeholder name from config
		$placeHolderName = $this->board->getConfigValue('DEFAULT_NONAME', 'System-chan');

		// generate capcode preview
		$capcodePreviewHtml = $this->generateCapcodePreviewHtml(
			$placeHolderName,
			$tripcode, 
			$isSecure
		);

		// generate view url
		$viewUrl = $this->generateCapcodeViewUrl($id);

		// build placeholder array
		$capcodePlaceholders = [
			'{$ID}' => htmlspecialchars($id),
			'{$ADDED_BY_USERNAME}' => htmlspecialchars($addedByUsername),
			'{$DATE_ADDED}' => htmlspecialchars($dateAdded),
			'{$IS_SECURE}' => $isSecure === 1,
			'{$TRIP_KEY}' => htmlspecialchars($tripKey),
			'{$TRIPCODE}' => htmlspecialchars($tripcode),
			'{$CAPCODE_COLOR}' => htmlspecialchars($capcodeColor),
			'{$CAPCODE_TEXT}' => htmlspecialchars($capcodeText),
			'{$PREVIEW}' => $capcodePreviewHtml, // premade html - dont sanitize or it'll break the html 
			'{$VIEW_ENTRY_URL}' => htmlspecialchars($viewUrl),
			'{$MODULE_URL}' => $this->modulePageUrl
		];

		// return the placeholders
		return $capcodePlaceholders;
	}

	private function generateCapcodeViewUrl(int $id): string {
		// set parameters
		$urlParameters = [
			'capcodeId' => $id,
			'pageName' => 'viewCapcode'
		];

		// generate the url with module url method
		$modulePageUrl = $this->moduleAdmin->getModulePageURL($urlParameters, false);

		// return the module page url
		return $modulePageUrl;
	}

	private function generateCapcodePreviewHtml(
		string $placeHolderName,
		string $tripcode, 
		bool $isSecure
	): string {
		// regular tripcode
		$regularTripcode = $isSecure ? '' : $tripcode;

		// secure tripcode
		$secureTripcode = $isSecure ? $tripcode : '';

		// generate the post name
		$postNameHtml = generatePostNameHtml(
			$this->moduleEngine,
			$placeHolderName,
			$regularTripcode,
			$secureTripcode,
			'',
			'',
			false
		);

		// return preview
		return $postNameHtml;
	}

	private function outputFinalPage(string $pageContent): void {
		// generate full admin page
		$pageHtml = $this->adminPageRenderer->ParsePage('GLOBAL_ADMIN_PAGE_CONTENT', 
			['{$PAGE_CONTENT}' => $pageContent], true
		);

		// output html
		echo $pageHtml;
	}
}