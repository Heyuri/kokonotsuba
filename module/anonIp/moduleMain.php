<?php

namespace Kokonotsuba\Modules\anonIp;

require_once __DIR__ . '/anonIpLib.php';

use Kokonotsuba\module_classes\abstractModuleMain;
use Kokonotsuba\module_classes\traits\listeners\RegistPostInsertedListenerTrait;

use function Kokonotsuba\Modules\anonIp\getAnonIpScheduler;

/**
 * Front-end half of the anonymizer: it renders nothing and adds no hook to the page.
 *
 * Its only job is to give the schedule a heartbeat. There is no cron, so the interval is checked
 * when a post is registered - the same traffic-driven pattern expired mutes are pruned on. The
 * check costs nothing until an interval is configured, and one query when it is.
 */
class moduleMain extends abstractModuleMain {
	use RegistPostInsertedListenerTrait;

	public function getName(): string {
		return 'IP Anonymizer';
	}

	public function getVersion(): string {
		return 'Koko 2026';
	}

	public function initialize(): void {
		if ((int) $this->getModuleConfig('AUTO_ANONYMIZE_DAYS', 0) <= 0) {
			return;
		}

		$this->listenRegistPostInserted('onPostInserted');
	}

	/** Runs after the post is in, so a slow dispatch cannot hold up the poster. */
	public function onPostInserted(int $postUid, string $ip): void {
		getAnonIpScheduler(
			$this->moduleContext,
			fn(string $key, int $default): int => (int) $this->getModuleConfig($key, $default)
		)->tick();
	}
}
