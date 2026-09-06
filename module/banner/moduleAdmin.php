<?php

namespace Kokonotsuba\Modules\banner;

require_once __DIR__ . '/bannerPreset.php';
require_once __DIR__ . '/bannerPresetRegistry.php';
require_once __DIR__ . '/bannerEntry.php';
require_once __DIR__ . '/bannerRepository.php';
require_once __DIR__ . '/bannerService.php';
require_once __DIR__ . '/bannerLib.php';

use Kokonotsuba\action_log\actionType;
use Kokonotsuba\error\BoardException;
use Kokonotsuba\module_classes\abstractModuleAdmin;
use Kokonotsuba\module_classes\traits\AuditableTrait;
use Kokonotsuba\module_classes\traits\listeners\PostControlHooksTrait;
use Kokonotsuba\module_classes\traits\listeners\StaffAlertsListenerTrait;
use Kokonotsuba\userRole;

use const Kokonotsuba\GLOBAL_BOARD_UID;

use function Puchiko\request\redirect;
use function Puchiko\strings\sanitizeStr;
use function Kokonotsuba\libraries\_T;
use function Kokonotsuba\libraries\html\drawPager;
use function Kokonotsuba\libraries\html\getPageFromRequest;

class moduleAdmin extends abstractModuleAdmin {
	use AuditableTrait;
	use PostControlHooksTrait;
	use StaffAlertsListenerTrait;

	/** The preset filter that means "every preset at once". */
	private const FILTER_ALL = 'all';

	private bannerService $bannerService;
	private bannerPresetRegistry $presets;
	private readonly string $modulePage;
	private readonly string $serveImageUrl;

	public function getRequiredRole(): userRole {
		return $this->getConfig('AuthLevels.CAN_MANAGE_BANNERS', userRole::LEV_MANAGER);
	}

	public function getName(): string {
		return 'Banner Manager';
	}

	public function getVersion(): string {
		return 'Twendy twendy sex';
	}

	public function initialize(): void {
		$this->presets = bannerPresetRegistry::fromConfig(
			fn (string $key, mixed $default): mixed => $this->getModuleConfig($key, $default)
		);
		$this->bannerService = getBannerService($this->moduleContext->transactionManager);

		$this->modulePage = $this->getModulePageURL([], false);
		$this->serveImageUrl = $this->getModulePageURL(['pageName' => 'bannerServeImage'], false, false);

		$this->registerLinksAboveBarHook('Manage banners', $this->modulePage, 'Banners', 'content');
		$this->listenStaffAlertsProtected('onCollectStaffAlerts');
	}

	/**
	 * "Banners" in the staff alerts widget.
	 *
	 * Unread here means unapproved: a reader-submitted banner sits at is_approved = 0 until
	 * someone acts on it, so the pending count is exactly the pile waiting to be looked at.
	 */
	private function onCollectStaffAlerts(array &$alerts): void {
		$alerts[] = [
			'key' => 'banners',
			'label' => _T('banner_alert_label'),
			'count' => $this->bannerService->countPendingBanners(),
			'url' => $this->modulePage,
			'title' => _T('banner_alert_title'),
		];
	}

	public function ModulePage(): void {
		if ($this->moduleContext->request->isPost()) {
			$this->handlePostActions();
			redirect($this->modulePage . '&preset=' . urlencode($this->requestedFilter()));
		}

		if ($this->moduleContext->request->getParameter('pageName', 'GET', '') === 'bannerServeImage') {
			$this->serveBannerImage();
			exit;
		}

		$this->drawAdminPage();
	}

	/** The preset the page is scoped to, as it came in: a preset key, or "all". */
	private function requestedFilter(): string {
		$requested = (string) $this->moduleContext->request->getParameter('preset', 'GET', '');

		return $requested === self::FILTER_ALL ? self::FILTER_ALL : $this->presets->resolve($requested)->key;
	}

