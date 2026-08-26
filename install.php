<?php

/**
 * Kokonotsuba installer.
 *
 * Runs from the backend directory itself: clone the repository somewhere web-accessible, point a
 * browser at this file, and it checks the environment, writes the config, migrates the schema, and
 * creates the first board and admin account. Delete it afterwards.
 */

use Kokonotsuba\install\checkReport;
use Kokonotsuba\install\exposureProbe;
use Kokonotsuba\install\installDefaults;
use Kokonotsuba\install\installer;
use Kokonotsuba\install\installerPage;
use Kokonotsuba\install\installInput;
use Kokonotsuba\install\pathRequirements;
use Kokonotsuba\install\systemRequirements;

// Details belong on the page, not in the response of a half-rendered one.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

if (!is_file(__DIR__.'/koko.php')) {
	http_response_code(500);
	exit('install.php has been moved out of the Kokonotsuba directory. Run it from where koko.php is.');
}

require __DIR__.'/autoload.php';
require __DIR__.'/code/Kokonotsuba/constants.php';
require __DIR__.'/paths.php';
require __DIR__.'/code/Puchiko/includes.php';

$appRoot = __DIR__;
$selfUrl = (string)($_SERVER['SCRIPT_NAME'] ?? 'install.php');
$page = new installerPage($selfUrl);

if (installer::isInstalled($appRoot)) {
	$page->alreadyInstalled($appRoot);
	exit;
}

$defaults = installDefaults::detect($_SERVER, $appRoot);

/** Every preflight check, in display order. */
function buildReport(installDefaults $defaults, string $appRoot): checkReport {
	$report = new checkReport();
	$report->addAll((new systemRequirements())->check());
	$report->addAll(pathRequirements::forAppRoot($appRoot)->check());
	$report->addAll((new exposureProbe($defaults->baseUrl()))->check());

	return $report;
}

/** @return array<string, string> */
function formDefaults(installDefaults $defaults): array {
	return [
		// Matches the grant the README tells you to create ('koko_user'@'localhost').
		'db_host' => 'localhost',
		'db_port' => '3306',
		'db_name' => 'kokonotsuba',
		'db_user' => 'koko_user',
		'admin_username' => '',
		'board_identifier' => 'b',
		'board_title' => '',
		'board_sub_title' => '',
		'website_url' => $defaults->websiteUrl(),
		'home_url' => $defaults->homeUrl(),
		'static_url' => $defaults->staticUrl(),
		'static_path' => $defaults->staticPath(),
	];
}

$isInstallRequest = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
	&& ($_POST['action'] ?? '') === 'install';

try {
	$report = buildReport($defaults, $appRoot);

	if (!$isInstallRequest) {
		$page->header('Serving from '.$defaults->baseUrl());
		$page->report($report);
		$page->webServerHelp($defaults->urlPrefix);
		$page->form(formDefaults($defaults), [], $report->hasFailures());
		$page->footer();
		exit;
	}

	$input = installInput::fromArray($_POST);

	// The environment can change between drawing the form and submitting it, so it is checked
	// again here rather than trusted from the page the user is looking at.
	if ($report->hasFailures() || !$input->isValid()) {
		http_response_code(422);
		$page->header('Nothing has been changed yet');
		$page->report($report);
		$page->form(array_merge(formDefaults($defaults), $input->redrawValues()), $input->errors(), $report->hasFailures());
		$page->footer();
		exit;
	}

	$installer = new installer($appRoot, getTableNames(), Kokonotsuba\KOKO_VERSION, $defaults);
	$result = $installer->run($input);

	if (!$result->succeeded()) {
		http_response_code(500);
	}

	$page->header();
	$page->result($result);

	if (!$result->succeeded()) {
		$page->form(array_merge(formDefaults($defaults), $input->redrawValues()), [], false);
	}

	$page->footer();
} catch (Throwable $e) {
	error_log('Installer: '.$e);

	http_response_code(500);
	$page->header();
	echo '<div class="panel fail"><h2>The installer itself failed</h2><p>',
		htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
		'</p><p>The full trace is in the PHP error log.</p></div>';
	$page->footer();
}
