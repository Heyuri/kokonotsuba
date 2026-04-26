<?php

namespace Kokonotsuba\Modules\ads;

require_once __DIR__ . '/adEntry.php';
require_once __DIR__ . '/adRepository.php';

use Kokonotsuba\database\databaseConnection;
use Kokonotsuba\module_classes\abstractModuleAdmin;
use Kokonotsuba\module_classes\traits\AuditableTrait;
use Kokonotsuba\module_classes\traits\listeners\PostControlHooksTrait;
use Kokonotsuba\userRole;

use const Kokonotsuba\GLOBAL_BOARD_UID;

use function Kokonotsuba\libraries\html\drawPager;
use function Puchiko\request\redirect;
use function Puchiko\strings\sanitizeStr;

class moduleAdmin extends abstractModuleAdmin {
	use AuditableTrait;
	use PostControlHooksTrait;

	private adRepository $adRepository;
	private readonly string $modulePage;

	private const VALID_SLOTS = [
		'top', 'mobile', 'sticky',
		'above', 'below', 'inline', 'post_ad',
	];

	private const VALID_TYPES = ['image', 'script'];

	public function getRequiredRole(): userRole {
		return $this->getConfig('AuthLevels.CAN_MANAGE_ADS', userRole::LEV_MODERATOR);
	}

	public function getName(): string {
		return 'Ads Manager';
	}

	public function getVersion(): string {
		return '1.0';
	}

	public function initialize(): void {
		$databaseSettings = getDatabaseSettings();
		$this->adRepository = new adRepository(
			databaseConnection::getInstance(),
			$databaseSettings['ADS_TABLE']
		);
		$this->modulePage = $this->getModulePageURL([], false);

		$this->registerLinksAboveBarHook('Manage ads', $this->modulePage, 'Ads');
	}

	public function ModulePage(): void {
		if ($this->moduleContext->request->isPost()) {
			$this->handlePostActions();
			redirect($this->modulePage);
		}

		$this->drawAdminPage();
	}

	private function handlePostActions(): void {
		$action = $this->moduleContext->request->getParameter('action', 'POST', '');

		if ($action === 'add') {
			$this->handleAdd();
			return;
		}

		if ($action === 'bulk') {
			if ($this->moduleContext->request->hasParameter('bulk_delete', 'POST')) {
				$this->handleBulkDelete();
				return;
			}
			if ($this->moduleContext->request->hasParameter('bulk_save', 'POST')) {
				$this->handleBulkSave();
				return;
			}
		}
	}

	private function handleAdd(): void {
		$slot = (string)($this->moduleContext->request->getParameter('ad_slot', 'POST', '') ?? '');
		$type = (string)($this->moduleContext->request->getParameter('ad_type', 'POST', '') ?? '');

		if (!in_array($slot, self::VALID_SLOTS, true) || !in_array($type, self::VALID_TYPES, true)) {
			return;
		}

		$src  = trim((string)($this->moduleContext->request->getParameter('ad_src',  'POST', '') ?? ''));
		$href = trim((string)($this->moduleContext->request->getParameter('ad_href', 'POST', '') ?? ''));
		$alt  = trim((string)($this->moduleContext->request->getParameter('ad_alt',  'POST', '') ?? ''));
		$html = trim((string)($this->moduleContext->request->getParameter('ad_html', 'POST', '') ?? ''));

		if ($type === 'image' && $src === '') {
			return;
		}
		if ($type === 'script' && $html === '') {
			return;
		}

		// Validate URLs — invalid src blocks the add, invalid href is downgraded to null
		if ($src !== '' && filter_var($src, FILTER_VALIDATE_URL) === false) {
			return;
		}
		if ($href !== '' && filter_var($href, FILTER_VALIDATE_URL) === false) {
			$href = '';
		}

		$this->adRepository->insertAd(
			$slot,
			$type,
			$src   !== '' ? $src   : null,
			$href  !== '' ? $href  : null,
			$alt   !== '' ? $alt   : null,
			$html  !== '' ? $html  : null,
		);

		$this->logAction("Added ad for slot '{$slot}' (type: {$type})", GLOBAL_BOARD_UID);
	}

	private function handleBulkDelete(): void {
		$ids = $this->moduleContext->request->getParameter('delete', 'POST') ?? [];
		if (!is_array($ids) || empty($ids)) {
			return;
		}

		$ids = array_filter(array_map('intval', $ids), fn($id) => $id > 0);
		foreach ($ids as $id) {
			$this->adRepository->deleteAd($id);
		}

		if (!empty($ids)) {
			$this->logAction("Deleted ad(s): " . implode(', ', $ids), GLOBAL_BOARD_UID);
		}
	}

