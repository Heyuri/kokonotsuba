<?php

namespace Kokonotsuba\Modules\cssHax;

use Kokonotsuba\module_classes\abstractModuleMain;

use function Puchiko\strings\sanitizeStr;

class moduleMain extends abstractModuleMain {
	public function getName(): string {
		return 'Thread css hax handler';
	}

	public function getVersion(): string  {
		return 'TWENTY TWENTY SEX BABY';
	}

 	public function initialize(): void {
		$this->moduleContext->moduleEngine->addListener('ModuleThreadHeader', function(string &$threadHeader, array $threadData) {
			$this->onModuleThreadHeader($threadHeader, $threadData);
		});
	}

	private function onModuleThreadHeader(string &$threadHeader, array $threadData): void {
		// generate and append the css styling for this thread
		$threadHeader .= $this->generateThreadStyle($threadData);

		// generate thread audio tags
		$threadHeader .= $this->generateThreadAudio($threadData);
	}

	private function buildStyleAttributes(array $threadData, int $threadNumber): string {
		$styleAttributes = [];

		// thread background color
		if (!empty($threadData['background_hex_color'])) {
			$styleAttributes[] =
				'background-color: ' . sanitizeStr($threadData['background_hex_color']);
		}

		// thread text color
		if (!empty($threadData['text_hex_color'])) {
			$styleAttributes[] =
				'color: ' . sanitizeStr($threadData['text_hex_color']);
		}

		// thread background image
		if (!empty($threadData['background_image_url'])) {
			$styleAttributes[] =
				'background-image: url(\'' .
				sanitizeStr($threadData['background_image_url']) .
				'\')';

			// also add a background option to prevent it
			$styleAttributes[] = 'background-size: auto 300px;';
		}

		// nothing to apply
		if (empty($styleAttributes)) {
			return '';
		}

		// implode with semi-colons
		$collapsedAttributes = implode('; ', $styleAttributes);

		// extract board uid
		$boardUID = $threadData['boardUID'] ?? '';

		// wrap attributes in id style block and return
		return "#t{$boardUID}_{$threadNumber} { $collapsedAttributes }";
	}

	private function generateThreadStyle(array $threadData): string {
		// build attributes
		$styleBlock = $this->buildStyleAttributes($threadData, $threadData['post_op_number']);

		// also pull the raw CSS if any exists
		$rawStyling = htmlspecialchars($threadData['raw_styling']);

		// return concatinated styling wrapped in raw style tags
		return '<style>' . $styleBlock . $rawStyling . '</style>';
	}

	private function generateThreadAudio(array $threadData): string {
		// return empty string if the thread doesn't have an audio URL
		if(!$threadData['audio']) {
			return '';
		}

		// only use audio if the user is currently viewing a thread
		if(!isset($_GET['res'])) {
			return '';
		}

		// return generated audio tag with autoplay
		return '<audio autoplay loop src="' . sanitizeStr($threadData['audio']) . '"></audio>';
	}
}