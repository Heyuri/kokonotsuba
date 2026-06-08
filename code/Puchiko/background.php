<?php

namespace Puchiko\background;

/**
 * Implement this on any class you want to run as a background task.
 *
 * The constructor must be callable with no arguments; wire all dependencies
 * manually inside handle() using the same pattern as moduleAdmin::initialize().
 */
interface BackgroundTaskInterface {
	/**
	 * Execute the task.
	 *
	 * @param array<string, mixed> $args JSON-serializable arguments passed at dispatch time.
	 */
	public function handle(array $args): void;
}

/**
 * Maps human-readable names to the class that implements them.
 *
 * Register tasks early in the bootstrap (or in a module's initialize()) before
 * any dispatch call.
 *
 * For classes that live outside the autoloader's search path (e.g. module files
 * under module/), pass the absolute path to their file via $file so the runner
 * can require it before instantiating.
 *
 * Example (autoloaded class):
 *   BackgroundTaskRegistry::register('my_task', MyTask::class);
 *
 * Example (non-autoloaded module class):
 *   BackgroundTaskRegistry::register('anonymize_ips', AnonIpTask::class, __DIR__ . '/anonIpTask.php');
 */
class BackgroundTaskRegistry {
	/** @var array<string, array{class: string, file: string|null}> */
	private static array $tasks = [];

	/**
	 * Register a task class under a unique name.
	 *
	 * @param string      $name   Unique task identifier.
	 * @param string      $class  FQN implementing BackgroundTaskInterface.
	 * @param string|null $file   Absolute path to the file to require before
	 *                            instantiating the class (for non-autoloaded classes).
	 *
	 * @throws \InvalidArgumentException If the class is autoloaded but does not
	 *                                   implement BackgroundTaskInterface, or if
	 *                                   $file is provided but does not exist.
	 */
	public static function register(string $name, string $class, ?string $file = null): void {
		if ($file !== null) {
			if (!is_file($file)) {
				throw new \InvalidArgumentException(
					"Task file '$file' does not exist."
				);
			}
		} elseif (!is_a($class, BackgroundTaskInterface::class, true)) {
			throw new \InvalidArgumentException(
				"$class must implement " . BackgroundTaskInterface::class . '.'
			);
		}

		self::$tasks[$name] = ['class' => $class, 'file' => $file];
	}

	/**
	 * Resolve a registered name to its class + optional file entry.
	 *
	 * @return array{class: string, file: string|null}
	 * @throws \InvalidArgumentException If the name has not been registered.
	 */
	public static function resolve(string $name): array {
		if (!array_key_exists($name, self::$tasks)) {
			throw new \InvalidArgumentException(
				"No background task registered under '$name'."
			);
		}

		return self::$tasks[$name];
	}

	/** @return array<string, array{class: string, file: string|null}> */
	public static function all(): array {
		return self::$tasks;
	}
}

/**
 * Serializes a registered task to an ephemeral JSON file and spawns a
 * detached background PHP process to execute it via background-runner.php.
 *
 * Must be configured once before first use (e.g. in a bootstrap file):
 *
 *   BackgroundTaskDispatcher::configure(
 *       ROOT_DIR . 'code/Puchiko/background-runner.php',
 *       sys_get_temp_dir()
 *   );
 */
class BackgroundTaskDispatcher {
	private static ?string $contextFile = null;
	private static ?string $appRoot = null;

	/**
	 * Provide an application bootstrap file that the runner loads before executing
	 * any task. Use this to set up the autoloader, database connection, and other
	 * application dependencies that background tasks rely on.
	 *
	 * @param string|null $file Absolute path to the context file, or null to clear.
	 */
	public static function setContext(?string $file): void {
		self::$contextFile = $file;
	}

	/**
	 * Set the application instance root directory. This is used by the runner to
	 * resolve paths correctly when code directories are symlinked. Should be set
	 * to the real, non-symlinked instance root (e.g. dirname(__FILE__) in the
	 * web entry point).
	 *
	 * @param string|null $dir Absolute path to the instance root (with trailing slash), or null to clear.
	 */
	public static function setAppRoot(?string $dir): void {
		self::$appRoot = $dir !== null ? rtrim($dir, '/') . '/' : null;
	}

