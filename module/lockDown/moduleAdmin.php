<?php

namespace Kokonotsuba\Modules\lockDown;

include_once __DIR__ . '/lockDownLibrary.php';

use Kokonotsuba\error\BoardException;
use Kokonotsuba\module_classes\abstractModuleAdmin;
use Kokonotsuba\module_classes\traits\listeners\PostControlHooksTrait;
use Kokonotsuba\userRole;

use function Kokonotsuba\libraries\_T;
use function Kokonotsuba\libraries\getCsrfHiddenInput;
use function Kokonotsuba\libraries\getUsernameFromSession;
use function Puchiko\request\redirect;
use function Puchiko\strings\sanitizeStr;

class moduleAdmin extends abstractModuleAdmin {
	use PostControlHooksTrait;

	private readonly string $modulePageUrl;

	public function getRequiredRole(): userRole {
		return userRole::LEV_MODERATOR;
	}

	public function getName(): string {
		return 'Board lock-down';
	}

	public function getVersion(): string {
		return 'Koko 2026';
	}

	public function initialize(): void {
		$this->modulePageUrl = $this->getModulePageURL([], false);

		$this->registerLinksAboveBarHook(_T('admin_nav_lockdown_title'), $this->modulePageUrl, _T('admin_nav_lockdown'));
	}

	public function ModulePage() {
		$request = $this->moduleContext->request;
		$flag = makeLockDownFlag($this->moduleContext);

		if ($request->isPost()) {
			if ($request->hasParameter('start', 'POST')) {
				if (!$flag->start(getUsernameFromSession())) {
					throw new BoardException(_T('lockdown_write_failed'), 500);
				}
			} elseif ($request->hasParameter('end', 'POST')) {
				$flag->end();
			}

			redirect($this->modulePageUrl);
		}

		$lockDown = $flag->read();

		$status = $lockDown === null
			? sanitizeStr(_T('lockdown_status_off'))
			: _T('lockdown_status_on',
				$this->moduleContext->postDateFormatter->formatFromTimestamp((int) strtotime($lockDown['started_at'] . ' UTC')),
				sanitizeStr($lockDown['started_by'] ?? _T('lockdown_unknown_staff')));

		$pageHtml = $this->moduleContext->adminPageRenderer->ParseBlock('LOCKDOWN_PAGE', [
			'{$MODULE_URL}' => sanitizeStr($this->modulePageUrl),
			'{$CSRF_TOKEN}' => getCsrfHiddenInput(),
			'{$HEADING}' => sanitizeStr(_T('lockdown_heading')),
			'{$DESCRIPTION}' => sanitizeStr(_T('lockdown_description')),
			'{$STATUS}' => $status,
			'{$BUTTON_NAME}' => $lockDown === null ? 'start' : 'end',
			'{$BUTTON_LABEL}' => sanitizeStr(_T($lockDown === null ? 'lockdown_start' : 'lockdown_end')),
		]);

		echo $this->moduleContext->adminPageRenderer->ParsePage('GLOBAL_ADMIN_PAGE_CONTENT', ['{$PAGE_CONTENT}' => $pageHtml], true);
	}
}
