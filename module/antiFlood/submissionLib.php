<?php

namespace Kokonotsuba\Modules\antiFlood;

use Kokonotsuba\database\databaseConnection;
use Kokonotsuba\Modules\antiFlood\submissionRepository;
use Kokonotsuba\Modules\antiFlood\submissionService;

function getSubmissionService(): submissionService {
	// Get database settings
	$databaseSettings = getDatabaseSettings();

	// Get database connection
	$databaseConnection = databaseConnection::getInstance();

	// Initialize repository
	$submissionRepository = new submissionRepository(
		$databaseConnection,
		$databaseSettings['LAST_THREAD_SUBMISSIONS_TABLE']
	);

	// Initialize and return service
	return new submissionService($submissionRepository);
}
