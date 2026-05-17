<?php

namespace Kokonotsuba\module_classes\traits;

use Puchiko\background\BackgroundTaskDispatcher;

use function Kokonotsuba\libraries\logError;
use function Puchiko\json\sendJsonResponse;
use function Puchiko\request\redirect;
use function Puchiko\strings\sanitizeStr;

/**
 * Helpers for module admins that dispatch background tasks and poll their status.
 *
 * Requires the using class to have $this->moduleContext->request available
 * (i.e. it must extend abstractModuleAdmin or abstractModule).
 */
trait BackgroundTaskTrait {
	/**
	 * If the current request is an AJAX poll (?pollJob=<id>), build and send
	 * the status JSON response, then exit. Does nothing otherwise.
	 *
	 * @param callable(string $status, array $data): string $messageResolver
	 *   Receives the status string and full status array; returns the human-readable
	 *   message to include in the response.
	 */
	protected function handleBackgroundPoll(callable $messageResolver): void {
		if (!$this->moduleContext->request->isAjax()) {
			return;
		}

		$jobId = $this->moduleContext->request->getParameter('pollJob', 'GET', '');
		if ($jobId === '') {
			return;
		}

		$data    = BackgroundTaskDispatcher::pollStatus($jobId);
		$status  = $data['status'];
		$message = $messageResolver($status, $data);

		$response = ['status' => $status, 'message' => $message];

		if ($status === 'failed' && isset($data['error'])) {
			$response['error'] = sanitizeStr($data['error']);
		}

		if (in_array($status, ['pending', 'running', 'failed'], true)) {
			$log = BackgroundTaskDispatcher::getJobLog($jobId);
			if ($log !== null) {
				$response['log'] = sanitizeStr($log);
			}
			$response['taskDir'] = sys_get_temp_dir();
		}

		sendJsonResponse($response);
	}

	/**
	 * Dispatch a background task, then respond appropriately.
	 *
	 * On success:  AJAX → JSON {dispatched:true, jobId, message}; otherwise → redirect to $successUrl.
	 * On failure:  logs the error; AJAX → JSON {dispatched:false, message} 500; otherwise → redirect to $failUrl.
	 *
	 * This method always terminates the current request (via sendJsonResponse or redirect).
	 *
	 * @param string $taskName       Registered task name.
	 * @param array  $args           Arguments forwarded to the task's handle().
	 * @param string $successMessage Message included in the AJAX success response.
	 * @param string $failMessage    Message included in the AJAX failure response.
	 * @param string $successUrl     Redirect target on non-AJAX success.
	 * @param string $failUrl        Redirect target on non-AJAX failure.
	 * @param string $logPrefix      Prefix used in the error log entry, e.g. '[rebuild]'.
	 */
	protected function dispatchBackgroundJob(
		string $taskName,
		array  $args,
		string $successMessage,
		string $failMessage,
		string $successUrl,
		string $failUrl,
		string $logPrefix = '[background]'
	): void {
		$isAjax = $this->moduleContext->request->isAjax();

		try {
			$jobId = BackgroundTaskDispatcher::dispatch($taskName, $args);

			if ($isAjax) {
				sendJsonResponse(['dispatched' => true, 'jobId' => $jobId, 'message' => $successMessage]);
			}

			redirect($successUrl);
		} catch (\Throwable $e) {
			logError($logPrefix . ' dispatch failed: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

			if ($isAjax) {
				sendJsonResponse(['dispatched' => false, 'message' => $failMessage], 500);
			}

			redirect($failUrl);
		}
	}
}
