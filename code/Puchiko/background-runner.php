<?php

/***************
 * Background Task Runner
 *
 * Internal script — invoked exclusively by BackgroundTaskDispatcher::dispatch().
 * Do not call this script directly.
 *
 * Usage:
 *   php background-runner.php /path/to/bgtask_<id>.json
 *
 * The payload file is a JSON object:
 *   { "class": "Fully\\Qualified\\TaskClass", "args": { ... } }
 *
 * The file is deleted immediately after being read.
 ***************/

if (PHP_SAPI !== 'cli') {
	http_response_code(403);
	exit(1);
}

if ($argc !== 2) {
	fwrite(STDERR, "Usage: background-runner.php <task-payload-file>\n");
	exit(1);
}

$payloadFile = $argv[1];

if (!is_file($payloadFile)) {
	fwrite(STDERR, "Payload file not found: $payloadFile\n");
	exit(1);
}

// ─── Read and immediately delete the payload file ───
$raw = file_get_contents($payloadFile);
unlink($payloadFile);

if ($raw === false) {
	fwrite(STDERR, "Could not read payload file.\n");
	exit(1);
}

// ─── Decode payload ───
try {
	$payload = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
} catch (\JsonException $e) {
	fwrite(STDERR, "Invalid payload JSON: " . $e->getMessage() . "\n");
	exit(1);
}

$class      = $payload['class']      ?? '';
$file       = $payload['file']       ?? null;
$args       = $payload['args']       ?? [];
$statusFile = $payload['statusFile'] ?? null;
$context    = $payload['context']    ?? null;

if (!is_string($class) || $class === '') {
	fwrite(STDERR, "Payload missing 'class'.\n");
	exit(1);
}

// ─── Validate and normalise the status file path ───
if ($statusFile !== null) {
	if (!preg_match('#/bgtask_status_[0-9a-f]{32}\.json$#', $statusFile)) {
		fwrite(STDERR, "Invalid status file path.\n");
		$statusFile = null; // don't write status, but still run the task
	}
}

/** Atomically write a JSON status blob. */
$writeStatus = static function (array $data) use ($statusFile): void {
	if ($statusFile !== null) {
		file_put_contents($statusFile, json_encode($data), LOCK_EX);
	}
};

$writeStatus(['status' => 'running']);

try {
	// ─── Load application context ───
	if ($context !== null) {
		if (!is_file($context)) {
			throw new \RuntimeException("Context file not found: $context");
		}
		require $context;
	}

	// ─── Optionally require the task file (for non-autoloaded classes) ───
	if ($file !== null) {
		// Security: only allow files within the application root
		$appRoot  = realpath(__DIR__ . '/../../');
		$realFile = realpath($file);

		if ($realFile === false || $appRoot === false || strncmp($realFile, $appRoot, strlen($appRoot)) !== 0) {
			throw new \RuntimeException("Task file '$file' is outside the application directory or does not exist.");
		}

		require_once $realFile;
	}

	// ─── Validate task class ───
	if (!class_exists($class)) {
		throw new \RuntimeException("Task class not found: $class");
	}

	if (!is_a($class, \Puchiko\background\BackgroundTaskInterface::class, true)) {
		throw new \RuntimeException("Class $class does not implement BackgroundTaskInterface.");
	}

	// ─── Execute task ───
	/** @var \Puchiko\background\BackgroundTaskInterface $task */
	$task = new $class();
	$task->handle((array) $args);
	$writeStatus(['status' => 'completed']);
} catch (\Throwable $e) {
	$errMsg = sprintf(
		"Task %s failed: [%s] %s in %s:%d\n",
		$class,
		get_class($e),
		$e->getMessage(),
		$e->getFile(),
		$e->getLine()
	);
	fwrite(STDERR, $errMsg);
	$writeStatus(['status' => 'failed', 'error' => $e->getMessage()]);
	exit(1);
}

exit(0);