	private function handlePostActions(): void {
		$request = $this->moduleContext->request;

		if ($request->getParameter('action', 'POST', '') === 'submitBanner') {
			$this->handleStaffUpload();
			return;
		}

		$bulkActions = [
			'action_approve' => ['Approved', fn (array $ids) => $this->bannerService->approveBanners($ids)],
			'action_delete'  => ['Deleted', fn (array $ids) => $this->bannerService->deleteBanners($ids)],
			'action_enable'  => ['Enabled', fn (array $ids) => $this->bannerService->setActiveMultiple($ids, true)],
			'action_disable' => ['Disabled', fn (array $ids) => $this->bannerService->setActiveMultiple($ids, false)],
		];

		foreach ($bulkActions as $parameter => [$verb, $apply]) {
			if (!$request->hasParameter($parameter, 'POST')) {
				continue;
			}

			$selectedIds = $this->getSelectedIds();
			if ($selectedIds !== []) {
				$apply($selectedIds);
				$this->logAction("{$verb} " . count($selectedIds) . " banner(s)", GLOBAL_BOARD_UID, actionType::CONTENT_BANNER);
			}

			return;
		}
	}

	private function handleStaffUpload(): void {
		$request = $this->moduleContext->request;
		$preset = $this->presets->resolve($request->getParameter('preset', 'POST', ''));

		$file = $request->getFile('banner_file');
		if (!$file) {
			throw new BoardException(_T('banner_no_file'));
		}

		$link = null;
		if ($preset->usesLink) {
			$link = trim($request->getParameter('banner_link', 'POST', '') ?? '');
			if ($link !== '' && !filter_var($link, FILTER_VALIDATE_URL)) {
				throw new BoardException(_T('banner_invalid_link'));
			}
			if ($link === '') {
				$link = null;
			}
		}

		$this->bannerService->adminUploadBanner($preset, $file, $link);
		$this->logAction("Uploaded {$preset->key} banner (auto-approved)", GLOBAL_BOARD_UID, actionType::CONTENT_BANNER);
	}

	private function getSelectedIds(): array {
		$ids = $this->moduleContext->request->getParameter('selected_ids', 'POST') ?? [];

		return array_map('intval', array_filter($ids, 'is_numeric'));
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

		header('Cache-Control: public, max-age=3600');
		header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 3600) . ' GMT');

		\Kokonotsuba\libraries\serveMedia($filePath);
	}

	private function drawAdminPage(): void {
		$request = $this->moduleContext->request;
		$filter = $this->requestedFilter();
		$showingAll = $filter === self::FILTER_ALL;
		$preset = $showingAll ? null : $this->presets->get($filter);

		$entriesPerPage = (int) $this->getConfig('ADMIN_PAGE_DEF', 100);
		$paginationData = $this->bannerService->getAllBannersPage($preset?->key, getPageFromRequest($request), $entriesPerPage);

		// Rows come from several presets in the "all" view, so each one is measured against its own.
		$rows = array_map(
			fn ($b) => $b->toAdminTemplateRow($this->serveImageUrl, $this->presets->resolve($b->preset), $this->moduleContext->postDateFormatter),
			$paginationData['items']
		);

		$filterUrl = $this->modulePage . '&preset=' . urlencode($filter);
		$paginationHtml = drawPager($paginationData['entriesPerPage'], $paginationData['totalEntries'], $filterUrl, $request);

		$templateValues = [
			'{$MODULE_PAGE_URL}' => sanitizeStr($filterUrl),
			'{$PRESET_NAV}' => renderPresetNav($this->presets, $this->modulePage, $preset?->key, true),
			'{$PRESET_KEY}' => sanitizeStr($preset?->key ?? ''),
			'{$PRESET_LABEL}' => sanitizeStr($preset?->label() ?? ''),
			'{$SHOW_UPLOAD}' => $preset !== null ? '1' : '',
			'{$USES_LINK}' => $preset !== null && $preset->usesLink ? '1' : '',
			'{$UPLOAD_HEADING}' => _T('banner_upload_heading'),
			'{$UPLOAD_BUTTON}' => _T('banner_upload_button'),
			'{$REQUIREMENTS}' => array_map(
				fn (string $rule): array => ['{$RULE}' => sanitizeStr($rule)],
				$preset?->requirements() ?? []
			),
			'{$STATUS_MESSAGE}' => '',
			'{$ROWS}' => $rows,
			'{$EMPTY}' => empty($rows) ? '1' : '',
		];

		$adminPageHtml = $this->moduleContext->adminPageRenderer->ParseBlock('BANNER_ADMIN_PAGE', $templateValues);
		echo $this->moduleContext->adminPageRenderer->ParsePage('GLOBAL_ADMIN_PAGE_CONTENT', [
			'{$PAGE_CONTENT}' => $adminPageHtml,
			'{$PAGER}' => $paginationHtml,
		], true);
	}
}
