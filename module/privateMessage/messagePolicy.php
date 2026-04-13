<?php

namespace Kokonotsuba\Modules\privateMessage;

use Kokonotsuba\policy\policyBase;

class messagePolicy extends policyBase {
	private messageService $messageService;

	public function setMessageService(messageService $messageService): void {
		$this->messageService = $messageService;
	}

	public function canViewContact(string $contactTripcode, string $userTripCode): bool {
		// users can always view conversations they are part of
		// the repository queries already filter by user tripcode, so this
		// just ensures the user has an existing conversation or is starting one
		return $this->messageService->hasConversationWith($userTripCode, $contactTripcode);
	}

	public function canSendMessage(string $userTripCode): bool {
		// any logged-in user with a valid tripcode can send messages
		return !empty($userTripCode);
	}
}