<?php

/*
	banner
	By: bobman (Yahoo! ^_^)
*/

namespace Kokonotsuba\Modules\banner;

require_once __DIR__ . '/bannerPreset.php';
require_once __DIR__ . '/bannerPresetRegistry.php';
require_once __DIR__ . '/bannerEntry.php';
require_once __DIR__ . '/bannerRepository.php';
require_once __DIR__ . '/bannerService.php';
require_once __DIR__ . '/bannerLib.php';

use Kokonotsuba\ban\banCheckpoint;
use Kokonotsuba\error\BoardException;
use Kokonotsuba\module_classes\abstractModuleMain;
use Kokonotsuba\module_classes\traits\BanCheckpointTrait;
use Kokonotsuba\module_classes\traits\listeners\AboveThreadsGlobalListenerTrait;
use Kokonotsuba\module_classes\traits\listeners\BelowThreadsGlobalListenerTrait;
use Kokonotsuba\module_classes\traits\listeners\IncludeScriptTrait;
use Kokonotsuba\module_classes\traits\listeners\PageTopListenerTrait;
use Kokonotsuba\module_classes\traits\listeners\TopLinksListenerTrait;

use function Kokonotsuba\libraries\_T;
use function Kokonotsuba\libraries\serveMedia;
use function Puchiko\request\redirect;
use function Puchiko\strings\sanitizeStr;
use function Kokonotsuba\libraries\html\drawPager;
use function Kokonotsuba\libraries\html\getPageFromRequest;

class moduleMain extends abstractModuleMain {
	use AboveThreadsGlobalListenerTrait;
	use BanCheckpointTrait;
	use BelowThreadsGlobalListenerTrait;
	use IncludeScriptTrait;
	use PageTopListenerTrait;
	use TopLinksListenerTrait;

	private readonly bool $showTopAd;
	private readonly bool $showBottomAd;
	private readonly bool $showBoardBanner;
	private readonly string $modulePageUrl;
	private readonly string $serveImageUrl;
	private readonly string $staticUrl;
	private bannerPresetRegistry $presets;
	private bannerService $bannerService;
	/** @var array<string, bool> preset key => whether anything is live under it */
	private array $hasBanners = [];

	public function getName(): string {
		return 'Kokonotsuba Banners';
	}

	public function getVersion(): string {
		return 'Twendy twendy sex';
	}

	public function initialize(): void {
		$this->presets = bannerPresetRegistry::fromConfig(
			fn (string $key, mixed $default): mixed => $this->getModuleConfig($key, $default)
		);
		$this->bannerService = getBannerService($this->moduleContext->transactionManager);

		$this->showTopAd = $this->getModuleConfig('SHOW_TOP_AD', true);
		$this->showBottomAd = $this->getModuleConfig('SHOW_BOTTOM_AD', true);
		$this->showBoardBanner = $this->getModuleConfig('SHOW_BOARD_BANNER', true);

		$request = $this->moduleContext->request;

		$this->modulePageUrl = $request->absoluteUrl($this->getModulePageURL([], false, false));
		$this->serveImageUrl = $this->presetPageUrl('bannerServeImage');
		$this->staticUrl = $request->absoluteUrl($this->getConfig('STATIC_URL'));

		if ($this->showBoardBanner) {
			$this->registerScript('banners.js');
			$this->listenPageTop('onRenderPageTop');
		}

		$this->listenAboveThreadsGlobal('onRenderAboveThreadArea');
		$this->listenBelowThreadsGlobal('onRenderBelowThreadArea');

		// How a reader reaches the banner listing and, where a preset takes them, its submit form.
		$this->addTopLink($this->modulePageUrl, _T('banner_toplink'));
	}

	/**
	 * A module endpoint URL, resolved to a real URL.
	 *
	 * WEBSITE_URL may be relative, in which case every URL built on it resolves against whatever
	 * document happens to hold it. These are used as an iframe source, as the image source inside
	 * that frame, and as redirect targets, so none of them can rely on the board page's directory.
	 */
	private function presetPageUrl(string $pageName, string $presetKey = ''): string {
		$params = ['pageName' => $pageName];
		if ($presetKey !== '') {
			$params['preset'] = $presetKey;
		}

		return $this->moduleContext->request->absoluteUrl($this->getModulePageURL($params, false, false));
	}

	/** Whether a preset has anything to draw. Asked at most once per preset per request. */
	private function hasBanners(string $presetKey): bool {
		return $this->hasBanners[$presetKey] ??= $this->bannerService->hasBanners($presetKey);
	}

