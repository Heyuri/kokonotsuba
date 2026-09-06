<?php

namespace Kokonotsuba\Modules\anonIp;

require_once __DIR__ . '/anonIpTask.php';
require_once __DIR__ . '/anonIpLib.php';

use Kokonotsuba\action_log\actionType;
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

use const Kokonotsuba\GLOBAL_BOARD_UID;

class moduleAdmin extends abstractModuleAdmin {
	use PostControlHooksTrait;
	use IncludeScriptTrait;
	use BackgroundTaskTrait;

	private readonly string $modulePageUrl;

	/**
	 * The interval, written to the global scope: the anonymizer walks every board's rows and keeps
	 * a single run ledger, so how often it runs is not a per-board question.
	 */
	private const SCHEDULE_EVERY_KEY = 'modules.anonIp.AUTO_ANONYMIZE_DAYS';

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
		$action = $this->moduleContext->request->getParameter('anonIpAction', 'POST', '');

		if ($action === 'schedule') {
			$this->handleScheduleUpdate();
			return;
		}

		$this->handleAnonymizeRequest($action);
	}

	/**
	 * Save the automatic run interval.
	 *
	 * setOverride() coerces the value to the field's type, clamps it to the schema minimum, and
	 * drops the override entirely when it matches what the scope inherits - so setting the
	 * interval back to the default leaves no row behind.
	 */
	private function handleScheduleUpdate(): void {
		$everyDays = (int) $this->moduleContext->request->getParameter('scheduleEveryDays', 'POST', 0);

		$this->moduleContext->configService->setOverride(GLOBAL_BOARD_UID, self::SCHEDULE_EVERY_KEY, $everyDays);

		$this->moduleContext->actionLoggerService->logAction(
			$everyDays > 0
				? "Set IP anonymization to run every $everyDays day(s)"
				: 'Turned scheduled IP anonymization off',
			GLOBAL_BOARD_UID,
			actionType::TOOL_ANON_IP
		);

		redirect($this->getModulePageURL(['scheduled' => '1'], false, true));
	}

	/** Queue a run over the chosen time frame. */
	private function handleAnonymizeRequest(string $action): void {
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

		// Recorded before the dispatch so the task is handed a row to close out. A dispatch that
		// fails leaves the row open, which reads as an attempted run and holds the schedule off
		// for one interval - the same as a job that started and died.
		$runId = getAnonIpRunRepository($this->moduleContext)
			->recordManualRun(self::timeframeInDays($timeframe));

		$this->dispatchBackgroundJob(
			'anonymize_ips',
			['timeframe' => $timeframe, 'runId' => $runId],
			sanitizeStr(_T('anon_ip_dispatched')),
			sanitizeStr(_T('anon_ip_dispatch_failed')),
			$this->getModulePageURL(['dispatched' => '1'], false, true),
			$this->modulePageUrl,
			'[anonIp]',
			function () use ($timeframe): void {
				$this->moduleContext->actionLoggerService->logAction(
					"Queued IP anonymization (timeframe: $timeframe)",
					GLOBAL_BOARD_UID,
					actionType::TOOL_ANON_IP
				);
			}
		);
	}

	/**
	 * The dropdown's time frames as a number of days, for the ledger's scope column.
	 * 'now' has no window at all, which the ledger stores as NULL.
	 */
	private static function timeframeInDays(string $timeframe): ?int {
		return match ($timeframe) {
			'1year'   => 365,
			'1month'  => 30,
			'1week'   => 7,
			'24hours' => 1,
			default   => null,
		};
	}

	/**
	 * The "last run" line, or the note that it has never run.
	 * An unfinished row is a run still going, or one whose job died.
	 */
	private function renderLastRun(): string {
		$run = getAnonIpRunRepository($this->moduleContext)->getLastRun();

		if ($run === null) {
			return sanitizeStr(_T('anon_ip_never_run'));
		}

		$when = $this->moduleContext->postDateFormatter->formatFromDateString($run['dispatched_at']);
		$source = $run['trigger_source'] === anonIpRunRepository::TRIGGER_SCHEDULED
			? _T('anon_ip_source_scheduled')
			: _T('anon_ip_source_manual');

		// $when is already markup from the date formatter, so only the other pieces are escaped
		$source = sanitizeStr($source);

		if ($run['finished_at'] === null) {
			return _T('anon_ip_last_run_unfinished', $when, $source);
		}

		return _T('anon_ip_last_run', $when, $source, (int) $run['rows_changed']);
	}

	/** The kinds of record a run touches, one list item each. */
	private function renderWarningTargets(): string {
		$keys = ['posts', 'reports', 'pms', 'soudane', 'appeals', 'banners', 'logins', 'actionlog', 'bans'];

		$items = array_map(
			fn(string $key): string => '<li>' . sanitizeStr(_T('anon_ip_target_' . $key)) . '</li>',
			$keys
		);

		return implode("\n", $items);
	}

	/** What the configured interval does, or that there is no schedule. */
	private function renderScheduleStatus(): string {
		$everyDays = (int) $this->getModuleConfig('AUTO_ANONYMIZE_DAYS', 0);

		return $everyDays > 0
			? _T('anon_ip_schedule_on', $everyDays)
			: _T('anon_ip_schedule_off');
	}

	/** The global interval, which is what the form edits and writes back. */
	private function globalScheduleInterval(): int {
		$values = $this->moduleContext->configService->getEffectiveValues(GLOBAL_BOARD_UID);

		return (int) ($values[self::SCHEDULE_EVERY_KEY] ?? 0);
	}

	/**
	 * Warn when this board overrides the interval, since the form writes the global scope and
	 * would otherwise look like it had done nothing.
	 */
	private function renderScheduleOverrideNote(): string {
		$boardEvery = (int) $this->getModuleConfig('AUTO_ANONYMIZE_DAYS', 0);

		return $boardEvery === $this->globalScheduleInterval()
			? ''
			: sanitizeStr(_T('anon_ip_schedule_board_override'));
	}

	public function ModulePage(): void {
		// No cron: the interval is checked here as well as on new posts, so a quiet board still
		// anonymizes whenever staff look in on it.
		getAnonIpScheduler(
			$this->moduleContext,
			fn(string $key, int $default): int => (int) $this->getModuleConfig($key, $default)
		)->tick();

		$this->handleBackgroundPoll(function (string $status) {
			return match ($status) {
				'completed' => sanitizeStr(_T('anon_ip_completed')),
				'failed'    => sanitizeStr(_T('anon_ip_dispatch_failed')),
				default     => '',
			};
		});

		$request        = $this->moduleContext->request;
		$successMessage = '';

		if ($request->getParameter('dispatched', 'GET', null) === '1') {
			$successMessage = sanitizeStr(_T('anon_ip_dispatched'));
		} elseif ($request->getParameter('scheduled', 'GET', null) === '1') {
			$successMessage = sanitizeStr(_T('anon_ip_schedule_saved'));
		}

		$scheduleEvery = $this->globalScheduleInterval();

		$templateValues = [
			'{$TITLE}'           => _T('anon_ip_title'),
			'{$WARNING_MESSAGE}' => _T('anon_ip_warning'),
			'{$WARNING_TARGETS}' => $this->renderWarningTargets(),
			'{$WARNING_UNDO}'    => _T('anon_ip_warning_undo'),
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
			'{$LAST_RUN}'        => $this->renderLastRun(),
			'{$SCHEDULE_STATUS}' => sanitizeStr($this->renderScheduleStatus()),
			'{$SCHEDULE_NOTE}'   => $this->renderScheduleOverrideNote(),
			'{$SCHEDULE_HEADING}'      => sanitizeStr(_T('anon_ip_schedule_heading')),
			'{$SCHEDULE_EVERY_LABEL}'  => sanitizeStr(_T('anon_ip_schedule_every_label')),
			'{$SCHEDULE_EVERY_DESC}'   => sanitizeStr(_T('anon_ip_schedule_every_desc')),
			'{$SCHEDULE_SUBMIT}'       => sanitizeStr(_T('anon_ip_schedule_submit')),
			'{$SCHEDULE_EVERY}'        => $scheduleEvery,
		];

		$pageHtml = $this->moduleContext->adminPageRenderer->ParseBlock('ANON_IP_PAGE', $templateValues);
		echo $this->moduleContext->adminPageRenderer->ParsePage(
			'GLOBAL_ADMIN_PAGE_CONTENT',
			['{$PAGE_CONTENT}' => $pageHtml],
			true
		);
	}
}
