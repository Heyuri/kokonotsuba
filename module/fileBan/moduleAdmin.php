<?php

namespace Kokonotsuba\Modules\fileBan;

require_once __DIR__ . '/fileBanRepository.php';
require_once __DIR__ . '/fileBanService.php';
require_once __DIR__ . '/fileBanLib.php';

use Kokonotsuba\error\BoardException;
use Kokonotsuba\module_classes\abstractModuleAdmin;
use Kokonotsuba\module_classes\traits\listeners\PostControlHooksTrait;
use Kokonotsuba\userRole;

use function Kokonotsuba\libraries\_T;
use function Kokonotsuba\libraries\html\drawPager;
use function Kokonotsuba\libraries\requirePostWithCsrf;
use function Kokonotsuba\libraries\searchBoardArrayForBoard;
use function Puchiko\json\sendAjaxAndDetach;
use function Puchiko\request\redirect;
use function Kokonotsuba\Modules\fileBan\getFileBanService;

class moduleAdmin extends abstractModuleAdmin {
	use PostControlHooksTrait;

	private fileBanService $fileBanService;
	private string $moduleUrl;

	public function getRequiredRole(): userRole {
		return $this->getConfig('AuthLevels.CAN_BAN_FILES', userRole::LEV_MODERATOR);
	}

	public function getName(): string {
		return 'File ban management';
	}

	public function getVersion(): string {
		return '1.0';
	}

	public function initialize(): void {
		$this->moduleUrl = $this->getModulePageURL([], false);

		$this->fileBanService = getFileBanService($this->moduleContext->transactionManager);

		$this->listenProtected('ModerateAttachmentWidget', function(array &$widgets, array &$fileData) {
			$this->onAttachmentWidget($widgets, $fileData);
		});
		$this->registerLinksAboveBarHook(_T('admin_nav_file_ban_title'), $this->moduleUrl, _T('admin_nav_file_ban'));

		$this->listenProtected('ModuleAdminHeader', function(string &$moduleHeader) {
			$this->includeScript('fileBan.js', $moduleHeader);
		});
	}

	private function onAttachmentWidget(array &$widgets, array &$fileData): void {
		$md5 = $fileData['fileMd5'] ?? '';
		if (empty($md5)) {
			return;
		}

		$banned = $this->fileBanService->findBannedHashes([$md5]);
		if (!empty($banned)) {
			return;
		}

		// Ban file only
		$banUrl = $this->getModulePageURL([
			'action' => 'banOnly',
			'post_uid' => $fileData['postUid'],
			'fileId' => $fileData['fileId'],
		], false, true);
		$widgets[] = $this->buildWidgetEntry($banUrl, 'BanFile', _T('file_ban_btn_title'), '');

		// Ban & delete (only if file can be deleted)
		if ($this->canDeleteAttachment($fileData)) {
			$bdUrl = $this->getModulePageURL([
				'action' => 'banAndDelete',
				'post_uid' => $fileData['postUid'],
				'fileId' => $fileData['fileId'],
			], false, true);
			$widgets[] = $this->buildWidgetEntry($bdUrl, 'BanDeleteFile', _T('file_ban_bd_btn_title'), '');
		}
	}

	public function ModulePage(): void {
		$action = $_REQUEST['action'] ?? '';

		if ($action === 'banAndDelete') {
			requirePostWithCsrf($this->moduleContext->request);
			$this->handleBanAndDelete();
			return;
		}

		if ($action === 'banOnly') {
			requirePostWithCsrf($this->moduleContext->request);
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
			throw new BoardException(_T('file_ban_invalid_action'));
		}
	}

