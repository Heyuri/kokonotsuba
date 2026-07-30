<?php

// sticky module made for kokonotsuba by deadking

namespace Kokonotsuba\Modules\sticky;

require_once __DIR__ . '/stickyLibrary.php';
require_once __DIR__ . '/stickyRepository.php';

use Kokonotsuba\database\databaseConnection;
use Kokonotsuba\post\Post;
use Kokonotsuba\thread\Thread;
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
			$this->isStickyCheck(...),
			30
		);
	}

	/**
	 * Whether the sticky indicator should show on this opening post.
	 *
	 * is_sticky lives on the thread row, so when the renderer hands us the Thread - which it does
	 * for every board index and thread page, the one place this indicator is drawn in bulk - we
	 * read it straight off that row. The board rebuild renders one opening post per thread, so this
	 * turned a query per thread into none. The repository lookup remains only as the fallback for
	 * render paths that carry no Thread (e.g. search results), where a stray query is harmless.
	 */
	private function isStickyCheck(Post $post, ?Thread $thread): bool {
		if ($thread !== null) {
			return $thread->isSticky();
		}

		return $this->stickyRepository->isSticky($post->getThreadUid());
	}

}