	/**
	 * Dispatch a registered task to run in a detached background process.
	 *
	 * Returns a job ID that can be passed to pollStatus() to check progress.
	 * The task transitions through: pending → running → completed | failed.
	 *
	 * @param string               $taskName  Name registered with BackgroundTaskRegistry.
	 * @param array<string, mixed> $args      JSON-serializable arguments for the task.
	 * @return string                         Opaque job ID (32 hex chars).
	 *
	 * @throws \RuntimeException         If the payload file cannot be written.
	 * @throws \InvalidArgumentException If the task name is not registered.
	 * @throws \JsonException            If $args cannot be JSON-encoded.
	 */
	public static function dispatch(string $taskName, array $args = []): string {
		$taskDir    = sys_get_temp_dir();
		$jobId      = bin2hex(random_bytes(16));
		$class      = BackgroundTaskRegistry::resolve($taskName);
		$statusFile = $taskDir . '/bgtask_status_' . $jobId . '.json';

		$payload = json_encode([
			'class'      => $class['class'],
			'file'       => $class['file'],
			'args'       => $args,
			'jobId'      => $jobId,
			'statusFile' => $statusFile,
			'context'    => self::$contextFile,
			'appRoot'    => self::$appRoot,
		], JSON_THROW_ON_ERROR);

		// Write status first so it exists before the process starts
		self::writeStatus($statusFile, ['status' => 'pending']);

		$payloadFile = $taskDir . '/bgtask_' . $jobId . '.json';

		if (file_put_contents($payloadFile, $payload, LOCK_EX) === false) {
			@unlink($statusFile);
			throw new \RuntimeException(
				"Could not write background task payload to '$payloadFile'."
			);
		}

		if (!function_exists('exec')) {
			@unlink($statusFile);
			@unlink($payloadFile);
			throw new \RuntimeException(
				'exec() is disabled on this server. Background tasks require exec() to be enabled.'
			);
		}

		$phpBin  = escapeshellarg(self::resolvePhpBinary());
		$runner  = escapeshellarg(__DIR__ . '/background-runner.php');
		$pfile   = escapeshellarg($payloadFile);
		$logFile = $taskDir . '/bgtask_' . $jobId . '.log';
		$log     = escapeshellarg($logFile);

		$envPrefix = self::$appRoot !== null
			? 'KOKONOTSUBA_APPROOT=' . escapeshellarg(self::$appRoot) . ' '
			: '';

		$output = [];
		$rc = 1;
		exec("{$envPrefix}$phpBin $runner $pfile > /dev/null 2>$log & echo $!", $output, $rc);
		$pid = isset($output[0]) ? (int) $output[0] : 0;

		if ($rc !== 0 || $pid <= 0) {
			self::writeStatus($statusFile, [
				'status' => 'failed',
				'error'  => 'Failed to start background process. Check PHP CLI binary and exec permissions.',
			]);
			@unlink($payloadFile);
			throw new \RuntimeException('Failed to start background process.');
		}

		// Persist PID so the job can be cancelled later
		self::writeStatus($statusFile, ['status' => 'pending', 'pid' => $pid]);

		return $jobId;
	}

	/**
	 * Poll the status of a dispatched job.
	 *
	 * Possible returned statuses: 'pending', 'running', 'completed', 'failed', 'not_found'.
	 * Status files for terminal states (completed/failed) are deleted after being read.
	 *
	 * @param string $jobId  Value returned by dispatch().
	 * @return array{status: string, error?: string}
	 */
	public static function pollStatus(string $jobId): array {
		if (!ctype_xdigit($jobId) || strlen($jobId) !== 32) {
			return ['status' => 'not_found'];
		}

		$taskDir    = sys_get_temp_dir();
		$statusFile = $taskDir . '/bgtask_status_' . $jobId . '.json';

		if (!is_file($statusFile)) {
			return ['status' => 'not_found'];
		}

		$raw = file_get_contents($statusFile);
		if ($raw === false) {
			return ['status' => 'not_found'];
		}

		try {
			$status = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
		} catch (\JsonException) {
			return ['status' => 'not_found'];
		}

		$currentStatus = $status['status'] ?? '';
		if (in_array($currentStatus, ['pending', 'running'], true)) {
			$pid = isset($status['pid']) ? (int) $status['pid'] : 0;
			if ($pid > 0 && !self::isProcessAlive($pid)) {
				$status = [
					'status' => 'failed',
					'error'  => 'Background worker exited before reporting completion.',
					'pid'    => $pid,
				];
				self::writeStatus($statusFile, $status);
			}
		}

		$logFile = $taskDir . '/bgtask_' . $jobId . '.log';
		if (($status['status'] ?? '') === 'failed' && is_file($logFile)) {
			$log = file_get_contents($logFile);
			if ($log !== false && $log !== '') {
				$status['log'] = $log;
			}
		}

		// Clean up terminal states so the tmp dir doesn't fill up
		if (in_array($status['status'] ?? '', ['completed', 'failed'], true)) {
			@unlink($statusFile);
			if (($status['status'] ?? '') === 'completed') {
				@unlink($logFile);
			}
		}

		return $status;
	}

