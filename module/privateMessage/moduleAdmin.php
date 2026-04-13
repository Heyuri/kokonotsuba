<?php

namespace Kokonotsuba\Modules\privateMessage;

require_once __DIR__ . '/messageRepository.php';
require_once __DIR__ . '/messageRenderer.php';
require_once __DIR__ . '/messageUtility.php';

use Kokonotsuba\database\databaseConnection;
use Kokonotsuba\module_classes\abstractModuleAdmin;
use Kokonotsuba\module_classes\traits\AuditableTrait;
use Kokonotsuba\module_classes\traits\BanFileOperationsTrait;
use Kokonotsuba\module_classes\traits\listeners\PostControlHooksTrait;
use Kokonotsuba\post\helper\postDateFormatter;
use Kokonotsuba\userRole;

use function Kokonotsuba\libraries\_T;
use function Kokonotsuba\libraries\html\drawPager;
use function Puchiko\request\redirect;
use function Puchiko\strings\sanitizeStr;

class moduleAdmin extends abstractModuleAdmin {
	use AuditableTrait;
    use PostControlHooksTrait;
	use BanFileOperationsTrait;

	private messageRepository $messageRepository;
	private messageRenderer $messageRenderer;
	private string $modulePageUrl;
	private int $messagesPerPage;

	public function getRequiredRole(): userRole {
		return $this->getConfig('AuthLevels.CAN_MANAGE_PMS', userRole::LEV_ADMIN);
	}

	public function getName(): string {
		return 'Private Message Admin';
	}

	public function getVersion(): string {
		return 'Koko 2025';
	}

	public function initialize(): void {
		$this->modulePageUrl = $this->getModulePageURL([], false, true);
		$this->messagesPerPage = $this->getConfig('ADMIN_PAGE_DEF', 100);

		$databaseConnection = databaseConnection::getInstance();
		$privateMessageTable = getDatabaseSettings()['PRIVATE_MESSAGE_TABLE'];
		$this->messageRepository = new messageRepository($databaseConnection, $privateMessageTable);

		$this->messageRenderer = new messageRenderer(
			$this->moduleContext->adminPageRenderer,
			new messageUtility($this->getModulePageURL(...), $this->getConfig('TRIPSALT')),
			new postDateFormatter($this->getConfig('TIME_ZONE', 0)),
			$this->moduleContext->moduleEngine,
			$this->moduleContext->request
		);

		$this->registerLinksAboveBarHook(_T('admin_nav_pm_title'), $this->modulePageUrl, _T('admin_nav_pm'));
	}

	public function ModulePage(): void {
		$viewPage = $this->moduleContext->request->getParameter('viewPage', 'GET', '');

		if ($viewPage === 'view') {
			$this->renderViewMessage();
			return;
		}

		if ($this->moduleContext->request->isPost()) {
			$action = $this->moduleContext->request->getParameter('action', 'POST', '');

			switch ($action) {
				case 'delete':
					$this->handleDeleteMessages();
					break;
				case 'ban':
					$this->handleBanSelectedIps();
					break;
			}
		}

		$this->renderAdminPage();
	}

	private function handleDeleteMessages(): void {
		$ids = $this->getSelectedIds();
		if (!empty($ids)) {
			$this->messageRepository->deleteMessages($ids);
			$this->logAction('Deleted ' . count($ids) . ' private message(s)', $this->moduleContext->board->getBoardUID());
		}
		redirect($this->moduleContext->request->getReferer());
		exit;
	}

	private function handleBanSelectedIps(): void {
		$ids = $this->getSelectedIds();
		if (empty($ids)) {
			redirect($this->moduleContext->request->getReferer());
			exit;
		}

		$bannedIps = [];
		$banFile = $this->getGlobalBanFilePath();
		$startTime = time();
		$expires = $startTime + $this->calculateBanDuration('1y');

		foreach ($ids as $id) {
			$message = $this->messageRepository->getMessageById($id);
			if ($message && !empty($message['ip_address'])) {
				$ip = $message['ip_address'];
				if (!in_array($ip, $bannedIps, true)) {
					$this->addBanEntry($banFile, $ip, $startTime, $expires, 'Banned via PM admin');
					$bannedIps[] = $ip;
				}
			}
		}

		if (!empty($bannedIps)) {
			$this->logAction('Banned ' . implode(', ', $bannedIps) . ' via PM admin', -1);
		}

		redirect($this->moduleContext->request->getReferer());
		exit;
	}

