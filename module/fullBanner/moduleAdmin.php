<?php

namespace Kokonotsuba\Modules\fullBanner;

require_once __DIR__ . '/fullBannerEntry.php';
require_once __DIR__ . '/fullBannerRepository.php';
require_once __DIR__ . '/fullBannerService.php';
require_once __DIR__ . '/fullBannerLib.php';

use Kokonotsuba\error\BoardException;
use Kokonotsuba\module_classes\abstractModuleAdmin;
use Kokonotsuba\module_classes\traits\AuditableTrait;
use Kokonotsuba\module_classes\traits\listeners\PostControlHooksTrait;
use Kokonotsuba\userRole;

use const Kokonotsuba\GLOBAL_BOARD_UID;

use function Puchiko\request\redirect;
use function Puchiko\strings\sanitizeStr;
use function Kokonotsuba\libraries\_T;

class moduleAdmin extends abstractModuleAdmin {
	use AuditableTrait;
	use PostControlHooksTrait;

	private fullBannerService $fullBannerService;
	private readonly string $modulePage;
	private readonly string $serveImageUrl;
	private readonly int $requiredWidth;
	private readonly int $requiredHeight;
	private readonly int $maxFileSize;

	public function getRequiredRole(): userRole {
		return userRole::LEV_MODERATOR;
	}

	public function getName(): string {
		return 'Full Banner Manager';
	}

	public function getVersion(): string {
		return 'Twendy twendy sex';
	}

	public function initialize(): void {
		$this->fullBannerService = getFullBannerService($this->moduleContext->transactionManager);
		$this->modulePage = $this->getModulePageURL([], false);
		$this->serveImageUrl = $this->getModulePageURL(['page' => 'bannerServeImage'], false, false);
		$this->requiredWidth = $this->getConfig('ModuleSettings.FULLBANNER_REQUIRED_WIDTH', 468);
		$this->requiredHeight = $this->getConfig('ModuleSettings.FULLBANNER_REQUIRED_HEIGHT', 60);
		$this->maxFileSize = $this->getConfig('ModuleSettings.FULLBANNER_MAX_FILE_SIZE', 204800);

		$this->registerLinksAboveBarHook('Manage full banners', $this->modulePage, 'Full banners');
	}

	public function ModulePage(): void {
		if ($this->moduleContext->request->isPost()) {
			$this->handlePostActions();
			redirect($this->modulePage);
		}

		// Serve banner images via GET
		$page = $this->moduleContext->request->getParameter('page', 'GET', '');
		if ($page === 'bannerServeImage') {
			$this->serveBannerImage();
			exit;
		}

		$this->drawAdminPage();
	}

	private function handlePostActions(): void {
		$action = $this->moduleContext->request->getParameter('action', 'POST', '');

		// Staff banner upload
		if ($action === 'submitBanner') {
			$this->handleStaffUpload();
			return;
		}

		// Approve selected
		if ($this->moduleContext->request->hasParameter('action_approve', 'POST')) {
			$selectedIds = $this->getSelectedIds();
			if (!empty($selectedIds)) {
				$this->fullBannerService->approveBanners($selectedIds);
				$this->logAction("Approved " . count($selectedIds) . " full banner(s)", GLOBAL_BOARD_UID);
			}
			return;
		}

		// Delete selected
		if ($this->moduleContext->request->hasParameter('action_delete', 'POST')) {
			$selectedIds = $this->getSelectedIds();
			if (!empty($selectedIds)) {
				$this->fullBannerService->deleteBanners($selectedIds);
				$this->logAction("Deleted " . count($selectedIds) . " full banner(s)", GLOBAL_BOARD_UID);
			}
			return;
		}

		// Enable selected
		if ($this->moduleContext->request->hasParameter('action_enable', 'POST')) {
			$selectedIds = $this->getSelectedIds();
			if (!empty($selectedIds)) {
				$this->fullBannerService->setActiveMultiple($selectedIds, true);
				$this->logAction("Enabled " . count($selectedIds) . " full banner(s)", GLOBAL_BOARD_UID);
			}
			return;
		}

		// Disable selected
		if ($this->moduleContext->request->hasParameter('action_disable', 'POST')) {
			$selectedIds = $this->getSelectedIds();
			if (!empty($selectedIds)) {
				$this->fullBannerService->setActiveMultiple($selectedIds, false);
				$this->logAction("Disabled " . count($selectedIds) . " full banner(s)", GLOBAL_BOARD_UID);
			}
			return;
		}
    }

	private function handleStaffUpload(): void {
		$file = $this->moduleContext->request->getFile('banner_file');
		if (!$file) {
			throw new BoardException('No file uploaded.');
		}

		$link = trim($this->moduleContext->request->getParameter('banner_link', 'POST', '') ?? '');
		if ($link !== '' && !filter_var($link, FILTER_VALIDATE_URL)) {
			throw new BoardException('Invalid destination link URL.');
		}
		if ($link === '') {
			$link = null;
		}

		$this->fullBannerService->adminUploadBanner($file, $link, $this->requiredWidth, $this->requiredHeight, $this->maxFileSize);
		$this->logAction("Uploaded full banner (auto-approved)", GLOBAL_BOARD_UID);
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

		$filePath = $this->fullBannerService->getBannerFilePath($fileName);
		if ($filePath === null) {
			header("HTTP/1.0 404 Not Found");
			exit;
		}

		header('Cache-Control: public, max-age=3600');
		header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 3600) . ' GMT');

		\Kokonotsuba\libraries\serveMedia($filePath);
	}

	private function drawAdminPage(): void {
		$banners = $this->fullBannerService->getAllBanners();

		$rows = array_map(fn($b) => $b->toAdminTemplateRow($this->serveImageUrl, $this->requiredWidth, $this->requiredHeight), $banners);

		$templateValues = [
			'{$MODULE_PAGE_URL}' => sanitizeStr($this->modulePage),
			'{$UPLOAD_HEADING}' => _T('fullbanner_upload_heading'),
			'{$UPLOAD_BUTTON}' => _T('fullbanner_upload_button'),
			'{$REQ_DIMENSIONS}' => _T('fullbanner_req_dimensions', $this->requiredWidth, $this->requiredHeight),
			'{$REQ_FILETYPES}' => _T('fullbanner_req_filetypes'),
			'{$REQ_FILESIZE}' => _T('fullbanner_req_filesize', round($this->maxFileSize / 1024)),
			'{$BANNER_HEIGHT}' => (string) $this->requiredHeight,
			'{$ROWS}' => $rows,
			'{$EMPTY}' => empty($rows) ? '1' : '',
		];

		$adminPageHtml = $this->moduleContext->adminPageRenderer->ParseBlock('FULLBANNER_ADMIN_PAGE', $templateValues);
		echo $this->moduleContext->adminPageRenderer->ParsePage('GLOBAL_ADMIN_PAGE_CONTENT', [
			'{$PAGE_CONTENT}' => $adminPageHtml
		], true);
	}
}