	/**
	 * Read the stderr log for a job, if it exists.
	 * Returns null if there is no log or it is empty.
	 */
	public static function getJobLog(string $jobId): ?string {
		if (!ctype_xdigit($jobId) || strlen($jobId) !== 32) {
			return null;
		}
		$logFile = sys_get_temp_dir() . '/bgtask_' . $jobId . '.log';
		if (!is_file($logFile)) {
			return null;
		}
		$contents = file_get_contents($logFile);
		return ($contents !== false && $contents !== '') ? $contents : null;
	}

	/**
	 * Attempt to terminate a running background job by sending SIGTERM to its PID.
	 *
	 * Returns true if the signal was sent, false if the job is not found, already
	 * in a terminal state, or has no recorded PID.
	 *
	 * @param string $jobId Value returned by dispatch().
	 */
	public static function killJob(string $jobId): bool {
		if (!ctype_xdigit($jobId) || strlen($jobId) !== 32) {
			return false;
		}

		$taskDir    = sys_get_temp_dir();
		$statusFile = $taskDir . '/bgtask_status_' . $jobId . '.json';

		if (!is_file($statusFile)) {
			return false;
		}

		$raw = @file_get_contents($statusFile);
		if ($raw === false) {
			return false;
		}

		try {
			$status = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
		} catch (\JsonException) {
			return false;
		}

		if (in_array($status['status'] ?? '', ['completed', 'failed'], true)) {
			return false;
		}

		$pid = isset($status['pid']) ? (int) $status['pid'] : 0;
		if ($pid <= 0) {
			return false;
		}

		if (function_exists('posix_kill')) {
			$sent = posix_kill($pid, SIGTERM);
		} else {
			exec('kill ' . $pid . ' 2>/dev/null', result_code: $rc);
			$sent = ($rc === 0);
		}

		if ($sent) {
			self::writeStatus($statusFile, ['status' => 'failed', 'error' => 'Killed by request.', 'pid' => $pid]);
			@unlink($taskDir . '/bgtask_' . $jobId . '.log');
		}

		return $sent;
	}

	/**
	 * Locate an executable PHP binary.
	 *
	 * PHP_BINARY is empty when running under mod_php or certain FPM configurations,
	 * which causes the shell to receive an empty command and fail with
	 * "sh: : Permission denied". Fall back through common alternatives.
	 *
	 * @throws \RuntimeException If no executable PHP binary can be found.
	 */
	private static function resolvePhpBinary(): string {
		$candidates = [];

		$configuredCli = getenv('PHP_CLI_BINARY');
		if (is_string($configuredCli) && $configuredCli !== '') {
			$candidates[] = $configuredCli;
		}

		if (PHP_BINARY !== '') {
			$candidates[] = PHP_BINARY;
		}

		$candidates[] = PHP_BINDIR . '/php';

		// Ask the shell for common CLI binary names.
		if (function_exists('exec')) {
			$found = [];
			exec('command -v php 2>/dev/null', $found);
			if (!empty($found[0])) {
				$candidates[] = $found[0];
			}

			$found = [];
			exec('command -v php-cli 2>/dev/null', $found);
			if (!empty($found[0])) {
				$candidates[] = $found[0];
			}
		}

		$candidates = array_values(array_unique($candidates));

		foreach ($candidates as $candidate) {
			if (self::isCliPhpBinary($candidate)) {
				return $candidate;
			}
		}

		throw new \RuntimeException(
			'Could not locate an executable CLI PHP binary. ' .
			'Set PHP_CLI_BINARY or ensure php CLI is on the PATH.'
		);
	}

	/**
	 * Determine whether a candidate points to a usable CLI PHP binary.
	 */
	private static function isCliPhpBinary(string $binary): bool {
		if ($binary === '' || !is_executable($binary)) {
			return false;
		}

		$base = strtolower(basename($binary));
		if (strpos($base, 'php-fpm') !== false || strpos($base, 'php-cgi') !== false) {
			return false;
		}

		if (!function_exists('exec')) {
			return true;
		}

		$probe = [];
		$rc    = 1;
		exec(escapeshellarg($binary) . " -r 'echo PHP_SAPI;' 2>/dev/null", $probe, $rc);

		return $rc === 0 && isset($probe[0]) && trim($probe[0]) === 'cli';
	}

	/**
	 * Determine whether a PID currently exists.
	 */
	private static function isProcessAlive(int $pid): bool {
		if ($pid <= 0) {
			return false;
		}

		if (function_exists('posix_kill')) {
			return @posix_kill($pid, 0);
		}

		exec('kill -0 ' . $pid . ' 2>/dev/null', result_code: $rc);
		return $rc === 0;
	}

	/** @param array<string, mixed> $data */
	private static function writeStatus(string $statusFile, array $data): void {
		file_put_contents($statusFile, json_encode($data), LOCK_EX);
	}
}