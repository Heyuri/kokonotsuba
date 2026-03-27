<?php

namespace Kokonotsuba\Modules\privateMessage;

use Kokonotsuba\policy\policyBase;

class messagePolicy extends policyBase {
	private messageService $messageService;

	public function setMessageService(messageService $messageService): void {
		$this->messageService = $messageService;
	}
}