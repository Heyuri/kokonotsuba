<?php

namespace Kokonotsuba\Modules\ads;

require_once __DIR__ . '/adEntry.php';
require_once __DIR__ . '/adRepository.php';

use Kokonotsuba\database\databaseConnection;
use Kokonotsuba\module_classes\abstractModuleMain;
use Kokonotsuba\module_classes\traits\listeners\AboveThreadsGlobalListenerTrait;
use Kokonotsuba\module_classes\traits\listeners\BelowThreadsGlobalListenerTrait;
use Kokonotsuba\module_classes\traits\listeners\FootListenerTrait;
use Kokonotsuba\module_classes\traits\listeners\IncludeHtmlTrait;
use Kokonotsuba\module_classes\traits\listeners\IncludeScriptTrait;
use Kokonotsuba\module_classes\traits\listeners\PageTopListenerTrait;
use Kokonotsuba\module_classes\traits\listeners\PostSeparateListenerTrait;
use Kokonotsuba\module_classes\traits\listeners\ThreadSeparateListenerTrait;

use function Kokonotsuba\libraries\html\generatePostNameHtml;
use function Puchiko\json\renderJsonPage;
use function Puchiko\strings\sanitizeStr;

class moduleMain extends abstractModuleMain {
	use IncludeScriptTrait;
	use IncludeHtmlTrait;

	private int $adPostCounter = 0;
	use PageTopListenerTrait;
	use FootListenerTrait;
	use AboveThreadsGlobalListenerTrait;
	use BelowThreadsGlobalListenerTrait;
	use ThreadSeparateListenerTrait;
	use PostSeparateListenerTrait;

	private string $modulePageUrl;
	private ?adRepository $adRepo = null;

	public function getName(): string {
		return 'Ads Module';
	}

	public function getVersion(): string {
		return '1.0';
	}

