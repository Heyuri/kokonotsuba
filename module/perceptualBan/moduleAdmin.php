<?php

namespace Kokonotsuba\Modules\perceptualBan;

require_once __DIR__ . '/perceptualBanRepository.php';
require_once __DIR__ . '/perceptualBanService.php';
require_once __DIR__ . '/perceptualBanLib.php';
require_once __DIR__ . '/perceptualHasher.php';

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
use function Kokonotsuba\Modules\perceptualBan\getPerceptualBanService;
use function Kokonotsuba\Modules\perceptualBan\getPerceptualHasher;

class moduleAdmin extends abstractModuleAdmin {
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

		// "Ban file perceptually" button on attachments
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
	}

	private function onRenderAttachment(string &$attachmentProperties, array &$attachment): void {
		if (empty($attachment)) {
			return;
		}

		$mimeType = $attachment['mimeType'] ?? '';
		if (!$this->perceptualHasher->isHashableMedia($mimeType)) {
			return;
		}

		// PBF (perceptual ban only)
		$banUrl = $this->getModulePageURL([
			'action' => 'banOnly',
			'post_uid' => $attachment['postUid'],
			'fileId' => $attachment['fileId'],
		], false, true);

		$attachmentProperties .= ' <span class="adminFunctions adminPerceptualBanFunction attachmentButton">[<a href="' . htmlspecialchars($banUrl) . '" title="' . _T('perceptual_ban_btn_title') . '">PBF</a>]</span>';

		// PB&D (perceptual ban + delete)
		if ($this->canRenderDeleteButton($attachment)) {
			$bdUrl = $this->getModulePageURL([
				'action' => 'banAndDelete',
				'post_uid' => $attachment['postUid'],
				'fileId' => $attachment['fileId'],
			], false, true);

			$attachmentProperties .= ' <span class="adminFunctions adminPerceptualBanDeleteFunction attachmentButton">[<a href="' . htmlspecialchars($bdUrl) . '" title="' . _T('perceptual_ban_bd_btn_title') . '">PB&amp;D</a>]</span>';
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
		$linkHtml .= '<li class="adminNavLink"><a title="' . _T('admin_nav_perceptual_ban_title') . '" href="' . htmlspecialchars($this->moduleUrl) . '">' . _T('admin_nav_perceptual_ban') . '</a></li>';
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
			throw new BoardException(_T('perceptual_ban_invalid_action'));
		}
	}

	private function handleBanAndDelete(): void {
		$postUid = $_GET['post_uid'] ?? null;

		validatePostInput($postUid);

		$post = $this->moduleContext->postRepository->getPostByUid($postUid, true);

		validatePostInput($post, false);

		$fileId = (int) ($_GET['fileId'] ?? 0);
		if (empty($fileId)) {
			throw new BoardException(_T('perceptual_ban_no_file'));
		}

		$attachment = $post['attachments'][$fileId] ?? false;
		if (!$attachment) {
			throw new BoardException(_T('attachment_not_found'));
		}

		// compute perceptual hash from the actual file
		$hashHex = $this->computeHashFromAttachment($attachment);

		$board = searchBoardArrayForBoard($post['boardUID']);
		$boardUID = $board->getBoardUID();

		// ban the perceptual hash
		$this->perceptualBanService->addBan($hashHex, $this->moduleContext->currentUserId);

		// delete the file
		$this->moduleContext->deletedPostsService->deleteFilesFromPosts([$attachment], $this->moduleContext->currentUserId);

		$this->moduleContext->actionLoggerService->logAction(
			'Perceptually banned and deleted file (pHash: ' . $hashHex . ') from post No.' . $post['no'],
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
			throw new BoardException(_T('perceptual_ban_no_file'));
		}

		$attachment = $post['attachments'][$fileId] ?? false;
		if (!$attachment) {
			throw new BoardException(_T('attachment_not_found'));
		}

		// compute perceptual hash from the actual file
		$hashHex = $this->computeHashFromAttachment($attachment);

		$board = searchBoardArrayForBoard($post['boardUID']);
		$boardUID = $board->getBoardUID();

		// ban the perceptual hash only — no file deletion
		$this->perceptualBanService->addBan($hashHex, $this->moduleContext->currentUserId);

		$this->moduleContext->actionLoggerService->logAction(
			'Perceptually banned file (pHash: ' . $hashHex . ') from post No.' . $post['no'],
			$boardUID
		);

		if (isJavascriptRequest()) {
			sendAjaxAndDetach(['success' => true]);
		} else {
			redirect('back');
		}
	}

	private function computeHashFromAttachment(array $attachment): string {
		// resolve the file path from the attachment
		$attachmentObj = $this->moduleContext->fileService->getAttachment($attachment['fileId']);
		if (!$attachmentObj) {
			throw new BoardException(_T('attachment_not_found'));
		}

		$mimeType = $attachment['mimeType'] ?? '';

		if ($this->perceptualHasher->needsFrameExtraction($mimeType)) {
			$filePath = $attachmentObj->getPath();
			if (!file_exists($filePath)) {
				throw new BoardException(_T('perceptual_ban_file_missing'));
			}
			$hashHex = $this->perceptualHasher->computeHashFromAnimated($filePath);
		} else {
			$filePath = $attachmentObj->getPath();
			if (!file_exists($filePath)) {
				throw new BoardException(_T('perceptual_ban_file_missing'));
			}
			$hashHex = $this->perceptualHasher->computeHash($filePath);
		}

		if ($hashHex === null) {
			throw new BoardException(_T('perceptual_ban_hash_failed'));
		}

		return $hashHex;
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
