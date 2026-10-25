<?php
//Handle GET mode values for koko

namespace Kokonotsuba\routers;

use Kokonotsuba\containers\appContainer;
use Kokonotsuba\board\board;
use Kokonotsuba\error\BoardException;
use Kokonotsuba\profiler\excimerProfiler;
use Kokonotsuba\thread\bumpLimitSweeper;

use function Kokonotsuba\libraries\CheckSupportGZip;
use function Kokonotsuba\libraries\getRoleLevelFromSession;
use function Kokonotsuba\libraries\requirePostWithCsrf;
use function Kokonotsuba\libraries\_T;

class modeHandler {
	public function __construct(
		private appContainer $container	
	) {}

	/**
	 * Determine the profiler category for the current request, or null if not profiled.
	 */
	private function getProfilerCategory(string $mode, routeEntry $entry): ?string {
		if ($entry->profile !== null) {
			return $entry->profile;
		}
		if ($mode === 'module') {
			$load = $this->container->get('request')->getParameter('load', default: '');
			if ($load === 'adminDel') {
				return 'deleting';
			}
			if ($load === 'deletedPosts') {
				return 'deletedPosts';
			}
		}
		return null;
	}

	public function validateBoard(board $board): void {
		if (!file_exists($board->getBoardStoragePath())) {
			die("Board's storage directory <i>" . $board->getBoardStoragePath() . "</i> does not exist.");
		}
	}

	public function handle() {
		if ($this->container->get('config')['GZIP_COMPRESS_LEVEL'] && ($Encoding = CheckSupportGZip($this->container->get('request')))) {
			ob_start();
			ob_implicit_flush(0);
		}
	
		$request = $this->container->get('request');
		$mode = $request->getParameter('mode', 'GET')
			?? $request->getParameter('mode', 'POST', '');

		$globalConfig = $this->container->get('globalConfig');

		securityHeaders::send(
			getRoleLevelFromSession()->isStaff(),
			(string) ($globalConfig['STATIC_URL'] ?? ''),
			(string) ($globalConfig['WEBSITE_URL'] ?? ''),
			!empty($globalConfig['USE_CDN']) ? (string) ($globalConfig['CDN_URL'] ?? '') : null
		);

		$entry = (is_string($mode) ? routeTable::routes()[$mode] ?? null : null)
			?? throw new BoardException(_T('mode_not_found'), 404);

		if ($entry->csrf === csrfRule::POST_ONLY || ($entry->csrf === csrfRule::ON_POST && $request->isPost())) {
			requirePostWithCsrf($request);
		}

		// Start Excimer profiler if enabled and this route is profiled
		$profiler = null;
		if (!empty($globalConfig['EXCIMER_PROFILING'])) {
			$category = $this->getProfilerCategory($mode, $entry);
			if ($category !== null) {
				$outputPath = getBackendGlobalDir() . 'excimer';
				$profiler = new excimerProfiler($outputPath, $category);
				$profiler->start();
			}
		}
	
		// before any route draws, so this request already sees threads that just hit the bump limit
		(new bumpLimitSweeper(
			$this->container->get('board'),
			$this->container->get('threadRepository')
		))->tick();

		($entry->factory)($this->container)->handle();

		// Stop Excimer profiler and write output
		if ($profiler !== null) {
			$profiler->stop();
		}
	
		if ($this->container->get('config')['GZIP_COMPRESS_LEVEL'] && $Encoding) {
			$this->finalizeGzip($Encoding);
		}
	}	

	private function finalizeGzip($Encoding) {
		if (!ob_get_length()) exit; // No content, no need to compress
		header('Content-Encoding: ' . $Encoding);
		header('X-Content-Encoding-Level: ' . $this->container->get('config')['GZIP_COMPRESS_LEVEL']);
		header('Vary: Accept-Encoding');
		print gzencode(ob_get_clean(), $this->container->get('config')['GZIP_COMPRESS_LEVEL']); // Compressed content
	}

}
