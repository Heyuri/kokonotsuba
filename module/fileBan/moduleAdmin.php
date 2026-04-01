<?php

namespace Kokonotsuba\Modules\fileBan;

require_once __DIR__ . '/fileBanRepository.php';
require_once __DIR__ . '/fileBanService.php';
require_once __DIR__ . '/fileBanLib.php';

use Kokonotsuba\error\BoardException;
use Kokonotsuba\module_classes\abstractModuleAdmin;
use Kokonotsuba\userRole;

use function Kokonotsuba\libraries\_T;
use function Kokonotsuba\libraries\html\drawPager;
use function Kokonotsuba\libraries\attachmentFileExists;
use function Kokonotsuba\libraries\searchBoardArrayForBoard;
use function Kokonotsuba\libraries\getPageOfThread;
use function Kokonotsuba\libraries\validatePostInput;
use function Kokonotsuba\libraries\html\getCurrentUrlNoQuery;
use function Puchiko\json\isJavascriptRequest;
use function Puchiko\json\sendAjaxAndDetach;
use function Puchiko\request\isGetRequest;
use function Puchiko\request\isPostRequest;
use function Puchiko\request\redirect;
use function Kokonotsuba\Modules\fileBan\getFileBanService;

class moduleAdmin extends abstractModuleAdmin {
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

		// "Ban file" button on attachments - same hook point as "Delete file" (ModerateAttachment)
		$this->moduleContext->moduleEngine->addRoleProtectedListener(
			$this->getRequiredRole(),
			'ModerateAttachment',
			function(
				string &$attachmentProperties,
				string &$attachmentImage,
				string &$attachmentUrl,
				array &$attachment
			) {
				$this->onRenderAttachment($attachmentProperties, $attachment);
			}
		);

		// Nav link
		$this->moduleContext->moduleEngine->addRoleProtectedListener(
			$this->getRequiredRole(),
			'LinksAboveBar',
			function(string &$linkHtml) {
				$this->onRenderLinksAboveBar($linkHtml);
			}
		);

