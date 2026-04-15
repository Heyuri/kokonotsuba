<?php

namespace Kokonotsuba\profiler;

/**
 * Wraps the Excimer PHP extension to produce speedscope-compatible JSON profiles.
 * Output files are saved to global/excimer/{category}/ with timestamp filenames.
 */
class excimerProfiler {
	private ?\ExcimerProfiler $profiler = null;
	private string $outputDir;
	private string $category;

	/**
	 * @param string $outputBasePath Base directory for profile output (e.g. global/excimer/)
	 * @param string $category       Subdirectory name (e.g. 'posting', 'rebuild', 'deleting')
	 */
	public function __construct(string $outputBasePath, string $category) {
		$this->category = $category;
		$this->outputDir = rtrim($outputBasePath, '/\\') . DIRECTORY_SEPARATOR . $category;
	}

	public function start(): void {
		if (!extension_loaded('excimer')) {
			return;
		}

		if (!is_dir($this->outputDir)) {
			mkdir($this->outputDir, 0750, true);
		}

		$this->profiler = new \ExcimerProfiler();
		$this->profiler->setPeriod(0.001); // 1ms sampling interval
		$this->profiler->setEventType(EXCIMER_REAL);
		$this->profiler->start();

		// Register shutdown function so the profile is saved even if exit/die is called
		register_shutdown_function([$this, 'stop']);
	}

	public function stop(): void {
		if ($this->profiler === null) {
			return;
		}

		$this->profiler->stop();
		$log = $this->profiler->getLog();

		$speedscope = $log->getSpeedscopeData();
		$speedscope['name'] = $this->category . ' profile';

		$filename = date('Y-m-d_His') . '_' . bin2hex(random_bytes(4)) . '.speedscope.json';
		$filepath = $this->outputDir . DIRECTORY_SEPARATOR . $filename;

		file_put_contents($filepath, json_encode($speedscope, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

		// Prune old profiles: keep only the most recent 50 per category
		$this->pruneOldProfiles(50);

		$this->profiler = null;
	}

	private function pruneOldProfiles(int $keep): void {
		$files = glob($this->outputDir . '/' . '*.speedscope.json');
		if ($files === false || count($files) <= $keep) {
			return;
		}

		// Sort by modification time ascending (oldest first)
		usort($files, fn($a, $b) => filemtime($a) <=> filemtime($b));

		$toDelete = array_slice($files, 0, count($files) - $keep);
		foreach ($toDelete as $file) {
			unlink($file);
		}
	}
}
