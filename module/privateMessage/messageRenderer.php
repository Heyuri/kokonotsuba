<?php

namespace Kokonotsuba\Modules\privateMessage;

use Kokonotsuba\module_classes\moduleEngine;
use Kokonotsuba\post\helper\postDateFormatter;
use Kokonotsuba\request\request;
use Kokonotsuba\template\pageRenderer;

use function Kokonotsuba\libraries\_T;
use function Kokonotsuba\libraries\html\drawPager;
use function Kokonotsuba\libraries\html\generatePostNameHtml;
use function Kokonotsuba\libraries\html\quote_unkfunc;
use function Puchiko\strings\newLinesToBreakLines;
use function Puchiko\strings\autoLink;
use function Puchiko\strings\sanitizeStr;
use function Puchiko\strings\truncateText;

class messageRenderer {
	public function __construct(
		private pageRenderer $adminPageRenderer,
		private messageUtility $messageUtility,
		private postDateFormatter $dateFormatter,
		private moduleEngine $moduleEngine,
		private request $request,
	) {}

	private function renderPmPage(string $contentHtml, string $pagerHtml = ''): void {
		$wrappedHtml = $this->adminPageRenderer->ParseBlock('PM_PAGE', [
			'{$PAGE_TITLE}' => _T('pm_main_title'),
			'{$PAGE_CONTENT}' => $contentHtml
		]);

		echo $this->adminPageRenderer->ParsePage('GLOBAL_ADMIN_PAGE_CONTENT', [
			'{$PAGE_CONTENT}' => $wrappedHtml,
			'{$PAGER}' => $pagerHtml,
		], false);
	}

	public function renderLoginPage(string $modulePageUrl): void {
		$formHtml = $this->adminPageRenderer->ParseBlock('PM_LOGIN_FORM', [
			'{$MODULE_PAGE_URL}' => sanitizeStr($modulePageUrl),
			'{$LOGIN_FORM_HEADING}' => _T('pm_login_page_title'),
			'{$TRIPCODE_LOGIN_DESCRIPTION}' => _T('pm_login_description'),
			'{$TRIPCODE_LOGIN_HASH_NOTE}' => _T('pm_tripcode_login_hash_note'),
			'{$TRIPCODE_LOGIN_LABEL}' => _T('pm_tripcode_login_label'),
			'{$LOGIN_SUBMIT}' => _T('form_submit_btn'),
		]);

		$this->renderPmPage($formHtml);
	}

	private function buildSenderNameHtml(array $message): string {
		$hasName = !empty($message['sender_name']);
		$name = $hasName ? $message['sender_name'] : '';
		$tripcode = '';
		$secure_tripcode = '';

		if (!$hasName) {
			$senderTrip = $message['sender_tripcode'];
			if (str_starts_with($senderTrip, '★')) {
				$secure_tripcode = mb_substr($senderTrip, 1);
			} elseif (str_starts_with($senderTrip, '◆')) {
				$tripcode = mb_substr($senderTrip, 1);
			}
		}

		return generatePostNameHtml($this->moduleEngine, $name, $tripcode, $secure_tripcode, '', '');
	}

	public function formatPreviewComment(string $body, int $maxLength = 80): string {
		return $this->applyCommentHooks(sanitizeStr(truncateText($body, $maxLength)));
	}

	/**
	 * Dispatch the PostComment hook on a comment string.
	 * This allows modules like emotes to process PM body text.
	 */
	private function applyCommentHooks(string $comment): string {
		$this->moduleEngine->dispatch('PostComment', [&$comment]);
		$comment = quote_unkfunc($comment);
		$comment = autoLink($comment);
		return $comment;
	}

