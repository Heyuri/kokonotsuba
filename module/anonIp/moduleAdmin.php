<?php

namespace Kokonotsuba\Modules\anonIp;

require_once __DIR__ . '/anonIpTask.php';

use Kokonotsuba\module_classes\abstractModuleAdmin;
use Kokonotsuba\module_classes\traits\BackgroundTaskTrait;
use Kokonotsuba\module_classes\traits\listeners\IncludeScriptTrait;
use Kokonotsuba\module_classes\traits\listeners\PostControlHooksTrait;
use Kokonotsuba\userRole;
use Puchiko\background\BackgroundTaskRegistry;

use function Kokonotsuba\libraries\_T;
use function Kokonotsuba\libraries\getCsrfHiddenInput;
use function Puchiko\json\sendJsonResponse;
use function Puchiko\request\redirect;
use function Puchiko\strings\sanitizeStr;

class moduleAdmin extends abstractModuleAdmin {
	use PostControlHooksTrait;
	use IncludeScriptTrait;
	use BackgroundTaskTrait;

	private readonly string $modulePageUrl;

	public function getRequiredRole(): userRole {
		return $this->getConfig('AuthLevels.CAN_ANONYMIZE_IPS', userRole::LEV_ADMIN);
	}

	public function getName(): string {
		return 'IP Anonymizer';
	}

	public function getVersion(): string {
		return 'Koko 2026';
	}

	public function initialize(): void {
		$this->modulePageUrl = $this->getModulePageURL([], false, true);

		BackgroundTaskRegistry::register('anonymize_ips', anonIpTask::class, __DIR__ . '/anonIpTask.php');

		$this->registerLinksAboveBarHook(
			_T('admin_nav_anon_ip_title'),
			$this->modulePageUrl,
			_T('admin_nav_anon_ip')
		);
		$this->registerScript('anonIp.js');
	}

	/**
	 * Handle the anonymize POST action.
	 * CSRF token + POST method are enforced automatically by
	 * abstractModuleAdmin::dispatchModuleRequest() before this fires.
	 */
	protected function handleModuleRequest(): void {
		$action    = $this->moduleContext->request->getParameter('anonIpAction', 'POST', '');
		$timeframe = $this->moduleContext->request->getParameter('timeframe', 'POST', '');
		$isAjax    = $this->moduleContext->request->isAjax();

		$validTimeframes = ['1year', '1month', '1week', '24hours', 'now'];

		if ($action !== 'anonymize' || !in_array($timeframe, $validTimeframes, true)) {
			if ($isAjax) {
				sendJsonResponse(['dispatched' => false, 'message' => sanitizeStr(_T('anon_ip_invalid_request'))], 400);
			}
			redirect($this->modulePageUrl);
			return;
		}

		$this->dispatchBackgroundJob(
			'anonymize_ips',
			['timeframe' => $timeframe],
			sanitizeStr(_T('anon_ip_dispatched')),
			sanitizeStr(_T('anon_ip_dispatch_failed')),
			$this->getModulePageURL(['dispatched' => '1'], false, true),
			$this->modulePageUrl,
			'[anonIp]'
		);
	}

	public function ModulePage(): void {
		$this->handleBackgroundPoll(function (string $status) {
			return match ($status) {
				'completed' => sanitizeStr(_T('anon_ip_completed')),
				'failed'    => sanitizeStr(_T('anon_ip_dispatch_failed')),
				default     => '',
			};
		});

		$dispatched     = $this->moduleContext->request->getParameter('dispatched', 'GET', null);
		$successMessage = '';

		if ($dispatched === '1') {
			$successMessage = sanitizeStr(_T('anon_ip_dispatched'));
		}

		$templateValues = [
			'{$TITLE}'           => _T('anon_ip_title'),
			'{$WARNING_MESSAGE}' => _T('anon_ip_warning'),
			'{$SELECT_LABEL}'    => _T('anon_ip_select_label'),
			'{$OPT_1_YEAR}'      => _T('anon_ip_1_year'),
			'{$OPT_1_MONTH}'     => _T('anon_ip_1_month'),
			'{$OPT_1_WEEK}'      => _T('anon_ip_1_week'),
			'{$OPT_24_HOURS}'    => _T('anon_ip_24_hours'),
			'{$OPT_NOW}'         => _T('anon_ip_now'),
			'{$SUBMIT_BTN}'      => _T('anon_ip_submit'),
			'{$MODULE_URL}'      => sanitizeStr($this->modulePageUrl),
			'{$CSRF_TOKEN}'      => getCsrfHiddenInput(),
			'{$SUCCESS_MESSAGE}' => $successMessage,
		];

		$pageHtml = $this->moduleContext->adminPageRenderer->ParseBlock('ANON_IP_PAGE', $templateValues);
		echo $this->moduleContext->adminPageRenderer->ParsePage(
			'GLOBAL_ADMIN_PAGE_CONTENT',
			['{$PAGE_CONTENT}' => $pageHtml],
			true
		);
	}
}