	private function getSelectedIds(): array {
		$selected = $this->moduleContext->request->getParameter('selected', 'POST', []);
		if (is_array($selected)) {
			return array_map('intval', $selected);
		}
		return [];
	}

	private function renderAdminPage(): void {
		$page = $this->moduleContext->request->hasParameter('page')
			? max(0, (int) $this->moduleContext->request->getParameter('page'))
			: 0;

		$totalEntries = $this->messageRepository->getAllMessagesCount();
		$offset = $page * $this->messagesPerPage;
		$messages = $this->messageRepository->getAllMessages($offset, $this->messagesPerPage);

		$rows = [];
		foreach ($messages as $message) {
			$viewUrl = $this->getModulePageURL(['viewPage' => 'view', 'id' => (int) $message['id']], false, true);
			$rows[] = [
				'{$PM_ID}' => (int) $message['id'],
				'{$PM_SENDER}' => sanitizeStr($message['sender_tripcode']),
				'{$PM_RECIPIENT}' => sanitizeStr($message['recipient_tripcode']),
				'{$PM_SUBJECT}' => sanitizeStr($message['message_subject'] ?? ''),
				'{$PM_BODY}' => $this->messageRenderer->formatPreviewComment($message['message_body'] ?? '', 100),
				'{$PM_VIEW_URL}' => htmlspecialchars($viewUrl),
				'{$PM_IP}' => sanitizeStr($message['ip_address'] ?? ''),
				'{$PM_DATE}' => $this->moduleContext->postDateFormatter->formatFromDateString($message['date_sent']),
			];
		}

		$pagerHtml = drawPager($this->messagesPerPage, $totalEntries, $this->modulePageUrl, $this->moduleContext->request);

		$contentHtml = $this->moduleContext->adminPageRenderer->ParseBlock('PM_ADMIN_PAGE', [
			'{$MODULE_URL}' => sanitizeStr($this->modulePageUrl),
			'{$PAGE_TITLE}' => _T('pm_admin_title'),
			'{$TH_SELECT}' => _T('pm_admin_th_select'),
			'{$TH_SENDER}' => _T('pm_admin_th_sender'),
			'{$TH_RECIPIENT}' => _T('pm_admin_th_recipient'),
			'{$TH_SUBJECT}' => _T('pm_admin_th_subject'),
			'{$TH_BODY}' => _T('pm_admin_th_body'),
			'{$TH_IP}' => _T('pm_admin_th_ip'),
			'{$TH_DATE}' => _T('pm_admin_th_date'),
			'{$DELETE_BTN}' => _T('pm_admin_delete_btn'),
			'{$BAN_BTN}' => _T('pm_admin_ban_btn'),
			'{$MESSAGES}' => $rows,
			'{$HAS_MESSAGES}' => !empty($messages) ? '1' : '',
			'{$NO_MESSAGES_TEXT}' => _T('pm_admin_no_messages'),
			'{$PAGER_HTML}' => $pagerHtml,
		]);

		echo $this->moduleContext->adminPageRenderer->ParsePage('GLOBAL_ADMIN_PAGE_CONTENT', ['{$PAGE_CONTENT}' => $contentHtml], true);
	}

	private function renderViewMessage(): void {
		$id = (int) $this->moduleContext->request->getParameter('id', 'GET', 0);
		$message = $this->messageRepository->getMessageById($id);

		if (!$message) {
			redirect($this->modulePageUrl);
			exit;
		}

		$contentHtml = $this->messageRenderer->renderAdminViewMessage($message, $this->modulePageUrl);

		echo $this->moduleContext->adminPageRenderer->ParsePage('GLOBAL_ADMIN_PAGE_CONTENT', ['{$PAGE_CONTENT}' => $contentHtml], true);
	}
}