	private function getComposeFormVariables(string $modulePageUrl, string $prefillRecipient = '', string $prefillSubject = '', string $prefillBody = ''): array {
		return [
			'{$MODULE_PAGE_URL}' => sanitizeStr($modulePageUrl),
			'{$RECIPIENT_LABEL}' => _T('pm_recipient_label'),
			'{$RECIPIENT_PLACEHOLDER}' => _T('pm_recipient_placeholder'),
			'{$NAME_LABEL}' => _T('pm_name_label'),
			'{$SUBJECT_LABEL}' => _T('pm_subject_label'),
			'{$BODY_LABEL}' => _T('pm_body_label'),
			'{$SEND_LABEL}' => _T('pm_send_btn'),
			'{$PREFILL_RECIPIENT}' => sanitizeStr($prefillRecipient),
			'{$PREFILL_SUBJECT}' => sanitizeStr($prefillSubject),
			'{$PREFILL_BODY}' => sanitizeStr($prefillBody) . "\n\n",
		];
	}

	private function buildQuotedBody(string $rawBody): string {
		$lines = explode("\n", $rawBody);
		$quoted = array_map(fn(string $line) => '> ' . $line, $lines);
		return implode("\n", $quoted) . "\n";
	}

	private function buildReplySubject(string $originalSubject): string {
		if ($originalSubject === '') return '';
		return 'Re: ' . $originalSubject;
	}

	private function generateMessageRowTemplates(array $messages, string $userTripCode, string $modulePageUrl): array {
		$rows = [];

		foreach ($messages as $message) {
			$isSent = ($message['sender_tripcode'] === $userTripCode);
			$isRead = (bool) $message['is_read'];

			// for sent messages, show recipient; for received, show sender
			$otherTrip = $isSent ? $message['recipient_tripcode'] : $message['sender_tripcode'];

			$rows[] = [
				'{$PM_ID}' => (int) $message['id'],
				'{$PM_DIRECTION}' => $isSent ? _T('pm_direction_sent') : _T('pm_direction_received'),
				'{$PM_DIRECTION_CLASS}' => $isSent ? 'pmSent' : 'pmReceived',
				'{$PM_OTHER_TRIP}' => sanitizeStr($otherTrip),
				'{$PM_SUBJECT}' => sanitizeStr($message['message_subject'] ?? ''),
				'{$PM_PREVIEW}' => $this->formatPreviewComment($message['message_body'] ?? '', 80),
				'{$PM_DATE}' => $this->dateFormatter->formatFromDateString($message['date_sent']),
				'{$PM_IS_UNREAD}' => (!$isSent && !$isRead) ? '1' : '',
				'{$PM_ROW_CLASS}' => (!$isSent && !$isRead) ? 'pmUnread' : 'pmRead',
				'{$PM_VIEW_URL}' => sanitizeStr($modulePageUrl . '&view=' . (int) $message['id']),
				'{$PM_VIEW_LABEL}' => _T('pm_view_label'),
			];
		}
		return $rows;
	}

	public function renderInboxPage(
		array $messages,
		string $modulePageUrl,
		string $userTripCode,
		int $totalEntries,
		int $messagesPerPage
	): void {
		$userTrip = sanitizeStr($this->messageUtility->getUsertripCode() ?? '');
		$pagerHtml = drawPager($messagesPerPage, $totalEntries, $modulePageUrl, $this->request);
		$rows = $this->generateMessageRowTemplates($messages, $userTripCode, $modulePageUrl);

		$inboxHtml = $this->adminPageRenderer->ParseBlock('PM_INBOX_PAGE', array_merge(
			$this->getComposeFormVariables($modulePageUrl),
			[
			'{$INBOX_TITLE}' => _T('pm_inbox_page_title'),
			'{$LOGGED_IN_AS}' => _T('pm_logged_in_as') . ' ' . $userTrip,
			'{$LOGOUT_LABEL}' => _T('pm_logout_btn'),
			'{$PM_TABLE_FROM}' => _T('pm_table_from'),
			'{$PM_TABLE_SUBJECT}' => _T('pm_table_subject'),
			'{$PM_TABLE_PREVIEW}' => _T('pm_table_preview'),
			'{$PM_TABLE_DATE}' => _T('pm_table_date'),
			'{$MESSAGES}' => $rows,
			'{$HAS_MESSAGES}' => !empty($messages) ? '1' : '',
			'{$NO_MESSAGES_TEXT}' => _T('pm_no_messages'),
		]));

		$this->renderPmPage($inboxHtml, $pagerHtml);
	}

