<?php

namespace Kokonotsuba\Modules\anonIp;

require_once __DIR__ . '/anonIpRepository.php';
require_once __DIR__ . '/anonIpService.php';

use Kokonotsuba\database\databaseConnection;
use Kokonotsuba\module_classes\abstractModuleAdmin;
use Kokonotsuba\module_classes\traits\listeners\PostControlHooksTrait;
use Kokonotsuba\userRole;

use function Kokonotsuba\libraries\_T;
use function Kokonotsuba\libraries\getCsrfHiddenInput;
use function Puchiko\request\redirect;
use function Puchiko\strings\sanitizeStr;

class moduleAdmin extends abstractModuleAdmin {
	use PostControlHooksTrait;

	private anonIpService $anonIpService;
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
		$dbSettings       = getDatabaseSettings();
		$anonIpRepository = new anonIpRepository(
			databaseConnection::getInstance(),
			$dbSettings['POST_TABLE'],
			$dbSettings['ACTIONLOG_TABLE']
		);
		$this->anonIpService = new anonIpService($anonIpRepository, $this->moduleContext->transactionManager);

		$this->modulePageUrl = $this->getModulePageURL([], false, true);

		$this->registerLinksAboveBarHook(
			_T('admin_nav_anon_ip_title'),
			$this->modulePageUrl,
			_T('admin_nav_anon_ip')
		);
	}

	/**
	 * Handle the anonymize POST action.
	 * CSRF token + POST method are enforced automatically by
	 * abstractModuleAdmin::dispatchModuleRequest() before this fires.
	 */
	protected function handleModuleRequest(): void {
		$action    = $this->moduleContext->request->getParameter('anonIpAction', 'POST', '');
		$timeframe = $this->moduleContext->request->getParameter('timeframe', 'POST', '');

		if ($action === 'anonymize') {
			$count = $this->anonIpService->anonymizeByTimeframe($timeframe);

			if ($count >= 0) {
				redirect($this->getModulePageURL(['anonymized' => $count], false, true));
				return;
			}
		}

		redirect($this->modulePageUrl);
	}

	public function ModulePage(): void {
		$anonymized     = $this->moduleContext->request->getParameter('anonymized', 'GET', null);
		$successMessage = '';

		if ($anonymized !== null && ctype_digit((string)$anonymized)) {
			$successMessage = sanitizeStr(_T('anon_ip_success', (int)$anonymized));
		}

		$templateValues = [
			'{$TITLE}'           => _T('anon_ip_title'),
			'{$WARNING_MESSAGE}' => _T('anon_ip_warning'),
			'{$SELECT_LABEL}'    => _T('anon_ip_select_label'),
			'{$OPT_1_YEAR}'      => _T('anon_ip_1_year'),
			'{$OPT_1_MONTH}'     => _T('anon_ip_1_month'),
			'{$OPT_1_WEEK}'      => _T('anon_ip_1_week'),
			'{$OPT_24_HOURS}'    => _T('anon_ip_24_hours'),
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
