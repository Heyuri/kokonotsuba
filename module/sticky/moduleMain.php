<?php

// sticky module made for kokonotsuba by deadking

namespace Kokonotsuba\Modules\sticky;

require_once __DIR__ . '/stickyLibrary.php';
require_once __DIR__ . '/stickyRepository.php';

use Kokonotsuba\database\databaseConnection;
use Kokonotsuba\post\Post;
use Kokonotsuba\module_classes\abstractModuleMain;
use Kokonotsuba\module_classes\traits\listeners\OpeningPostListenerTrait;

class moduleMain extends abstractModuleMain {
	use OpeningPostListenerTrait;

	private stickyRepository $stickyRepository;

	public function getName(): string {
		return 'Sticky';
	}

	public function getVersion(): string {
		return 'Koko 2025';
	}

	public function initialize(): void {
		$databaseSettings = \getDatabaseSettings();
		$this->stickyRepository = new stickyRepository(
			databaseConnection::getInstance(),
			$databaseSettings['THREAD_TABLE']
		);

		$this->registerOpeningPostIndicator(
			'sticky',
			getStickyIndicator($this->getConfig('STATIC_URL')),
			fn(Post $p) => $this->stickyRepository->isSticky($p->getThreadUid()),
			30
		);
	}

}
