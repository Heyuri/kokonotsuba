<?php

namespace Kokonotsuba\Modules\privateMessage;

use Kokonotsuba\template\pageRenderer;

use function Kokonotsuba\libraries\_T;
use function Puchiko\strings\sanitizeStr;

class messageRenderer {
	public function __construct(
		private pageRenderer $adminPageRenderer,
		private messageUtility $messageUtility,
	) {}

	private function renderPmPage(string $contentHtml): void {
		// add the pm theading
		$wrappedHtml = $this->adminPageRenderer->ParseBlock('PM_PAGE', [
			'{$PAGE_TITLE}' => _T('pm_main_title'),
			'{$PAGE_CONTENT}' => $contentHtml
		]);

		// Render the PM page with the provided content
		echo $this->adminPageRenderer->ParsePage('GLOBAL_ADMIN_PAGE_CONTENT', ['{$PAGE_CONTENT}' => $wrappedHtml], false);
	}

	public function renderLoginPage(string $modulePageUrl): void {
		// Render the login form
		$formHtml = $this->adminPageRenderer->ParseBlock('PM_LOGIN_FORM', [
			'{$MODULE_PAGE_URL}' => sanitizeStr($modulePageUrl),
			'{$LOGIN_FORM_HEADING}' => _T('pm_login_page_title'),
			'{$TRIPCODE_LOGIN_DESCRIPTION}' => _T('pm_login_description'),
			'{$TRIPCODE_LOGIN_HASH_NOTE}' => _T('pm_tripcode_login_hash_note'),
			'{$TRIPCODE_LOGIN_LABEL}' => _T('pm_tripcode_login_label'),
			'{$LOGIN_SUBMIT}' => _T('form_submit_btn'),
		]);

		// render the page
		$this->renderPmPage($formHtml);
	}

	private function getContactDisplayName(array $contact): string {
		// If sender and recipient are the same, it's self
		if ($contact['sender_tripcode'] === $contact['recipient_tripcode']) {
			return _T('pm_contact_self');
		}
		// If current user is sender, show recipient; otherwise, show sender
		return ($contact['sender_tripcode'] === $this->messageUtility->getUsertripCode())
			? $contact['recipient_tripcode']
			: $contact['sender_tripcode'];
	}

	private function generateContactTemplates(array $contacts): array {
		// Generate contact templates for the inbox page
		$contactTemplates = [];

		// loop through contacts and create template data for each
		foreach ($contacts as $contact) {
			$displayName = sanitizeStr($this->getContactDisplayName($contact));
			$contactTemplates[] = [
				'{$CONTACT_NAME}' => $displayName,
				'{$CONTACT_TRIPCODE}' => $displayName,
				'{$CONTACT_LAST_MESSAGE}' => sanitizeStr($contact['message_subject']),
				'{$CONTACT_LAST_MESSAGE_TIME}' => sanitizeStr($contact['timestamp_added']),
				'{$CONTACT_IS_UNREAD}' => ($contact['is_read'] == 0) ? 'pm-unread' : '',
				'{$CONTACT_THREAD_URL}' => sanitizeStr($this->messageUtility->getModulePageURL(['tripcode' => $contact['sender_tripcode']])),
			];
		}
		return $contactTemplates;
	}

	public function renderInboxPage(array $contacts, string $messageThreadHtml, string $modulePageUrl): void {
		// Render the inbox page
		$inboxHtml = $this->adminPageRenderer->ParseBlock('PM_INBOX_PAGE', [
			'{$MODULE_PAGE_URL}' => sanitizeStr($modulePageUrl),
			'{$INBOX_TITLE}' => _T('pm_inbox_page_title'),
			'{$CONTACTS}' => $this->generateContactTemplates($contacts),
			'{$PRIVATE_THREAD}' => $messageThreadHtml
		]);

		// render the inbox page
		echo $this->adminPageRenderer->ParsePage('GLOBAL_ADMIN_PAGE_CONTENT', ['{$PAGE_CONTENT}' => $inboxHtml], false);
	}
}