	private function handleBulkSave(): void {
		$rowsData   = $this->moduleContext->request->getParameter('rows',    'POST') ?? [];
		$enabledMap = $this->moduleContext->request->getParameter('enabled', 'POST') ?? [];

		if (!is_array($rowsData))   { return; }
		if (!is_array($enabledMap)) { $enabledMap = []; }

		$updated = [];
		foreach ($rowsData as $rawId => $fields) {
			$id = (int)$rawId;
			if ($id <= 0 || !is_array($fields)) {
				continue;
			}

			$slot = (string)($fields['slot'] ?? '');
			$type = (string)($fields['type'] ?? '');
			if (!in_array($slot, self::VALID_SLOTS, true) || !in_array($type, self::VALID_TYPES, true)) {
				continue;
			}

			$src  = trim((string)($fields['src']  ?? ''));
			$href = trim((string)($fields['href'] ?? ''));
			$alt  = trim((string)($fields['alt']  ?? ''));
			$html = trim((string)($fields['html'] ?? ''));

			if ($src  !== '' && filter_var($src,  FILTER_VALIDATE_URL) === false) { continue; }
			if ($href !== '' && filter_var($href, FILTER_VALIDATE_URL) === false) { $href = ''; }

			$this->adRepository->updateAd(
				$id, $slot, $type,
				$src  !== '' ? $src  : null,
				$href !== '' ? $href : null,
				$alt  !== '' ? $alt  : null,
				$html !== '' ? $html : null,
			);
			$this->adRepository->setEnabled($id, isset($enabledMap[(string)$id]));
			$updated[] = $id;
		}

		if (!empty($updated)) {
			$this->logAction('Saved ad(s): ' . implode(', ', $updated), GLOBAL_BOARD_UID);
		}
	}

	private function drawAdminPage(): void {
		$request = $this->moduleContext->request;

		$slotFilter = $request->hasParameter('slot') ? (string)$request->getParameter('slot') : '';
		if ($slotFilter !== '' && !in_array($slotFilter, self::VALID_SLOTS, true)) {
			$slotFilter = '';
		}

		$currentPage = ($request->hasParameter('page') && is_numeric($request->getParameter('page')))
			? (int)$request->getParameter('page')
			: 0;
		$currentPage = max(0, $currentPage);

		$entriesPerPage = max(1, (int)$this->getConfig('ADMIN_PAGE_DEF', 100));

		$filterArg = $slotFilter !== '' ? $slotFilter : null;
		$total  = $this->adRepository->countAll($filterArg);
		$offset = $currentPage * $entriesPerPage;
		$ads    = $this->adRepository->getPagedAds($entriesPerPage, $offset, $filterArg);

		$filterUrl = $this->modulePage . ($slotFilter !== '' ? '&slot=' . rawurlencode($slotFilter) : '');

		$rows = [];
		foreach ($ads as $ad) {
			$row = $ad->toAdminTemplateRow();
			$slotSelectHtml = '';
			foreach (self::VALID_SLOTS as $s) {
				$sel = ($s === $ad->slot) ? ' selected' : '';
				$slotSelectHtml .= '<option value="' . sanitizeStr($s) . '"' . $sel . '>' . sanitizeStr($s) . '</option>';
			}
			$row['{$SLOT_SELECT}'] = $slotSelectHtml;
			$rows[] = $row;
		}

		$pagerHtml = drawPager($entriesPerPage, $total, $filterUrl, $request);

		$slotOptions = '';
		foreach (self::VALID_SLOTS as $s) {
			$selected = ($s === $slotFilter) ? ' selected' : '';
			$slotOptions .= '<option value="' . sanitizeStr($s) . '"' . $selected . '>' . sanitizeStr($s) . '</option>';
		}

		$templateValues = [
			'{$MODULE_URL}'   => sanitizeStr($this->modulePage),
			'{$FILTER_URL}'   => sanitizeStr($this->modulePage),
			'{$SLOT_OPTIONS}' => $slotOptions,
			'{$SLOT_FILTER}'  => sanitizeStr($slotFilter),
			'{$ROWS}'         => $rows,
			'{$EMPTY}'        => empty($rows) ? '1' : '',
		];

		$pageHtml = $this->moduleContext->adminPageRenderer->ParseBlock('ADS_ADMIN_PAGE', $templateValues);
		echo $this->moduleContext->adminPageRenderer->ParsePage('GLOBAL_ADMIN_PAGE_CONTENT', [
			'{$PAGE_CONTENT}' => $pageHtml,
			'{$PAGER}'        => $pagerHtml,
		], true);
	}
}
