<?php

/*
	fullBanner
	By: bobman (Yahoo! ^_^)
*/

namespace Kokonotsuba\Modules\fullBanner;

use Kokonotsuba\module_classes\abstractModuleMain;
use Kokonotsuba\module_classes\listeners\AboveThreadAreaListenerTrait;
use Kokonotsuba\module_classes\listeners\BelowThreadAreaListenerTrait;

use function Kokonotsuba\libraries\_T;
use function Puchiko\request\isGetRequest;
use function Puchiko\request\isPostRequest;
use function Puchiko\strings\sanitizeStr;

class moduleMain extends abstractModuleMain {
	use AboveThreadAreaListenerTrait;
	use BelowThreadAreaListenerTrait;

	private readonly bool $showTopAd;
	private readonly bool $showBottomAd;
	private readonly string $modulePageUrl, $bannerServerUrl;
	
	// Names
	public function getName(): string {
		return 'Kokonotsuba Full Banners';
	}

	public function getVersion(): string {
		return 'Twendy twendy sex';
	}

	public function initialize(): void {
		$this->showTopAd = $this->getConfig('ModuleSettings.SHOW_TOP_AD');
		$this->showBottomAd = $this->getConfig('ModuleSettings.SHOW_BOTTOM_AD');
		$this->modulePageUrl = $this->getModulePageURL(['page' => 'bannerIndex'], false, false);
		$this->bannerServerUrl = $this->getModulePageURL(['page' => 'bannerServer'], false, false);

		$this->listenAboveThreadArea('onRenderAboveThreadArea');
		$this->listenBelowThreadArea('onRenderBelowThreadArea');
	}

	private function renderBannerFrame(): string {
		return '<iframe class="fullbannerIframe" title="Banner" src="' . htmlspecialchars($this->bannerServerUrl) . '"></iframe>
				<div class="fullbannerSuggestionContainer centerText">
					<small class="fullbannerSuggestion">
						<a class="fullbannerSuggestionAnchor" href="' . sanitizeStr($this->modulePageUrl) . '">' . sanitizeStr(_T('self_serve_banner_suggest')) . '</a>
					</small>
				</div>
				<hr class="hrAds">';
	}

	// Top Ad
	private function onRenderAboveThreadArea(string &$aboveThreadsHtml): void {		
		if ($this->showTopAd) { // Check if top ad is enabled
			$aboveThreadsHtml .= $this->renderBannerFrame();
		}
	}

	// Bottom Ad
	private function onRenderBelowThreadArea(string &$belowThreadsHtml): void {
		if ($this->showBottomAd) { // Check if bottom ad is enabled
			$belowThreadsHtml .= $this->renderBannerFrame();
		}
	}

	private function handleRequests(): void {
		// get the action
		$action = $_POST['action'] ?? '';

		// handle banner submission
		if($action === 'submitBanner') {

		}
	}

	private function serveBanners(): void {
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
				<a href="' . $bannerAdLink . '" target="_blank"><img style="max-width: 100%;" src="'.$bannerAdImageUrl.'"></a>
			</body>
		</html>';
	}

	private function getBanners(): array {
		return $this->getConfig('ModuleSettings.BANNER_ADS') ?? [];
	}

	private function renderBannerList(): string {
		// get the banner ad array from config
		$bannerAds = $this->getBanners();

		// return early if there are no banner ads in config
		if(!$bannerAds) {
			return '<p>' . _T('no_banners_configured') . '</p>';
		}

		// render the banner ad list
		$bannerListHtml = '';

		// build template values for each banner
		$bannerRowTemplateValues = $this->buildRowTemplateValues($bannerAds);

		// now render the list
		$bannerListHtml = $this->moduleContext->adminPageRenderer->ParseBlock('BANNER_LIST', $bannerRowTemplateValues);

		return $bannerListHtml;
	}

	private function handleBannerIndexPage(): void {
		// generate banner creation form
		$formHtml = $this->moduleContext->adminPageRenderer->ParseBlock('BANNER_INDEX_FORM', [
			'{$MODULE_PAGE_URL}' => $this->modulePageUrl
		]);

		// generate the banner list
		$bannerList = $this->renderBannerList();

		// assemble page content
		$pageContent = $formHtml . $bannerList;

		// render the page
		echo $this->moduleContext->adminPageRenderer->ParsePage('GLOBAL_ADMIN_PAGE_CONTENT', ['{$PAGE_CONTENT}' => $pageContent], false);
	}

	private function handlePages(): void {
		// get the page
		$page = $_GET['page'] ?? '';

		// serve the banner server page
		if($page === 'bannerServer') {
			$this->serveBanners();
			exit;
		}
		// serve the banner index page
		else if($page === 'bannerIndex') {
			$this->handleBannerIndexPage();
			exit;
		}
	}

	public function ModulePage(): void {
		if(isPostRequest()) {
			// handle requests (adds, edits, removals, approvals, etc.)
			$this->handleRequests();
		}
		// handle GET requests to the module page (for the banner server)
		else if(isGetRequest()) {
			$this->handlePages();
		}
	}
}