	private function handleBanAndDelete(): void {
		$postUid = $this->moduleContext->request->param ?? null;
		$post = $this->fetchValidatedPost($postUid);

		$fileId = (int) ($_GET['fileId'] ?? 0);
		if (empty($fileId)) {
			throw new BoardException(_T('file_ban_invalid_hash'));
		}

		$attachment = $post->getAttachmentById($fileId);
		if (!$attachment) {
			throw new BoardException(_T('attachment_not_found'));
		}

		$md5 = $attachment['fileMd5'] ?? '';
		if ($md5 === '' || !preg_match('/^[a-fA-F0-9]{32}$/', $md5)) {
			throw new BoardException(_T('file_ban_invalid_hash'));
		}

		$board = searchBoardArrayForBoard($post->getBoardUid());
		$boardUID = $board->getBoardUID();

		// Ban the hash
		$this->fileBanService->addBan($md5, $this->moduleContext->currentUserId);

		// Delete the file
		$this->moduleContext->deletedPostsService->deleteFilesFromPosts([$attachment], $this->moduleContext->currentUserId);

		$this->moduleContext->actionLoggerService->logAction(
			'Banned and deleted file hash: ' . $md5 . ' from post No.' . $post->getNumber(),
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
			throw new BoardException(_T('file_ban_invalid_hash'));
		}

		$attachment = $post->getAttachmentById($fileId);
		if (!$attachment) {
			throw new BoardException(_T('attachment_not_found'));
		}

		$md5 = $attachment['fileMd5'] ?? '';
		if ($md5 === '' || !preg_match('/^[a-fA-F0-9]{32}$/', $md5)) {
			throw new BoardException(_T('file_ban_invalid_hash'));
		}

		$board = searchBoardArrayForBoard($post->getBoardUID());
		$boardUID = $board->getBoardUID();

		// Ban the hash only — no file deletion
		$this->fileBanService->addBan($md5, $this->moduleContext->currentUserId);

		$this->moduleContext->actionLoggerService->logAction(
			'Banned file hash: ' . $md5 . ' from post No.' . $post->getNumber(),
			$boardUID
		);

		if ($this->moduleContext->request->isAjax()) {
			sendAjaxAndDetach(['success' => true]);
		}
        else {
    		redirect('back');
        }
	}

	private function handleAddBan(): void {
		$md5 = trim($_POST['md5'] ?? '');

		if ($md5 === '' || !preg_match('/^[a-fA-F0-9]{32}$/', $md5)) {
			throw new BoardException(_T('file_ban_invalid_hash'));
		}

		$this->fileBanService->addBan(
			$md5,
			$this->moduleContext->currentUserId
		);

		$this->moduleContext->actionLoggerService->logAction(
			'Banned file hash: ' . $md5,
			$this->moduleContext->board->getBoardUID()
		);

		redirect($this->moduleUrl);
	}

	private function handleDeletions(): void {
		$entryIDs = $_POST['entryIDs'] ?? null;

		if (empty($entryIDs)) {
			redirect($this->moduleUrl);
		}

		$this->fileBanService->deleteEntries($entryIDs);

		$this->moduleContext->actionLoggerService->logAction(
			'Removed ' . count($entryIDs) . ' file ban(s)',
			$this->moduleContext->board->getBoardUID()
		);

		redirect($this->moduleUrl);
	}

	private function drawIndex(): void {
		$entriesPerPage = $this->getConfig('ACTIONLOG_MAX_PER_PAGE', 50);
		$page = (int) ($_GET['page'] ?? 0);

		$entries = $this->fileBanService->getEntries($entriesPerPage, $page);
		$totalEntries = $this->fileBanService->getTotalEntries();

		$templateRows = [];
		foreach ($entries as $entry) {
			$templateRows[] = [
				'{$ID}' => htmlspecialchars($entry['id']),
				'{$FILE_MD5}' => htmlspecialchars($entry['file_md5']),
				'{$ADDED_BY}' => htmlspecialchars($entry['added_by_username'] ?? ''),
				'{$CREATED_AT}' => htmlspecialchars($entry['created_at'] ?? ''),
			];
		}

		$indexHtml = $this->moduleContext->adminPageRenderer->ParseBlock('FILE_BAN_INDEX', [
			'{$ROWS}' => $templateRows,
			'{$MODULE_URL}' => htmlspecialchars($this->moduleUrl),
			'{$MD5_VALUE}' => '',
			'{$FILE_BAN_INDEX_TITLE}' => _T('file_ban_index_title'),
			'{$FILE_BAN_HASH_LABEL}' => _T('file_ban_hash_label'),
			'{$FILE_BAN_ADDED_BY_LABEL}' => _T('file_ban_added_by_label'),
			'{$FILE_BAN_DATE_LABEL}' => _T('file_ban_date_label'),
			'{$FILE_BAN_DELETE_LABEL}' => _T('file_ban_delete_label'),
			'{$FILE_BAN_ADD_TITLE}' => _T('file_ban_add_title'),
			'{$FORM_SUBMIT_BTN}' => _T('form_submit_btn'),
			'{$FILE_BAN_NO_ENTRIES}' => _T('file_ban_no_entries'),
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
