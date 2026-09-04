<?php

namespace Kokonotsuba\Modules\anonIp;

require_once __DIR__ . '/anonIpRunRepository.php';

use Puchiko\background\BackgroundTaskDispatcher;

/**
 * Decides whether the scheduled anonymization run is due, and dispatches it.
 *
 * There is no cron: tick() is called from the places that are already happening anyway - a post
 * being registered, and the anonymizer's own admin page being opened - the same way expired
 * mutes are pruned. A board with no traffic and no staff visiting it does not anonymize, which
 * is the trade for having no scheduler daemon.
 *
 * tick() is on the request path, so it does nothing at all until the interval is configured. The
 * work itself is handed to a background task, so the request that triggered it is not held up.
 */
class anonIpScheduler {

	public function __construct(
		private readonly anonIpRunRepository $anonIpRunRepository,
		/** How often to run, in days. 0 disables the schedule entirely. */
		private readonly int $everyDays,
		/** Where a failed dispatch is reported. Injected rather than reached for so the
		 *  decision can be tested without the application's error handler. */
		private readonly mixed $logger = null,
	) {}

	/**
	 * Dispatch the run if the interval has elapsed since the last one.
	 *
	 * Never throws: a failure to dispatch is logged and the request carries on, since nothing
	 * the visitor asked for depends on it.
	 *
	 * @return bool Whether a run was dispatched.
	 */
	public function tick(): bool {
		if ($this->everyDays <= 0) {
			return false;
		}

		try {
			$notBefore = (new \DateTimeImmutable("-{$this->everyDays} days"))->format('Y-m-d H:i:s');

			// A scheduled run covers every record: narrowing by age is a choice made per run from
			// the page's dropdown, not something a standing schedule should decide. Rows already
			// anonymized are skipped by the repository's own WHERE, so a re-run costs only a scan.
			$runId = $this->anonIpRunRepository->claimScheduledRun($notBefore, null);

			// Another request claimed it, or the last run is still inside the interval.
			if ($runId === null) {
				return false;
			}

			try {
				BackgroundTaskDispatcher::dispatch('anonymize_ips', [
					'olderThanDays' => null,
					'runId' => $runId,
				]);
			} catch (\Throwable $e) {
				// The claim outlives a job that never started, so give it back.
				$this->anonIpRunRepository->discardRun($runId);
				throw $e;
			}

			return true;
		} catch (\Throwable $e) {
			if (is_callable($this->logger)) {
				($this->logger)('[anonIp] scheduled dispatch failed: ' . $e->getMessage()
					. ' in ' . $e->getFile() . ':' . $e->getLine());
			}

			return false;
		}
	}
}
