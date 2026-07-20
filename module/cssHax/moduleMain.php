<?php

namespace Kokonotsuba\Modules\cssHax;

use Kokonotsuba\module_classes\abstractModuleMain;
use Kokonotsuba\module_classes\traits\listeners\ModuleHeaderListenerTrait;
use Kokonotsuba\module_classes\traits\listeners\ModuleThreadHeaderListenerTrait;
use Kokonotsuba\thread\Thread;

use function Puchiko\strings\sanitizeStr;

require_once __DIR__ . '/styleCollector.php';

class moduleMain extends abstractModuleMain {
	use ModuleThreadHeaderListenerTrait;
	use ModuleHeaderListenerTrait;

	private const STYLE_COLLECTOR_SERVICE = 'cssHaxStyleCollector';

	private styleCollector $styleCollector;

	public function getName(): string {
		return 'Thread css hax handler';
	}

	public function getVersion(): string  {
		return 'TWENTY TWENTY SEX BABY';
	}

 	public function initialize(): void {
		$this->styleCollector = $this->resolveStyleCollector();

		$this->listenModuleThreadHeader('onModuleThreadHeader');
		$this->listenModuleHeader('onModuleHeader');
	}

	/*
	* Every module engine on the page shares one collector.
	*
	* The overboard renders each board's threads through its own engine, so without a
	* shared collector the instance that gathers the styling is never the instance that
	* writes the head.
	*/
	private function resolveStyleCollector(): styleCollector {
		$container = $this->moduleContext->getContainer();

		if (!$container->has(self::STYLE_COLLECTOR_SERVICE)) {
			$container->set(self::STYLE_COLLECTOR_SERVICE, new styleCollector());
		}

		return $container->get(self::STYLE_COLLECTOR_SERVICE);
	}

	private function onModuleThreadHeader(string &$threadHeader, Thread $threadData, bool $isThreadView): void {
		// collect css styling for this thread to be rendered in <head>
		$this->collectThreadStyle($threadData, $isThreadView);

		// generate thread audio tags (these stay in the body)
		$threadHeader .= $this->generateThreadAudio($threadData, $isThreadView);
	}

	private function onModuleHeader(string &$moduleHeader): void {
		// inject all collected thread styles into the <head>
		if (!$this->styleCollector->isEmpty()) {
			$moduleHeader .= '<style>' . $this->styleCollector->flush() . '</style>';
		}
	}

	// the colour pickers always submit a value, so pure black means "not set"
	private function isColorSet(?string $color): bool {
		return !empty($color) && $color !== "#000000";
	}

	private function hasBackgroundImage(Thread $threadData): bool {
		return !empty($threadData->getBackgroundImageUrl());
	}

	// the declarations that paint the thread, shared by both style targets
	private function buildBackgroundDeclarations(Thread $threadData, bool $isThreadView): array {
		$styleAttributes = [];

		// thread background color
		if ($this->isColorSet($threadData->getBackgroundColor())) {
			$styleAttributes[] =
				'background-color: ' . sanitizeStr($threadData->getBackgroundColor());
		}

		// thread text color
		if ($this->isColorSet($threadData->getTextColor())) {
			$styleAttributes[] =
				'color: ' . sanitizeStr($threadData->getTextColor());
		}

		// thread background image
		if ($this->hasBackgroundImage($threadData)) {
			$styleAttributes[] =
				'background-image: url(\'' .
				sanitizeStr($threadData->getBackgroundImageUrl()) .
				'\')';

			// board themes paint their own tiled/stretched background, so pin ours down
			$styleAttributes[] = 'background-repeat: repeat';
			$styleAttributes[] = 'background-size: auto 300px';
		}
		// several board themes lay a gradient over the page background, which would
		// cover a flat colour - clear it when the thread owns the whole page
		else if ($isThreadView && $this->isColorSet($threadData->getBackgroundColor())) {
			$styleAttributes[] = 'background-image: none';
		}

		return $styleAttributes;
	}

	private function buildStyleAttributes(Thread $threadData, int $threadNumber, bool $isThreadView): string {
		// extract board uid
		$boardUID = $threadData->getBoardUID() ?? '';

		// init text bg
		$textBg = '';

		// collect the declarations that paint the background
		$styleAttributes = $this->buildBackgroundDeclarations($threadData, $isThreadView);

		// nothing to apply
		if (empty($styleAttributes)) {
			return '';
		}

		// implode with semi-colons
		$collapsedAttributes = implode('; ', $styleAttributes);

		// when the thread is being viewed on its own the styling belongs to the whole page,
		// otherwise it stays scoped to this thread's container in the listing
		$styleTarget = $isThreadView ? 'body' : "#t{$boardUID}_{$threadNumber}";

		// only style the replies when a colour was actually picked for them,
		// otherwise they keep the board theme's own reply background
		$replyBackground = $this->isColorSet($threadData->getReplyBackgroundColor())
			? "#t{$boardUID}_{$threadNumber} .reply { background-color: "
				. sanitizeStr($threadData->getReplyBackgroundColor()) . " }"
			: '';

		// handle text bg for visibility
		// a flat background colour stays readable on its own, so only an image
		// gets a backdrop - otherwise it would hide the background it sits on
		if($this->hasBackgroundImage($threadData)) {
			// set the background color of the text to the default bg so the OP can be read more easily
			$textBg = "#p{$boardUID}_{$threadNumber} .comment { background-color: var(--color-bg-main) }";
		}

		// wrap attributes in the style block as well as the reply style, then return
		return "{$styleTarget} { $collapsedAttributes }"
				. $replyBackground
				. $textBg;
	}

	private function collectThreadStyle(Thread $threadData, bool $isThreadView): void {
		// build attributes
		$styleBlock = $this->buildStyleAttributes($threadData, $threadData->getOpNumber(), $isThreadView);

		// also pull the raw CSS if any exists
		$rawStyling = htmlspecialchars($threadData->getRawStyling() ?? '');

		// collect styling to be rendered in <head> later
		$this->styleCollector->add($styleBlock . $rawStyling);
	}

	private function generateThreadAudio(Thread $threadData, bool $isThreadView): string {
		// return empty string if the thread doesn't have an audio URL
		if(!$threadData->getAudio()) {
			return '';
		}

		// only use audio if the user is currently viewing a thread
		if(!$isThreadView) {
			return '';
		}

		// return generated audio tag with autoplay
		return '<audio autoplay loop src="' . sanitizeStr($threadData->getAudio()) . '"></audio>';
	}
}