		// JS for seamless ban+delete
		$this->moduleContext->moduleEngine->addRoleProtectedListener(
			$this->getRequiredRole(),
			'ModuleAdminHeader',
			function(string &$moduleHeader) {
				$this->includeScript('fileBan.js', $moduleHeader);
			}
		);
	}

	private function onRenderAttachment(string &$attachmentProperties, array &$attachment): void {
		$md5 = $attachment['fileMd5'] ?? '';
		if (empty($md5)) {
			return;
		}

		// Don't show ban buttons if the hash is already banned
		$banned = $this->fileBanService->findBannedHashes([$md5]);
		if (!empty($banned)) {
			return;
		}

		// BF (ban only) — show on both live and already-deleted files
		if (!empty($attachment)) {
			$banUrl = $this->getModulePageURL([
				'action' => 'banOnly',
				'post_uid' => $attachment['postUid'],
				'fileId' => $attachment['fileId'],
			], false, true);

			$attachmentProperties .= ' <span class="adminFunctions adminBanFileFunction attachmentButton">[<a href="' . htmlspecialchars($banUrl) . '" title="' . _T('file_ban_btn_title') . '">BF</a>]</span>';
		}

		// B&D (ban + delete) — only on live, non-deleted files
		if ($this->canRenderDeleteButton($attachment)) {
			$bdUrl = $this->getModulePageURL([
				'action' => 'banAndDelete',
				'post_uid' => $attachment['postUid'],
				'fileId' => $attachment['fileId'],
			], false, true);

			$attachmentProperties .= ' <span class="adminFunctions adminBanDeleteFileFunction attachmentButton">[<a href="' . htmlspecialchars($bdUrl) . '" title="' . _T('file_ban_bd_btn_title') . '">B&amp;D</a>]</span>';
		}
	}

	private function canRenderDeleteButton(array $attachment): bool {
		if (!empty($attachment)) {
			if (attachmentFileExists($attachment) && !$attachment['isDeleted']) {
				return true;
			}
		}
		return false;
	}

	private function onRenderLinksAboveBar(string &$linkHtml): void {
		$linkHtml .= '<li class="adminNavLink"><a title="' . _T('admin_nav_file_ban_title') . '" href="' . htmlspecialchars($this->moduleUrl) . '">' . _T('admin_nav_file_ban') . '</a></li>';
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

		if (isPostRequest()) {
			$this->handleRequests();
		} elseif (isGetRequest()) {
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
		$postUid = $_GET['post_uid'] ?? null;

		validatePostInput($postUid);

		$post = $this->moduleContext->postRepository->getPostByUid($postUid, true);

		validatePostInput($post, false);

		$fileId = (int) ($_GET['fileId'] ?? 0);
		if (empty($fileId)) {
			throw new BoardException(_T('file_ban_invalid_hash'));
		}

		$attachment = $post['attachments'][$fileId] ?? false;
		if (!$attachment) {
			throw new BoardException(_T('attachment_not_found'));
		}

		$md5 = $attachment['fileMd5'] ?? '';
		if ($md5 === '' || !preg_match('/^[a-fA-F0-9]{32}$/', $md5)) {
			throw new BoardException(_T('file_ban_invalid_hash'));
		}

		$board = searchBoardArrayForBoard($post['boardUID']);
		$boardUID = $board->getBoardUID();

		// Ban the hash
		$this->fileBanService->addBan($md5, $this->moduleContext->currentUserId);

		// Delete the file
		$this->moduleContext->deletedPostsService->deleteFilesFromPosts([$attachment], $this->moduleContext->currentUserId);

		$this->moduleContext->actionLoggerService->logAction(
			'Banned and deleted file hash: ' . $md5 . ' from post No.' . $post['no'],
			$boardUID
		);

		if (isJavascriptRequest()) {
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

		validatePostInput($postUid);

		$post = $this->moduleContext->postRepository->getPostByUid($postUid, true);

		validatePostInput($post, false);

		$fileId = (int) ($_GET['fileId'] ?? 0);
		if (empty($fileId)) {
			throw new BoardException(_T('file_ban_invalid_hash'));
		}

		$attachment = $post['attachments'][$fileId] ?? false;
		if (!$attachment) {
			throw new BoardException(_T('attachment_not_found'));
		}

		$md5 = $attachment['fileMd5'] ?? '';
		if ($md5 === '' || !preg_match('/^[a-fA-F0-9]{32}$/', $md5)) {
			throw new BoardException(_T('file_ban_invalid_hash'));
		}

		$board = searchBoardArrayForBoard($post['boardUID']);
		$boardUID = $board->getBoardUID();

		// Ban the hash only — no file deletion
		$this->fileBanService->addBan($md5, $this->moduleContext->currentUserId);

		$this->moduleContext->actionLoggerService->logAction(
			'Banned file hash: ' . $md5 . ' from post No.' . $post['no'],
			$boardUID
		);

		if (isJavascriptRequest()) {
			sendAjaxAndDetach(['success' => true]);
		}
        else {
    		redirect('back');
        }
	}

	private function getDeletedLinkForFile(int $fileId): string {
		$deletedPost = $this->moduleContext->deletedPostsService->getDeletedPostRowByFileId($fileId);
		$deletedPostId = $deletedPost['deleted_post_id'];
		$baseUrl = getCurrentUrlNoQuery();

		$urlParameters = [
			'pageName' => 'viewMore',
			'deletedPostId' => $deletedPostId,
			'moduleMode' => 'admin',
			'mode' => 'module',
			'load' => 'deletedPosts'
		];

		return $baseUrl . '?' . http_build_query($urlParameters);
	}

	private function rebuildBoardForPost($board, array $post): void {
		if ($post['is_op']) {
			$board->rebuildBoard();
		} else {
			$thread_uid = $post['thread_uid'];
			$threads = $this->moduleContext->threadService->getThreadListFromBoard($board);
			$pageToRebuild = getPageOfThread($thread_uid, $threads, $board->getConfigValue('PAGE_DEF', 15));
			$pageToRebuild = min($pageToRebuild, $this->getConfig('STATIC_HTML_UNTIL'));
			$board->rebuildBoardPage($pageToRebuild);
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

		$pagerHtml = drawPager($entriesPerPage, $totalEntries, $this->moduleUrl);

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