	// Board banner, at the top of the page. Always drawn: the endpoint falls back to the
	// default image when no board banner has been uploaded yet.
	private function onRenderPageTop(string &$html): void {
		$preset = $this->presets->get(bannerPresetRegistry::BOARD);
		$src = $this->presetPageUrl('bannerRandom', $preset->key);

		// The invitation sits outside #bannerContainer, whose height is fixed to the image.
		$html .= '
      <div id="bannerContainer">
        <img width="' . $preset->width . '" height="' . $preset->height . '" src="' . sanitizeStr($src) . '" id="banner" title="Click to change">
      </div>' . $this->renderSuggestion($preset, 'boardbanner', 'self_serve_board_banner_suggest');
	}

	/**
	 * The "submit your own" line under a banner. Empty unless the board takes submissions for
	 * that preset.
	 *
	 * @param string $variant class-name prefix, so a theme can style each banner's line apart
	 * @param string $textKey language key for the wording
	 */
	private function renderSuggestion(bannerPreset $preset, string $variant, string $textKey): string {
		if (!$preset->allowSubmissions) {
			return '';
		}

		$submitUrl = $this->modulePageUrl . '&preset=' . urlencode($preset->key);

		return '<div class="' . $variant . 'SuggestionContainer centerText">
					<small class="' . $variant . 'Suggestion">
						<a class="' . $variant . 'SuggestionAnchor" href="' . sanitizeStr($submitUrl) . '">' . sanitizeStr(_T($textKey)) . '</a>
					</small>
				</div>';
	}

	private function renderBannerFrame(bannerPreset $preset): string {
		return '<iframe class="fullbannerIframe" title="Banner" src="' . sanitizeStr($this->presetPageUrl('bannerServer', $preset->key)) . '"></iframe>
				' . $this->renderSuggestion($preset, 'fullbanner', 'self_serve_banner_suggest') . '
				<hr class="hrAds">';
	}

	// Top ad
	private function onRenderAboveThreadArea(string &$aboveThreadsHtml): void {
		$preset = $this->presets->get(bannerPresetRegistry::AD);
		if ($this->showTopAd && $this->hasBanners($preset->key)) {
			$aboveThreadsHtml .= $this->renderBannerFrame($preset);
		}
	}

	// Bottom ad
	private function onRenderBelowThreadArea(string &$belowThreadsHtml): void {
		$preset = $this->presets->get(bannerPresetRegistry::AD);
		if ($this->showBottomAd && $this->hasBanners($preset->key)) {
			$belowThreadsHtml .= $this->renderBannerFrame($preset);
		}
	}

	private function handleBannerSubmission(bannerPreset $preset): void {
		if (!$preset->allowSubmissions) {
			throw new BoardException(_T('banner_submissions_disabled'));
		}

		$this->assertNotBanned(banCheckpoint::BANNER);

		$file = $this->moduleContext->request->getFile('banner_file');
		if (!$file) {
			throw new BoardException(_T('banner_no_file'));
		}

		$link = null;
		if ($preset->usesLink) {
			$link = trim($this->moduleContext->request->getParameter('banner_link', 'POST', '') ?? '');
			if ($link !== '' && !filter_var($link, FILTER_VALIDATE_URL)) {
				throw new BoardException(_T('banner_invalid_link'));
			}
			if ($link === '') {
				$link = null;
			}
		}

		$this->bannerService->submitBanner($preset, $file, $link, $this->moduleContext->request->getRemoteAddr());
	}

	private function handleRequests(): void {
		if ($this->moduleContext->request->getParameter('action', 'POST', '') !== 'submitBanner') {
			return;
		}

		$preset = $this->presets->resolve($this->moduleContext->request->getParameter('preset', 'POST', ''));

		try {
			$this->handleBannerSubmission($preset);
		} catch (BoardException $e) {
			$this->handleBannerIndexPage($preset, '<p class="warning">' . sanitizeStr($e->getMessage()) . '</p>');
			exit;
		}

		redirect($this->modulePageUrl . '&preset=' . urlencode($preset->key) . '&submittedBanner=1');
		exit;
	}

	/** The iframe a full banner ad is drawn in. */
	private function serveBannerFrame(bannerPreset $preset): void {
		$banner = $this->bannerService->getRandomActiveBanner($preset->key);

		if (!$banner) {
			echo '<!DOCTYPE html><html><body></body></html>';
			return;
		}

		$bannerImageUrl = $this->serveImageUrl . '&file=' . urlencode($banner->banner_file_name);
		$bannerLink = $banner->link ? sanitizeStr($banner->link) : '#';

		echo '<!DOCTYPE html>
		<html lang="en" style="overflow:hidden;">
			<head>
				<meta charset="UTF-8">
				<meta name="viewport" content="width=device-width, initial-scale=1.0">
				<title>Banner</title>
			</head>
			<body style="margin: 0;">
				<a href="' . $bannerLink . '" target="_blank"><img style="max-width: 100%;" src="' . sanitizeStr($bannerImageUrl) . '"></a>
			</body>
		</html>';
	}

