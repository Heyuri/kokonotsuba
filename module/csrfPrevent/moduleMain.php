<?php

namespace Kokonotsuba\Modules\csrfPrevent;

use Kokonotsuba\error\BoardException;
use Kokonotsuba\module_classes\abstractModuleMain;
use Kokonotsuba\module_classes\traits\listeners\RegistBeginListenerTrait;
use Kokonotsuba\module_classes\traits\listeners\PostFormListenerTrait;

use function Puchiko\strings\sanitizeStr;

class moduleMain extends abstractModuleMain {
	use RegistBeginListenerTrait;
	use PostFormListenerTrait;

	private const TOKEN_FIELD = 'csrf_token';

	public function getName(): string {
		return 'mod_csrf_prevent : 防止偽造跨站請求 (CSRF)';
	}

	public function getVersion(): string {
		return 'Koko BBS Release 2';
	}

	public function initialize(): void {
		$this->listenRegistBegin('onRegistBegin');

		$this->listenPostForm('onRenderPostForm');
	}

	private function getSessionToken(): string {
		if (empty($_SESSION['csrf_token'])) {
			$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
		}
		return $_SESSION['csrf_token'];
	}

	public function onRenderPostForm(string &$postForm): void {
		$token = sanitizeStr($this->getSessionToken());
		$postForm .= '<input type="hidden" name="' . self::TOKEN_FIELD . '" value="' . $token . '">';
	}

	public function onRegistBegin(): void {
		$submittedToken = $this->moduleContext->request->getParameter(self::TOKEN_FIELD, 'POST', '');
		$sessionToken = $this->getSessionToken();

		if (!hash_equals($sessionToken, $submittedToken)) {
			throw new BoardException('ERROR: CSRF detected!');
		}
	}
}//End-Of-Module
