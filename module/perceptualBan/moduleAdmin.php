<?php

namespace Kokonotsuba\Modules\perceptualBan;

require_once __DIR__ . '/perceptualBanRepository.php';
require_once __DIR__ . '/perceptualBanService.php';
require_once __DIR__ . '/perceptualBanLib.php';
require_once __DIR__ . '/perceptualHasher.php';

use Kokonotsuba\error\BoardException;
use Kokonotsuba\module_classes\abstractModuleAdmin;
use Kokonotsuba\module_classes\traits\listeners\PostControlHooksTrait;
use Kokonotsuba\userRole;
use Kokonotsuba\post\Post;

use function Kokonotsuba\libraries\_T;
use function Kokonotsuba\libraries\html\drawPager;
use function Kokonotsuba\libraries\searchBoardArrayForBoard;
use function Puchiko\json\sendAjaxAndDetach;
use function Puchiko\request\redirect;
use function Kokonotsuba\Modules\perceptualBan\getPerceptualBanService;
use function Kokonotsuba\Modules\perceptualBan\getPerceptualHasher;

class moduleAdmin extends abstractModuleAdmin {
	use PostControlHooksTrait;

	private perceptualBanService $perceptualBanService;
	private perceptualHasher $perceptualHasher;
	private string $moduleUrl;

	public function getRequiredRole(): userRole {
		return $this->getConfig('AuthLevels.CAN_BAN_FILES', userRole::LEV_MODERATOR);
	}

	public function getName(): string {
		return 'Perceptual file ban management';
	}

	public function getVersion(): string {
		return '1.0';
	}

	public function initialize(): void {
		$this->moduleUrl = $this->getModulePageURL([], false);

		$this->perceptualBanService = getPerceptualBanService($this->moduleContext->transactionManager);
		$this->perceptualHasher = getPerceptualHasher();

		$this->listenProtected('ModerateAttachmentWidget', function(array &$widgetArray, array &$fileData) {
			$this->onRenderAttachmentWidget($widgetArray, $fileData);
		});
		$this->registerLinksAboveBarHook(_T('admin_nav_perceptual_ban_title'), $this->moduleUrl, _T('admin_nav_perceptual_ban'));
	}

	private function onRenderAttachmentWidget(array &$widgetArray, array &$fileData): void {
		$mimeType = $fileData['mimeType'] ?? '';
		$hasAttachment = !empty($fileData) && $this->perceptualHasher->isHashableMedia($mimeType);
		$canDelete = $this->canDeleteAttachment($fileData);

		if ($hasAttachment) {
			$banUrl = $this->getModulePageURL([
				'action' => 'banOnly',
				'post_uid' => $fileData['postUid'],
				'fileId' => $fileData['fileId'],
			], false, true);
			$widgetArray[] = $this->buildWidgetEntry($banUrl, 'BanFile', _T('perceptual_ban_btn_title'), '');
		}

		if ($canDelete) {
			$bdUrl = $this->getModulePageURL([
				'action' => 'banAndDelete',
				'post_uid' => $fileData['postUid'],
				'fileId' => $fileData['fileId'],
			], false, true);
			$widgetArray[] = $this->buildWidgetEntry($bdUrl, 'BanDeleteFile', _T('perceptual_ban_bd_btn_title'), '');
		}
	}

	public function ModulePage(): void {
		$action = $_REQUEST['action'] ?? '';

		if ($action === 'banAndDelete') {
			$this->handleBanAndDelete();
			return;
		}

		if ($action === 'banOnly') {
			$this->handleBanOnly();
			return;
		}

		if ($this->moduleContext->request->isPost()) {
			$this->handleRequests();
		} elseif ($this->moduleContext->request->isGet()) {
			$this->drawIndex();
		}
	}

	private function handleRequests(): void {
		$action = $_POST['action'] ?? '';

		if ($action === 'addBan') {
			$this->handleAddBan();
		} elseif ($action === 'delete') {
			$this->handleDeletions();
		} else {
			throw new BoardException(_T('perceptual_ban_invalid_action'));
		}
	}

	private function handleBanAndDelete(): void {
		$postUid = $_GET['post_uid'] ?? null;
		$post = $this->fetchValidatedPost($postUid);

		$fileId = (int) ($_GET['fileId'] ?? 0);
		if (empty($fileId)) {
			throw new BoardException(_T('perceptual_ban_no_file'));
		}

		$attachment = $post->getAttachmentById($fileId);
		if (!$attachment) {
			throw new BoardException(_T('attachment_not_found'));
		}

		$hashHex = $this->computeHashFromAttachment($attachment);

		$board = searchBoardArrayForBoard($post->getBoardUID());
		$boardUID = $board->getBoardUID();

		$this->perceptualBanService->addBan($hashHex, $this->moduleContext->currentUserId);

		$this->moduleContext->deletedPostsService->deleteFilesFromPosts([$attachment], $this->moduleContext->currentUserId);

		$this->moduleContext->actionLoggerService->logAction(
			'Perceptually banned and deleted file (pHash: ' . $hashHex . ') from post No.' . $post->getNumber(),
			$boardUID
		);

		if ($this->moduleContext->request->isAjax()) {
			$deletedLink = $this->getDeletedLinkForFile($fileId);
			sendAjaxAndDetach(['success' => true, 'deleted_link' => $deletedLink]);
			$this->rebuildBoardForPost($board, $post);
			exit;
		}

		$this->rebuildBoardForPost($board, $post);
		redirect('back');
	}