	public function initialize(): void {
		$this->modulePageUrl = $this->getModulePageURL([], false, false);

		$this->registerScript('pageMedia.js');

		$stickyAds = $this->getAdsForSlot('sticky');
		if (!empty($stickyAds)) {
			$adsHtml = array_map(fn($ad) => $this->buildAdHtml($ad), $stickyAds);
			$adsJson = json_encode(array_values($adsHtml), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
			$this->registerHeaderHtml($this->generateTemplate(
				'pmStickyTpl',
				'<div class="pmStickyWrap" data-ads="' . $adsJson . '"><button type="button" class="pmStickyClose" aria-label="Close">&times;</button><div class="pmStickyContent centerText">' . $adsHtml[0] . '</div></div>'
			));
		}

		$this->listenPageTop('onRenderPageTop');
		$this->listenFoot('onRenderFoot');
		$this->listenAboveThreadsGlobal('onRenderAboveThreads');
		$this->listenBelowThreadsGlobal('onRenderBelowThreads');
		$this->listenThreadSeparate('onRenderThreadSeparate');
		$this->listenPostSeparate('onRenderPostSeparate');
	}

	private function onRenderAboveThreads(string &$html): void {
		$ads = $this->getAdsForSlot('above');
		if (!empty($ads)) {
			$dims = $this->getSlotDimensions('above');
			$html .= $this->moduleContext->adminPageRenderer->ParseBlock('ADS_INLINE_SLOT', [
				'{$STYLE}' => '--pm-slot-width:' . (int)$dims['width'] . 'px;--pm-slot-height:' . (int)$dims['height'] . 'px;',
				'{$SRC}'   => sanitizeStr($this->buildFrameUrl('above')),
			]);
		}

		$mobileAds = $this->getAdsForSlot('mobile');
		if (!empty($mobileAds)) {
			$mobileDims = $this->getSlotDimensions('mobile');
			$html .= $this->moduleContext->adminPageRenderer->ParseBlock('ADS_MOBILE_INLINE_SLOT', [
				'{$STYLE}' => '--pm-slot-width:' . (int)$mobileDims['width'] . 'px;--pm-slot-height:' . (int)$mobileDims['height'] . 'px;',
				'{$SRC}'   => sanitizeStr($this->buildFrameUrl('mobile')),
			]);
		}
	}

	private function onRenderBelowThreads(string &$html): void {
		$ads = $this->getAdsForSlot('below');
		if (empty($ads)) {
			return;
		}
		$dims = $this->getSlotDimensions('below');
		$html .= $this->moduleContext->adminPageRenderer->ParseBlock('ADS_INLINE_SLOT', [
			'{$STYLE}' => '--pm-slot-width:' . (int)$dims['width'] . 'px;--pm-slot-height:' . (int)$dims['height'] . 'px;',
			'{$SRC}'   => sanitizeStr($this->buildFrameUrl('below')),
		]);
	}

	private function onRenderThreadSeparate(string &$html, int $threadIterator): void {
		$inlineEvery = max(1, (int)$this->getConfig('ModuleSettings.ADS_INLINE_EVERY_N_THREADS', 4));
		if (($threadIterator + 1) % $inlineEvery !== 0) {
			return;
		}
		$ads = $this->getAdsForSlot('inline');
		if (empty($ads)) {
			return;
		}
		$count = min(5, max(1, (int)$this->getConfig('ModuleSettings.ADS_INLINE_COUNT', 3)));
		$dims = $this->getSlotDimensions('inline');
		$style = '--pm-slot-width:' . (int)$dims['width'] . 'px;--pm-slot-height:' . (int)$dims['height'] . 'px;';
		$frameUrl = sanitizeStr($this->buildFrameUrl('inline'));
		$slots = '';
		for ($i = 0; $i < $count; $i++) {
			$slots .= $this->moduleContext->adminPageRenderer->ParseBlock('ADS_INLINE_SLOT_ITEM', [
				'{$STYLE}' => $style,
				'{$SRC}'   => $frameUrl,
			]);
		}
		$html .= $this->moduleContext->adminPageRenderer->ParseBlock('ADS_INLINE_ROW', [
			'{$SLOTS}' => $slots,
		]);

		$mobileAds = $this->getAdsForSlot('mobile');
		if (!empty($mobileAds)) {
			$mobileDims = $this->getSlotDimensions('mobile');
			$html .= $this->moduleContext->adminPageRenderer->ParseBlock('ADS_MOBILE_INLINE_SLOT', [
				'{$STYLE}' => '--pm-slot-width:' . (int)$mobileDims['width'] . 'px;--pm-slot-height:' . (int)$mobileDims['height'] . 'px;',
				'{$SRC}'   => sanitizeStr($this->buildFrameUrl('mobile')),
			]);
		}
	}

	private function onRenderPostSeparate(string &$html, int $replyIterator): void {
		$everyN = max(1, (int)$this->getConfig('ModuleSettings.ADS_POST_AD_EVERY_N_POSTS', 5));
		if (($replyIterator + 1) % $everyN !== 0) {
			return;
		}
		$ad = $this->getNextAdForSlot('post_ad');
		if ($ad === null) {
			return;
		}

		$names = $this->getConfig('ModuleSettings.NAME_RANDOMIZER_NAMES', []);
		$name  = (!empty($names) && is_array($names))
			? sanitizeStr($names[array_rand($names)])
			: 'Anonymous';

		$now = $this->moduleContext->postDateFormatter->formatFromTimestamp(time());

		$no = (++$this->adPostCounter) . 'xxx';

		$html .= $this->moduleContext->templateEngine->ParseBlock('REPLY', [
			'{$BOARD_UID}'                  => 'ad',
			'{$NO}'                         => $no,
			'{$POST_UID}'                   => '',
			'{$MODULE_POST_CSS_CLASSES}'    => 'pmPostAd',
			'{$DATA_ATTRIBUTES}'            => '',
			'{$POST_POSITION_ENABLED}'      => '1',
			'{$POST_POSITION}'              => 'AD',
			'{$SUB}'                        => '',
			'{$NAME_TEXT}'                  => '',
			'{$NAME}'                       => generatePostNameHtml($this->moduleContext->moduleEngine, $name, '', '', '', '', false),
			'{$NOW}'                        => $now,
			'{$POSTER_HASH}'                => '',
			'{$POSTER_HASH_COUNT}'          => '',
			'{$QUOTEBTN}'                   => '',
			'{$POST_URL}'                   => '#',
			'{$POSTINFO_EXTRA}'             => '',
			'{$POST_MENU}'                  => '',
			'{$MODULE_ATTACHMENT_CSS_CLASSES}' => '',
			'{$POST_ATTACHMENTS}'           => $this->buildAdHtml($ad, 'post_ad'),
			'{$COM}'                        => '',
			'{$BELOW_COMMENT}'              => '',
			'{$CATEGORY}'                   => '',
			'{$CATEGORY_TEXT}'              => '',
			'{$WARN_BEKILL}'                => '',
		]);
	}

	private function onRenderPageTop(string &$html): void {
		$topAds    = $this->getAdsForSlot('top');
		$mobileAds = $this->getAdsForSlot('mobile');

		if (empty($topAds) && empty($mobileAds)) {
			return;
		}

		$topAd    = !empty($topAds)    ? $topAds[array_rand($topAds)]       : null;
		$mobileAd = !empty($mobileAds) ? $mobileAds[array_rand($mobileAds)] : null;

		$html .= $this->moduleContext->adminPageRenderer->ParseBlock('ADS_TOP_CONTAINER', [
			'{$HAS_TOP}'     => $topAd !== null,
			'{$HAS_MOBILE}'  => $mobileAd !== null,
			'{$TOP_HTML}'    => $topAd    !== null ? $this->buildAdHtml($topAd, 'top')       : '',
			'{$MOBILE_HTML}' => $mobileAd !== null ? $this->buildAdHtml($mobileAd, 'mobile') : '',
		]);
	}

	private function onRenderFoot(string &$footer): void {
		$footer .= $this->moduleContext->adminPageRenderer->ParseBlock('ADS_FOOTER_INJECT', [
			'{$STICKY_ROTATE_SECONDS}' => max(0, (int)$this->getConfig('ModuleSettings.ADS_STICKY_ROTATE_SECONDS', 45)),
		]);

		$mobileAds = $this->getAdsForSlot('mobile');
		if (!empty($mobileAds)) {
			$mobileDims = $this->getSlotDimensions('mobile');
			$footer .= $this->moduleContext->adminPageRenderer->ParseBlock('ADS_MOBILE_INLINE_SLOT', [
				'{$STYLE}' => '--pm-slot-width:' . (int)$mobileDims['width'] . 'px;--pm-slot-height:' . (int)$mobileDims['height'] . 'px;',
				'{$SRC}'   => sanitizeStr($this->buildFrameUrl('mobile')),
			]);
		}
	}

	public function ModulePage(): void {
		$pageName = strtolower((string)$this->moduleContext->request->getParameter('pageName', 'GET', 'frame'));
		$slot = strtolower((string)$this->moduleContext->request->getParameter('type', 'GET', 'top'));

		if (!in_array($slot, ['top', 'mobile', 'sticky', 'above', 'below', 'inline', 'post_ad'], true)) {
			renderJsonPage(['success' => false, 'error' => 'Invalid ad slot'], 400);
			return;
		}

		if ($pageName === 'json') {
			$this->renderJsonForSlot($slot);
			return;
		}

		$this->renderFrameForSlot($slot);
	}

	private function renderJsonForSlot(string $slot): void {
		$ad = $this->getNextAdForSlot($slot);
		if ($ad === null) {
			renderJsonPage(['success' => true, 'slot' => $slot, 'ad' => null]);
			return;
		}

		$dims = $this->getSlotDimensions($slot);
		$payload = [
			'success' => true,
			'slot' => $slot,
			'ad' => [
				'html' => $this->buildAdHtml($ad),
				'href' => $ad->href,
				'width' => (int)$dims['width'],
				'height' => (int)$dims['height'],
			],
		];

		renderJsonPage($payload);
	}

	private function renderFrameForSlot(string $slot): void {
		$ad = $this->getNextAdForSlot($slot);
		$dims = $this->getSlotDimensions($slot);

		header('Content-Type: text/html; charset=utf-8');

		if ($ad === null) {
			echo $this->moduleContext->adminPageRenderer->ParseBlock('ADS_FRAME_EMPTY', []);
			return;
		}

		echo $this->moduleContext->adminPageRenderer->ParseBlock('ADS_FRAME_PAGE', [
			'{$FRAME_WIDTH}'  => (int)$dims['width'],
			'{$FRAME_HEIGHT}' => (int)$dims['height'],
			'{$AD_HTML}'      => $this->buildAdHtml($ad),
		]);
	}

	private function getSlotDimensions(string $slot): array {
		$dimensions = $this->getConfig('ModuleSettings.ADS_SLOT_DIMENSIONS', []);

		$defaults = [
			'top'    => ['width' => 728, 'height' => 90],
			'mobile' => ['width' => 300, 'height' => 250],
			'sticky' => ['width' => 728, 'height' => 90],
			'above' => ['width' => 728, 'height' => 90],
			'below' => ['width' => 728, 'height' => 90],
			'inline' => ['width' => 728, 'height' => 90],
			'post_ad' => ['width' => 300, 'height' => 250],
		];

		$slotDefault = $defaults[$slot] ?? ['width' => 728, 'height' => 90];
		$slotConfig = (is_array($dimensions) && isset($dimensions[$slot]) && is_array($dimensions[$slot]))
			? $dimensions[$slot]
			: [];

		$width = max(1, (int)($slotConfig['width'] ?? $slotDefault['width']));
		$height = max(1, (int)($slotConfig['height'] ?? $slotDefault['height']));

		return ['width' => $width, 'height' => $height];
	}

	private function getAdsRepo(): adRepository {
		if ($this->adRepo === null) {
			$databaseSettings = getDatabaseSettings();
			$this->adRepo = new adRepository(
				databaseConnection::getInstance(),
				$databaseSettings['ADS_TABLE']
			);
		}
		return $this->adRepo;
	}

	private function getAdsForSlot(string $slot): array {
		return $this->getAdsRepo()->getEnabledAdsForSlot($slot);
	}

	private function getNextAdForSlot(string $slot): ?adEntry {
		$ads = $this->getAdsForSlot($slot);
		if (empty($ads)) {
			return null;
		}

		return $ads[array_rand($ads)];
	}

	private function buildAdHtml(adEntry $ad, ?string $slot = null): string {
		if ($ad->type === 'script') {
			return (string)$ad->html;
		}

		if ($slot !== null) {
			$dims = $this->getSlotDimensions($slot);
			$imgStyle = 'max-width:' . (int)$dims['width'] . 'px;max-height:' . (int)$dims['height'] . 'px;height:auto;display:inline;';
		} else {
			$imgStyle = 'max-width:100%;height:auto;display:inline;';
		}

		return $this->moduleContext->adminPageRenderer->ParseBlock('ADS_AD_IMAGE', [
			'{$SRC}'       => sanitizeStr((string)($ad->src ?? '')),
			'{$HREF}'      => sanitizeStr((string)($ad->href ?? '')),
			'{$ALT}'       => sanitizeStr((string)($ad->alt !== null && $ad->alt !== '' ? $ad->alt : 'Advertisement')),
			'{$HAS_HREF}'  => $ad->href !== null && $ad->href !== '',
			'{$IMG_STYLE}' => $imgStyle,
		]);
	}

	private function buildFrameUrl(string $slot): string {
		return $this->modulePageUrl . '&pageName=frame&type=' . rawurlencode($slot);
	}

	private function buildJsonUrl(string $slot): string {
		return $this->modulePageUrl . '&pageName=json&type=' . rawurlencode($slot);
	}
}
