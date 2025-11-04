<?php

/*
	fullBanner
	By: bobman (Yahoo! ^_^)
*/

namespace Kokonotsuba\Modules\fullBanner;

use Kokonotsuba\ModuleClasses\abstractModuleMain;

class moduleMain extends abstractModuleMain {
	private readonly bool $showTopAd;
	private readonly bool $showBottomAd;
	private readonly string $modulePageUrl;
	
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
		$this->modulePageUrl = $this->getModulePageURL([], false, false);

		$this->moduleContext->moduleEngine->addListener('AboveThreadArea', function (string &$aboveThreadsHtml) {
			$this->onRenderAboveThreadArea($aboveThreadsHtml);  // Call the method to modify the form
		});
		
		$this->moduleContext->moduleEngine->addListener('BelowThreadArea', function (string &$belowThreadsHtml) {
			$this->onRenderBelowThreadArea($belowThreadsHtml);  // Call the method to modify the form
		});
	}

	// Top Ad
	private function onRenderAboveThreadArea(string &$aboveThreadsHtml): void {		
		if ($this->showTopAd) { // Check if top ad is enabled
			$aboveThreadsHtml .= '<iframe id="fullbannerIframeTop" class="fullbannerIframe" title="Banner" src="' . htmlspecialchars($this->modulePageUrl) . '"></iframe><hr class="hrAds">';
		}
	}

	// Bottom Ad
	private function onRenderBelowThreadArea(string &$belowThreadsHtml): void {
		if ($this->showBottomAd) { // Check if bottom ad is enabled
			$belowThreadsHtml .= '<iframe id="fullbannerIframeBottom" class="fullbannerIframe" title="Banner" src="' . htmlspecialchars($this->modulePageUrl) . '"></iframe><hr class="hrAds">';
		}
	}

	public function ModulePage(): void {
		// get the banner ad array from config
		$bannerAds = $this->getConfig('ModuleSettings.BANNER_ADS');

		// return early if there are no banner ads in config
		if(!$bannerAds) {
			return;
		}
		
		// get a random file name from the banner ad array
		$randomBannerAdName = array_rand($bannerAds);

		// get static url
		$staticUrl = $this->getConfig('STATIC_URL', 'static/');

		// assemble the banner ad url
		$bannerAdImageUrl = $staticUrl . 'image/fullbanners/' . $randomBannerAdName;

		// get the link the banner ad leads to
		$bannerAdLink = $bannerAds[$randomBannerAdName];

		echo '<!DOCTYPE html>
		<html lang="en" style="overflow:hidden;">
			<head>
				<meta charset="UTF-8">
				<meta name="viewport" content="width=device-width, initial-scale=1.0">
				<title>Full banner</title>
			</head>
			<body style="margin: 0;">
				<a href="' . $bannerAdLink . '" target="_blank"><img style="max-width: 100%;" src="'.$bannerAdImageUrl.'">
			</body>
		</html>';
	}
}