	public function renderViewMessage(array $message, string $modulePageUrl, string $userTripCode): void {
		$nameHtml = $this->buildSenderNameHtml($message);
		$isSent = ($message['sender_tripcode'] === $userTripCode);

		// apply PostComment hook for emotes/bbcode rendering
		$bodyHtml = newLinesToBreakLines(sanitizeStr($message['message_body']));
		$bodyHtml = $this->applyCommentHooks($bodyHtml);

		// build reply prefills
		$replyRecipient = $message['sender_tripcode'];
		$replySubject = $this->buildReplySubject($message['message_subject'] ?? '');
		$replyBody = $this->buildQuotedBody($message['message_body'] ?? '');

		$viewHtml = $this->adminPageRenderer->ParseBlock('PM_VIEW_MESSAGE', array_merge(
			$this->getComposeFormVariables($modulePageUrl, $replyRecipient, $replySubject, $replyBody),
			[
			'{$BACK_LABEL}' => _T('pm_back_to_inbox'),
			'{$REPLY_LABEL}' => _T('pm_reply_label'),
			'{$SHOW_REPLY}' => '1',
			'{$PM_SENDER_NAME}' => $nameHtml,
			'{$PM_SENDER_TRIP}' => sanitizeStr($message['sender_tripcode']),
			'{$PM_RECIPIENT_TRIP}' => sanitizeStr($message['recipient_tripcode']),
			'{$PM_SUBJECT}' => sanitizeStr($message['message_subject'] ?? ''),
			'{$PM_BODY}' => $bodyHtml,
			'{$PM_IP}' => '',
			'{$IP_LABEL}' => '',
			'{$PM_DATE}' => $this->dateFormatter->formatFromDateString($message['date_sent']),
			'{$PM_DIRECTION}' => $isSent ? _T('pm_direction_sent') : _T('pm_direction_received'),
			'{$PM_IS_SENT}' => $isSent ? '1' : '',
			'{$FROM_LABEL}' => _T('pm_from_label'),
			'{$TO_LABEL}' => _T('pm_to_label'),
			'{$DATE_LABEL}' => _T('pm_date_label'),
		]));

		$this->renderPmPage($viewHtml);
	}

	public function renderAdminViewMessage(array $message, string $backUrl): string {
		$nameHtml = $this->buildSenderNameHtml($message);

		$bodyHtml = newLinesToBreakLines(sanitizeStr($message['message_body']));
		$bodyHtml = $this->applyCommentHooks($bodyHtml);

		return $this->adminPageRenderer->ParseBlock('PM_VIEW_MESSAGE', [
			'{$MODULE_PAGE_URL}' => htmlspecialchars($backUrl),
			'{$BACK_LABEL}' => _T('pm_admin_back'),
			'{$SHOW_REPLY}' => '',
			'{$REPLY_LABEL}' => '',
			'{$PM_SENDER_NAME}' => $nameHtml,
			'{$PM_SENDER_TRIP}' => sanitizeStr($message['sender_tripcode']),
			'{$PM_RECIPIENT_TRIP}' => sanitizeStr($message['recipient_tripcode']),
			'{$PM_SUBJECT}' => sanitizeStr($message['message_subject'] ?? ''),
			'{$PM_BODY}' => $bodyHtml,
			'{$PM_IP}' => sanitizeStr($message['ip_address'] ?? ''),
			'{$IP_LABEL}' => _T('pm_admin_th_ip'),
			'{$PM_DATE}' => $this->dateFormatter->formatFromDateString($message['date_sent']),
			'{$FROM_LABEL}' => _T('pm_from_label'),
			'{$TO_LABEL}' => _T('pm_to_label'),
			'{$DATE_LABEL}' => _T('pm_date_label'),
		]);
	}
}