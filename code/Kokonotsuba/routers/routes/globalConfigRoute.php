<?php

// global config route - edit the configuration overrides that apply to every board

namespace Kokonotsuba\routers\routes;

use Exception;
use Throwable;
use Kokonotsuba\config\configService;
use Kokonotsuba\board\boardService;
use Kokonotsuba\error\softErrorHandler;
use Kokonotsuba\request\request;
use Kokonotsuba\template\pageRenderer;
use Kokonotsuba\template\templateEngine;
use Kokonotsuba\userRole;
use Puchiko\background\BackgroundTaskDispatcher;

use function Kokonotsuba\libraries\_T;
use function Kokonotsuba\libraries\html\drawGlobalConfigForm;
use function Kokonotsuba\libraries\getCsrfHiddenInput;
use function Kokonotsuba\libraries\logError;
use function Kokonotsuba\libraries\requirePostWithCsrf;
use function Puchiko\json\sendJsonResponse;
use function Puchiko\request\redirect;

use const Kokonotsuba\GLOBAL_BOARD_UID;

/**
 * The global config is stored exactly like a board's, under the reserved GLOBAL board's UID, and
 * sits between the schema defaults and each board's own overrides. Editing it here therefore
 * changes every board that hasn't overridden the value itself - see configService.
 */
class globalConfigRoute {
	public function __construct(
		private readonly array $config,
		private readonly softErrorHandler $softErrorHandler,
		private readonly templateEngine $adminTemplateEngine,
		private readonly pageRenderer $adminPageRenderer,
		private readonly boardService $boardService,
		private readonly request $request,
		private readonly configService $configService
	) {}

	public function handleGlobalConfig(): void {
		$this->softErrorHandler->handleAuthError(userRole::LEV_ADMIN);

		// Reset is checked before save: the form posts its saveGlobalConfig field whichever of its
		// two buttons was clicked.
		if (!empty($this->request->getParameter('resetGlobalConfig', 'POST'))) {
			$this->resetGlobalConfigFromRequest();
			return;
		}

		if (!empty($this->request->getParameter('saveGlobalConfig', 'POST'))) {
			$this->saveGlobalConfigFromRequest();
			return;
		}

		$this->drawGlobalConfigPage();
	}

	private function drawGlobalConfigPage(): void {
		$notice = $this->request->getParameter('rebuild', 'GET', '') === 'queued'
			? 'Saved. Every board is being rebuilt in the background.'
			: '';

		$configFormHtml = drawGlobalConfigForm(
			$this->adminTemplateEngine,
			$this->configService,
			$this->config['LIVE_INDEX_FILE'],
			getCsrfHiddenInput(),
			$notice
		);

		echo $this->adminPageRenderer->ParsePage(
			'GLOBAL_ADMIN_PAGE_CONTENT',
			['{$PAGE_CONTENT}' => $configFormHtml],
			true
		);
	}

	private function saveGlobalConfigFromRequest(): void {
		requirePostWithCsrf($this->request);

		$submitted = $this->request->getParameter('config', 'POST', []);
		if (!is_array($submitted)) {
			$submitted = [];
		}

		$isAjax = $this->request->isAjax();

		try {
			// Persist only values that differ from the schema defaults.
			$this->configService->saveOverrides(GLOBAL_BOARD_UID, $submitted);
		} catch (Exception $e) {
			if ($isAjax) {
				sendJsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
			}

			http_response_code(400);
			echo "Error saving global configuration: " . $e->getMessage();
			return;
		}

		$this->queueRebuildOfAllBoards();

		// The editor saves over AJAX so the admin keeps their scroll position; without JS the same
		// POST falls through to the redirect below.
		if ($isAjax) {
			sendJsonResponse([
				'success'    => true,
				'message'    => _T('config_saved'),
				'overridden' => array_keys($this->configService->getOverrides(GLOBAL_BOARD_UID)),
			]);
		}

		redirect($this->config['LIVE_INDEX_FILE'] . '?mode=globalConfig&rebuild=queued');
	}

	private function resetGlobalConfigFromRequest(): void {
		requirePostWithCsrf($this->request);

		try {
			$this->configService->resetOverrides(GLOBAL_BOARD_UID);
		} catch (Exception $e) {
			http_response_code(400);
			echo "Error resetting global configuration: " . $e->getMessage();
			return;
		}

		$this->queueRebuildOfAllBoards();

		redirect($this->config['LIVE_INDEX_FILE'] . '?mode=globalConfig&rebuild=queued');
	}

	/**
	 * A global change can alter any board's rendered output, so every board's static pages need
	 * regenerating. That is far too much work to do inside the request - it is handed to the same
	 * detached 'rebuild_boards' task the rebuild module uses.
	 *
	 * The config is already committed by this point, so a rebuild that fails to dispatch is
	 * logged and left for the admin to run from the Rebuild page; it never fails the save.
	 */
	private function queueRebuildOfAllBoards(): void {
		$boardUids = [];
		foreach ($this->boardService->getAllRegularBoards() ?? [] as $board) {
			$boardUids[] = (int)$board->getBoardUID();
		}

		if (empty($boardUids)) {
			return;
		}

		try {
			BackgroundTaskDispatcher::dispatch('rebuild_boards', ['boardUIDs' => $boardUids]);
		} catch (Throwable $e) {
			logError('[globalConfig] rebuild dispatch failed: ' . $e->getMessage());
		}
	}
}