	/**
	 * Redirect to a random banner image of one preset — what the board banner's <img> points at,
	 * so the switcher script gets a new one by re-requesting the same URL.
	 */
	private function redirectToRandomBanner(bannerPreset $preset): void {
		$banner = $this->bannerService->getRandomActiveBanner($preset->key);

		$url = $banner
			? $this->serveImageUrl . '&file=' . urlencode($banner->banner_file_name)
			: $this->staticUrl . 'image/default/defaultbanner.png';

		redirect($url);
	}

	private function serveBannerImage(): void {
		$fileName = $this->moduleContext->request->getParameter('file', 'GET', '');
		if ($fileName === '') {
			header("HTTP/1.0 400 Bad Request");
			exit;
		}

		$filePath = $this->bannerService->getBannerFilePath($fileName);
		if ($filePath === null) {
			header("HTTP/1.0 404 Not Found");
			exit;
		}

		// Cache for 1 hour
		header('Cache-Control: public, max-age=3600');
		header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 3600) . ' GMT');

		serveMedia($filePath);
	}

	private function handleBannerIndexPage(bannerPreset $preset, string $statusMessage = ''): void {
		$perPage = (int) $this->getConfig('ADMIN_PAGE_DEF', 100);
		$request = $this->moduleContext->request;

		$paginationData = $this->bannerService->getApprovedActivePage($preset->key, getPageFromRequest($request), $perPage);

		$presetUrl = $this->modulePageUrl . '&preset=' . urlencode($preset->key);
		$rows = array_map(
			fn ($b) => $b->toPublicTemplateRow($this->serveImageUrl, $preset, $this->moduleContext->postDateFormatter),
			$paginationData['items']
		);

		$paginationHtml = drawPager($paginationData['entriesPerPage'], $paginationData['totalEntries'], $presetUrl, $request);

		$templateValues = [
			'{$MODULE_PAGE_URL}' => sanitizeStr($presetUrl),
			'{$PRESET_KEY}' => sanitizeStr($preset->key),
			'{$PRESET_LABEL}' => sanitizeStr($preset->label()),
			'{$PRESET_NAV}' => renderPresetNav($this->presets, $this->modulePageUrl, $preset->key),
			'{$UPLOAD_HEADING}' => _T('banner_submit_heading'),
			'{$UPLOAD_BUTTON}' => _T('banner_submit_button'),
			'{$REQUIREMENTS}' => array_map(fn (string $rule): array => ['{$RULE}' => sanitizeStr($rule)], $preset->requirements()),
			'{$USES_LINK}' => $preset->usesLink ? '1' : '',
			'{$ALLOW_SUBMISSIONS}' => $preset->allowSubmissions ? '1' : '',
			'{$STATUS_MESSAGE}' => $statusMessage,
			'{$ROWS}' => $rows,
			'{$EMPTY}' => empty($rows) ? '1' : '',
		];

		$pageContent = $this->moduleContext->adminPageRenderer->ParseBlock('BANNER_INDEX', $templateValues);
		echo $this->moduleContext->adminPageRenderer->ParsePage('GLOBAL_ADMIN_PAGE_CONTENT', [
			'{$PAGE_CONTENT}' => $pageContent,
			'{$PAGER}' => $paginationHtml,
		], false);
	}

	private function handlePages(): void {
		$request = $this->moduleContext->request;
		$pageName = $request->getParameter('pageName', 'GET', '');
		$preset = $this->presets->resolve($request->getParameter('preset', 'GET', ''));

		if ($pageName === 'bannerServer') {
			$this->serveBannerFrame($preset);
			exit;
		}

		if ($pageName === 'bannerRandom') {
			$this->redirectToRandomBanner($preset);
			exit;
		}

		if ($pageName === 'bannerServeImage') {
			$this->serveBannerImage();
			exit;
		}

		$statusMessage = '';
		if ($request->getParameter('submittedBanner', 'GET', '') === '1') {
			$statusMessage = '<p>' . sanitizeStr(_T('banner_submit_success')) . '</p>';
		}

		$this->handleBannerIndexPage($preset, $statusMessage);
		exit;
	}

	public function ModulePage(): void {
		if ($this->moduleContext->request->isPost()) {
			$this->handleRequests();
		} else if ($this->moduleContext->request->isGet()) {
			$this->handlePages();
		}
	}
}