	private function handleBanOnly(): void {
		$postUid = $_GET['post_uid'] ?? null;
		$post = $this->fetchValidatedPost($postUid);

		$fileId = (int) ($_GET['fileId'] ?? 0);
		if (empty($fileId)) {
			throw new BoardException(_T('perceptual_ban_no_file'));
		}

		$attachment = $post->getAttachmentById($fileId);
		if (!$attachment) {
			throw new BoardException(_T('attachment_not_found'));
		}

		$hashHex = $this->computeHashFromAttachment($attachment);

		$board = searchBoardArrayForBoard($post->getBoardUID());
		$boardUID = $board->getBoardUID();

		$this->perceptualBanService->addBan($hashHex, $this->moduleContext->currentUserId);

		$this->moduleContext->actionLoggerService->logAction(
			'Perceptually banned file (pHash: ' . $hashHex . ') from post No.' . $post->getNumber(),
			$boardUID
		);

		if ($this->moduleContext->request->isAjax()) {
			sendAjaxAndDetach(['success' => true]);
		} else {
			redirect('back');
		}
	}

	private function computeHashFromAttachment(array $attachment): string {
		$attachmentObj = $this->moduleContext->fileService->getAttachment($attachment['fileId']);
		if (!$attachmentObj) {
			throw new BoardException(_T('attachment_not_found'));
		}

		$mimeType = $attachment['mimeType'] ?? '';
		$filePath = $attachmentObj->getPath();

		if (!file_exists($filePath)) {
			throw new BoardException(_T('perceptual_ban_file_missing'));
		}

		if ($this->perceptualHasher->needsFrameExtraction($mimeType)) {
			$hashHex = $this->perceptualHasher->computeHashFromAnimated($filePath);
		} else {
			$hashHex = $this->perceptualHasher->computeHash($filePath);
		}

		if ($hashHex === null) {
			throw new BoardException(_T('perceptual_ban_hash_failed'));
		}

		return $hashHex;
	}

	private function handleAddBan(): void {
		$hashHex = trim($_POST['phash'] ?? '');

		if ($hashHex === '' || !preg_match('/^[a-fA-F0-9]{16}$/', $hashHex)) {
			throw new BoardException(_T('perceptual_ban_invalid_hash'));
		}

		$this->perceptualBanService->addBan(
			$hashHex,
			$this->moduleContext->currentUserId
		);

		$this->moduleContext->actionLoggerService->logAction(
			'Perceptually banned hash: ' . $hashHex,
			$this->moduleContext->board->getBoardUID()
		);

		redirect($this->moduleUrl);
	}

	private function handleDeletions(): void {
		$entryIDs = $_POST['entryIDs'] ?? null;

		if (empty($entryIDs)) {
			redirect($this->moduleUrl);
		}

		$this->perceptualBanService->deleteEntries($entryIDs);

		$this->moduleContext->actionLoggerService->logAction(
			'Removed ' . count($entryIDs) . ' perceptual file ban(s)',
			$this->moduleContext->board->getBoardUID()
		);

		redirect($this->moduleUrl);
	}

	private function drawIndex(): void {
		$entriesPerPage = $this->getConfig('ACTIONLOG_MAX_PER_PAGE', 50);
		$page = (int) ($_GET['page'] ?? 0);
		$threshold = $this->getConfig('ModuleSettings.perceptualBan.HAMMING_THRESHOLD', 10);

		$entries = $this->perceptualBanService->getEntries($entriesPerPage, $page);
		$totalEntries = $this->perceptualBanService->getTotalEntries();

		$templateRows = [];
		foreach ($entries as $entry) {
			$templateRows[] = [
				'{$ID}' => htmlspecialchars($entry['id']),
				'{$PHASH_HEX}' => htmlspecialchars($entry['phash_hex']),
				'{$ADDED_BY}' => htmlspecialchars($entry['added_by_username'] ?? ''),
				'{$CREATED_AT}' => htmlspecialchars($entry['created_at'] ?? ''),
			];
		}

		$indexHtml = $this->moduleContext->adminPageRenderer->ParseBlock('PERCEPTUAL_BAN_INDEX', [
			'{$ROWS}' => $templateRows,
			'{$MODULE_URL}' => htmlspecialchars($this->moduleUrl),
			'{$PHASH_VALUE}' => '',
			'{$THRESHOLD}' => htmlspecialchars((string) $threshold),
			'{$PERCEPTUAL_BAN_INDEX_TITLE}' => _T('perceptual_ban_index_title'),
			'{$PERCEPTUAL_BAN_HASH_LABEL}' => _T('perceptual_ban_hash_label'),
			'{$PERCEPTUAL_BAN_ADDED_BY_LABEL}' => _T('perceptual_ban_added_by_label'),
			'{$PERCEPTUAL_BAN_DATE_LABEL}' => _T('perceptual_ban_date_label'),
			'{$PERCEPTUAL_BAN_DELETE_LABEL}' => _T('perceptual_ban_delete_label'),
			'{$PERCEPTUAL_BAN_ADD_TITLE}' => _T('perceptual_ban_add_title'),
			'{$PERCEPTUAL_BAN_THRESHOLD_LABEL}' => _T('perceptual_ban_threshold_label'),
			'{$FORM_SUBMIT_BTN}' => _T('form_submit_btn'),
			'{$PERCEPTUAL_BAN_NO_ENTRIES}' => _T('perceptual_ban_no_entries'),
		]);

		$pagerHtml = drawPager($entriesPerPage, $totalEntries, $this->moduleUrl, $this->moduleContext->request);

		$this->renderPage($indexHtml, $pagerHtml);
	}

	private function renderPage(string $pageContentHtml, string $pagerHtml = ''): void {
		$pageContent = [
			'{$PAGE_CONTENT}' => $pageContentHtml,
			'{$PAGER}' => $pagerHtml,
		];

		$pageHtml = $this->moduleContext->adminPageRenderer->ParsePage('GLOBAL_ADMIN_PAGE_CONTENT', $pageContent, true);
		echo $pageHtml;
	}
}