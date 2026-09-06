<?php

namespace Kokonotsuba\Modules\anonIp;

require_once __DIR__ . '/anonIpTarget.php';

use Kokonotsuba\database\transactionManager;
use Kokonotsuba\database\TransactionalTrait;

/**
 * Runs anonymization across every registered anonIpTarget.
 *
 * Row counts come from the updates themselves rather than a preceding COUNT, so the figure
 * reported is what actually changed and each table is scanned once instead of twice.
 */
class anonIpService {
	use TransactionalTrait;

	/** Rows changed per target key on the last run. @var array<string, int> */
	private array $lastBreakdown = [];

	/** @param anonIpTarget[] $targets */
	public function __construct(
		private anonIpRepository $anonIpRepository,
		private transactionManager $transactionManager,
		private array $targets = [],
	) {}

	/**
	 * Anonymize every target's rows older than the given time frame.
	 *
	 * Supported values: '1year' | '1month' | '1week' | '24hours'
	 *
	 * @return int Rows anonymized across every target, or -1 if the time frame was unrecognized.
	 */
	public function anonymizeByTimeframe(string $timeframe): int {
		$cutoff = $this->resolveCutoff($timeframe);

		if ($cutoff === null) {
			return -1;
		}

		return $this->run($cutoff->format('Y-m-d H:i:s'));
	}

	/**
	 * Anonymize every target's rows older than the given number of days.
	 *
	 * This is what the schedule uses: a retention window in days says the same thing as a time
	 * frame but without a fixed list to pick from, so the interval and the window can be set
	 * independently.
	 *
	 * @param int $days Days a record keeps its address. 0 or less means every record.
	 * @return int Rows anonymized across every target.
	 */
	public function anonymizeOlderThanDays(int $days): int {
		if ($days <= 0) {
			return $this->anonymizeAll();
		}

		return $this->run((new \DateTimeImmutable("-{$days} days"))->format('Y-m-d H:i:s'));
	}

	/**
	 * Anonymize every target in full, regardless of row age.
	 *
	 * @return int Rows anonymized across every target.
	 */
	public function anonymizeAll(): int {
		return $this->run(null);
	}

	/**
	 * Rows changed per target key on the last run, for logging and reporting.
	 *
	 * @return array<string, int>
	 */
	public function getLastBreakdown(): array {
		return $this->lastBreakdown;
	}

	/**
	 * @param string|null $cutoff MySQL datetime (Y-m-d H:i:s), or null to take every row.
	 * @return int Rows anonymized across every target.
	 */
	private function run(?string $cutoff): int {
		$breakdown = [];

		$this->inTransaction(function () use ($cutoff, &$breakdown): void {
			foreach ($this->targets as $target) {
				// A cutoff run can only reach tables whose rows can be dated.
				if ($cutoff !== null && !$target->canBeAged()) {
					continue;
				}

				$changed = $this->anonIpRepository->anonymize($target, $cutoff);

				if ($changed > 0) {
					$breakdown[$target->key] = ($breakdown[$target->key] ?? 0) + $changed;
				}
			}
		});

		$this->lastBreakdown = $breakdown;

		return array_sum($breakdown);
	}

	/**
	 * Resolve a time-frame string to a cutoff, or null when it is unrecognized.
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
