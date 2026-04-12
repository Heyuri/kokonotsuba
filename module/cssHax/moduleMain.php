<?php

namespace Kokonotsuba\Modules\cssHax;

use Kokonotsuba\module_classes\abstractModuleMain;
use Kokonotsuba\module_classes\traits\listeners\ModuleHeaderListenerTrait;
use Kokonotsuba\module_classes\traits\listeners\ModuleThreadHeaderListenerTrait;
use Kokonotsuba\thread\Thread;

use function Puchiko\strings\sanitizeStr;

class moduleMain extends abstractModuleMain {
	use ModuleThreadHeaderListenerTrait;
	use ModuleHeaderListenerTrait;

	private array $collectedStyles = [];

	public function getName(): string {
		return 'Thread css hax handler';
	}

	public function getVersion(): string  {
		return 'TWENTY TWENTY SEX BABY';
	}

 	public function initialize(): void {
		$this->listenModuleThreadHeader('onModuleThreadHeader');
		$this->listenModuleHeader('onModuleHeader');
	}

	private function onModuleThreadHeader(string &$threadHeader, Thread $threadData): void {
		// collect css styling for this thread to be rendered in <head>
		$this->collectThreadStyle($threadData);

		// generate thread audio tags (these stay in the body)
		$threadHeader .= $this->generateThreadAudio($threadData);
	}

	private function onModuleHeader(string &$moduleHeader): void {
		// inject all collected thread styles into the <head>
		if (!empty($this->collectedStyles)) {
			$moduleHeader .= '<style>' . implode('', $this->collectedStyles) . '</style>';
			$this->collectedStyles = [];
		}
	}

	private function buildStyleAttributes(Thread $threadData, int $threadNumber): string {
		$styleAttributes = [];

		// extract board uid
		$boardUID = $threadData->getBoardUID() ?? '';

		// init text bg
		$textBg = '';

		// thread background color
		if (!empty($threadData->getBackgroundColor()) && $threadData->getBackgroundColor() !== "#000000") {
			$styleAttributes[] =
				'background-color: ' . sanitizeStr($threadData->getBackgroundColor());
		}

		// thread text color
		if (!empty($threadData->getTextColor()) && $threadData->getTextColor() !== "#000000") {
			$styleAttributes[] =
				'color: ' . sanitizeStr($threadData->getTextColor());
		}

		// thread background image
		if (!empty($threadData->getBackgroundImageUrl())) {
			$styleAttributes[] =
				'background-image: url(\'' .
				sanitizeStr($threadData->getBackgroundImageUrl()) .
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
			(!empty($threadData->getReplyBackgroundColor()) && $threadData->getReplyBackgroundColor() !== "#000000") 
			? 'background-color: ' . sanitizeStr($threadData->getReplyBackgroundColor())
			: '';


		// handle text bg for visibility
		if(!empty($threadData->getBackgroundColor()) && $threadData->getBackgroundColor() !== "#000000"
			|| !empty($threadData->getBackgroundImageUrl())) {
			// set the background color of the text to the default bg so the OP can be read more easily
			$textBg = "#p{$boardUID}_{$threadNumber} .comment { background-color: var(--color-bg-main) }";
		}

		// wrap attributes in id style block as well as the reply style, then return
		return "#t{$boardUID}_{$threadNumber} { $collapsedAttributes }"
				. "#t{$boardUID}_{$threadNumber} .reply { $replyBackgroundAttribute }"
				. $textBg;
	}

	private function collectThreadStyle(Thread $threadData): void {
		// build attributes
		$styleBlock = $this->buildStyleAttributes($threadData, $threadData->getOpNumber());

		// also pull the raw CSS if any exists
		$rawStyling = htmlspecialchars($threadData->getRawStyling());

		// collect styling to be rendered in <head> later
		$combined = $styleBlock . $rawStyling;
		if (!empty($combined)) {
			$this->collectedStyles[] = $combined;
		}
	}

	private function generateThreadAudio(Thread $threadData): string {
		// return empty string if the thread doesn't have an audio URL
		if(!$threadData->getAudio()) {
			return '';
		}

		// only use audio if the user is currently viewing a thread
		if($this->moduleContext->request->isViewingThread() === false) {
			return '';
		}

		// return generated audio tag with autoplay
		return '<audio autoplay loop src="' . sanitizeStr($threadData->getAudio()) . '"></audio>';
	}
}