<?php

namespace Kokonotsuba\Modules\anonIp;

use Kokonotsuba\database\transactionManager;
use Kokonotsuba\database\TransactionalTrait;

/**
 * Service that orchestrates IP anonymization for posts older than a given time frame.
 */
class anonIpService {
	use TransactionalTrait;

	public function __construct(
		private anonIpRepository $anonIpRepository,
		private transactionManager $transactionManager,
	) {}

	/**
	 * Anonymize IP addresses in both the posts table and the action log table
	 * for entries older than the specified time frame.
	 *
	 * Supported $timeframe values:
	 *   '1year' | '1month' | '1week' | '24hours'
	 *
	 * @param string $timeframe  One of the recognized time-frame strings.
	 * @return int               Total number of rows anonymized across both tables,
	 *                           or -1 if the time-frame string was unrecognized.
	 */
	public function anonymizeByTimeframe(string $timeframe): int {
		$cutoff = $this->resolveCutoff($timeframe);

		if ($cutoff === null) {
			return -1;
		}

		$cutoffSql = $cutoff->format('Y-m-d H:i:s');

		$postCount      = $this->anonIpRepository->countToAnonymize($cutoffSql);
		$actionLogCount = $this->anonIpRepository->countActionLogToAnonymize($cutoffSql);
		$soudaneCount   = $this->anonIpRepository->countSoudaneToAnonymize($cutoffSql);

		$this->inTransaction(function () use ($cutoffSql, $postCount, $actionLogCount, $soudaneCount) {
			if ($postCount > 0) {
				$this->anonIpRepository->anonymizeBefore($cutoffSql);
			}
			if ($actionLogCount > 0) {
				$this->anonIpRepository->anonymizeActionLogBefore($cutoffSql);
			}
			if ($soudaneCount > 0) {
				$this->anonIpRepository->anonymizeSoudaneBefore($cutoffSql);
			}
		});

		return $postCount + $actionLogCount + $soudaneCount;
	}

	/**
	 * Hash every IP address that has not yet been anonymized, regardless of post age.
	 *
	 * @return int  Total number of rows anonymized across both tables.
	 */
	public function anonymizeAll(): int {
		$postCount      = $this->anonIpRepository->countAllToAnonymize();
		$actionLogCount = $this->anonIpRepository->countAllActionLogToAnonymize();
		$soudaneCount   = $this->anonIpRepository->countAllSoudaneToAnonymize();

		$this->inTransaction(function () use ($postCount, $actionLogCount, $soudaneCount) {
			if ($postCount > 0) {
				$this->anonIpRepository->anonymizeAll();
			}
			if ($actionLogCount > 0) {
				$this->anonIpRepository->anonymizeAllActionLog();
			}
			if ($soudaneCount > 0) {
				$this->anonIpRepository->anonymizeAllSoudane();
			}
		});

		return $postCount + $actionLogCount + $soudaneCount;
	}

	/**
	 * Resolve a human-readable time-frame string to a DateTimeImmutable cutoff.
	 *
	 * @param string $timeframe
	 * @return \DateTimeImmutable|null  null when the string is unrecognized.
	 */
	private function resolveCutoff(string $timeframe): ?\DateTimeImmutable {
		return match ($timeframe) {
			'1year'   => new \DateTimeImmutable('-1 year'),
			'1month'  => new \DateTimeImmutable('-1 month'),
			'1week'   => new \DateTimeImmutable('-1 week'),
			'24hours' => new \DateTimeImmutable('-24 hours'),
			default   => null,
		};
	}
}
