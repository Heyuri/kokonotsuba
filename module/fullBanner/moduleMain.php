<?php

/*
	mod_ads.php
	By: bobman (Yahoo! ^_^)
*/

namespace Kokonotsuba\Modules\fullBanner;

use Kokonotsuba\ModuleClasses\abstractModuleMain;

class moduleMain extends abstractModuleMain {
	private readonly bool $showTopAd;
	private readonly bool $showBottomAd;
	private readonly string $staticUrl;
	
	// Names
	public function getName(): string {
		return 'Kokonotsuba Full Banners';
	}

	public function getVersion(): string {
		return 'Kokonotsuba 2025';
	}

	public function initialize(): void {
		$this->showTopAd = $this->getConfig('ModuleSettings.SHOW_TOP_AD');
		$this->showBottomAd = $this->getConfig('ModuleSettings.SHOW_BOTTOM_AD');
		$this->staticUrl = $this->getConfig('STATIC_URL');

		$this->moduleContext->moduleEngine->addListener('AboveThreadArea', function (string &$aboveThreadsHtml) {
			$this->onRenderAboveThreadArea($aboveThreadsHtml);  // Call the method to modify the form
		});
		
		$this->moduleContext->moduleEngine->addListener('BelowThreadArea', function (string &$belowThreadsHtml) {
			$this->onRenderBelowThreadArea($belowThreadsHtml);  // Call the method to modify the form
		});
	}

	// Top Ad
	public function onRenderAboveThreadArea(string &$aboveThreadsHtml): void {		
		if ($this->showTopAd) { // Check if top ad is enabled
			$aboveThreadsHtml .= '<iframe id="fullbannerIframeTop" class="fullbannerIframe" title="Banner" src="' . $this->staticUrl . 'image/fullbanners/fullbanners.php"></iframe><hr class="hrAds">'."\n";
		}
	}

	// Bottom Ad
	public function onRenderBelowThreadArea(string &$belowThreadsHtml): void {
		if ($this->showBottomAd) { // Check if bottom ad is enabled
			$belowThreadsHtml .= '<iframe id="fullbannerIframeBottom" class="fullbannerIframe" title="Banner" src="' . $this->staticUrl . 'image/fullbanners/fullbanners.php"></iframe><hr class="hrAds">'."\n";
		}
	}
	
}
