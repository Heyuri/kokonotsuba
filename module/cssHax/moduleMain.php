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

		// extract board uid
		$boardUID = $threadData['boardUID'] ?? '';

		// init text bg
		$textBg = '';

		// thread background color
		if (!empty($threadData['background_hex_color']) && $threadData['background_hex_color'] !== "#000000") {
			$styleAttributes[] =
				'background-color: ' . sanitizeStr($threadData['background_hex_color']);
			
			// set the background color of the text to the default bg so the OP can be read more easily
			$textBg = "#p{$boardUID}_{$threadNumber} .comment { background-color: var(--color-bg-main) }";
		}

		// thread text color
		if (!empty($threadData['text_hex_color']) && $threadData['text_hex_color'] !== "#000000") {
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

		// assemble the reply bg color attribute
		$replyBackgroundAttribute = 
			(!empty($threadData['reply_background_hex_color']) && $threadData['reply_background_hex_color'] !== "#000000") 
			? 'background-color: ' . sanitizeStr($threadData['reply_background_hex_color'])
			: '';

		// wrap attributes in id style block as well as the reply style, then return
		return "#t{$boardUID}_{$threadNumber} { $collapsedAttributes }"
				. "#t{$boardUID}_{$threadNumber} .reply { $replyBackgroundAttribute }"
				. $textBg;
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