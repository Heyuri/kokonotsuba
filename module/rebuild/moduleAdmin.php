<?php

namespace Kokonotsuba\Modules\rebuild;

require_once __DIR__ . '/rebuildTask.php';

use Kokonotsuba\module_classes\abstractModuleAdmin;
use Kokonotsuba\module_classes\traits\BackgroundTaskTrait;
use Kokonotsuba\module_classes\traits\listeners\IncludeScriptTrait;
use Kokonotsuba\module_classes\traits\listeners\PostControlHooksTrait;
use Kokonotsuba\userRole;
use Puchiko\background\BackgroundTaskRegistry;

use function Kokonotsuba\libraries\_T;
use function Kokonotsuba\libraries\getCsrfHiddenInput;
use function Kokonotsuba\libraries\html\generateRebuildListCheckboxHTML;
use function Puchiko\json\sendJsonResponse;
use function Puchiko\request\redirect;
use function Puchiko\strings\sanitizeStr;

use const Kokonotsuba\GLOBAL_BOARD_UID;

class moduleAdmin extends abstractModuleAdmin {
	use PostControlHooksTrait;
	use IncludeScriptTrait;
	use BackgroundTaskTrait;

	private readonly string $modulePageUrl;

    public function getRequiredRole(): userRole {
        return $this->getConfig('AuthLevels.CAN_MANAGE_REBUILD');
    }

	public function getName(): string {
		return 'Rebuild tools';
	}

	public function getVersion(): string {
		return 'Koko 2025';
	}

	public function initialize(): void {
		$this->modulePageUrl = $this->getModulePageURL([], false);

		BackgroundTaskRegistry::register('rebuild_boards', rebuildTask::class, __DIR__ . '/rebuildTask.php');

		$this->registerLinksAboveBarHook(_T('admin_nav_rebuild_multiple_title'), $this->modulePageUrl, _T('admin_nav_rebuild_multiple'));
		$this->registerScript('rebuild.js');
	}

	/**
	 * Handle the rebuild POST action.
	 * CSRF token + POST method are enforced automatically by
	 * abstractModuleAdmin::dispatchModuleRequest() before this fires.
	 */
	protected function handleModuleRequest(): void {
		$boardUIDsToRebuild = $this->moduleContext->request->getParameter('rebuildBoardUIDs', 'POST', []);
		$isAjax             = $this->moduleContext->request->isAjax();

		if (empty($boardUIDsToRebuild) || !is_array($boardUIDsToRebuild)) {
			if ($isAjax) {
				sendJsonResponse(['dispatched' => false, 'message' => 'No boards selected.'], 400);
			}
			redirect($this->modulePageUrl);
			return;
		}

		$boardUIDs = array_map('intval', $boardUIDsToRebuild);

		$this->dispatchBackgroundJob(
			'rebuild_boards',
			['boardUIDs' => $boardUIDs],
			'Rebuild started.',
			'Failed to start rebuild.',
			$this->getModulePageURL(['dispatched' => '1'], false),
			$this->modulePageUrl,
			'[rebuild]',
			function () use ($boardUIDs): void {
				$count = count($boardUIDs);
				$this->moduleContext->actionLoggerService->logAction(
					"Queued rebuild for $count board(s) (UIDs: " . implode(', ', $boardUIDs) . ')',
					GLOBAL_BOARD_UID
				);
			}
		);
	}

	public function ModulePage() {
		$this->handleBackgroundPoll(fn(string $status, array $data) => match ($status) {
			'completed' => 'Boards rebuilt successfully.',
			'failed'    => $data['error'] ?? 'Rebuild failed.',
			default     => '',
		});

		$dispatched     = $this->moduleContext->request->getParameter('dispatched', 'GET', null);
		$successMessage = $dispatched === '1' ? 'Rebuild job started.' : '';

		$templateValues = [
			'{$REBUILD_CHECK_LIST}' => generateRebuildListCheckboxHTML(GLOBAL_BOARD_ARRAY),
			'{$MODULE_URL}'         => sanitizeStr($this->modulePageUrl),
			'{$CSRF_TOKEN}'         => getCsrfHiddenInput(),
			'{$SUCCESS_MESSAGE}'    => sanitizeStr($successMessage),
		];

		$adminRebuildPage = $this->moduleContext->adminPageRenderer->ParseBlock('ADMIN_REBUILD_PAGE', $templateValues);
		echo $this->moduleContext->adminPageRenderer->ParsePage('GLOBAL_ADMIN_PAGE_CONTENT', ['{$PAGE_CONTENT}' => $adminRebuildPage], true);
	}
}
