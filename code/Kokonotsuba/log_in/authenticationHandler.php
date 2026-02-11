<?php

namespace Kokonotsuba\log_in;

// Handle authentication
class authenticationHandler {
	public function verifyPasswordHash($userEnteredPassword, $account) {
		$hashedPassword = $account->getPasswordHash() ?? '';
		
		if(password_verify($userEnteredPassword, $hashedPassword)) return true;
		else return false;
	}
}
