<?php



// ───────────────────────────────────────
// Dependencies
// ───────────────────────────────────────

use Kokonotsuba\api\boardApi;
use Kokonotsuba\api\threadApi;
use Kokonotsuba\error\softErrorHandler;
use Kokonotsuba\ip\IPAddress;
use Kokonotsuba\ip\IPValidator;
use Kokonotsuba\log_in\adminLoginController;
use Kokonotsuba\log_in\authenticationHandler;
use Kokonotsuba\log_in\loginSessionHandler;
use Kokonotsuba\overboard;
use Kokonotsuba\post\postValidator;
use Kokonotsuba\post\helper\postDateFormatter;
use Kokonotsuba\template\pageRenderer;
use Kokonotsuba\template\templateEngine;

$templateEngine = $board->getBoardTemplateEngine();
$moduleEngine = $board->getModuleEngine();

$adminTemplateEngine = new templateEngine(getBackendDir() . 'templates/admin.tpl', [
	'config'	=> $config,
	'boardData'	=> [
		'title'		=> $board->getBoardTitle(),
		'subtitle'	=> $board->getBoardSubTitle()
	]
]);

$adminPageRenderer = new pageRenderer($adminTemplateEngine, $moduleEngine, $board);

// ───────────────────────────────────────
// Error Handling & Authentication
// ───────────────────────────────────────
$softErrorHandler = new softErrorHandler($board->getBoardHead('Error!'), $board->getBoardFooter(), $board->getConfigValue('STATIC_INDEX_FILE'), $templateEngine);
$loginSessionHandler = new loginSessionHandler($config['STAFF_LOGIN_TIMEOUT']);
$authenticationHandler = new authenticationHandler();

$adminLoginController = new adminLoginController(
	$actionLoggerService,
	$accountRepository,
	$loginSessionHandler,
	$authenticationHandler,
	$softErrorHandler
);

$IPValidator = new IPValidator($config, new IPAddress);
$postValidator = new postValidator($config, $IPValidator, $threadRepository, $threadService, $fileService);

$postDateFormatter = new postDateFormatter($config['TIME_ZONE']);

// ───────────────────────────────────────
// Overboard
// ───────────────────────────────────────
$overboard = new overboard(
	$board, 
	$config, 
	$softErrorHandler, 
	$threadRepository, 
	$boardService, 
	$postRepository, 
	$postService, 
	$quoteLinkService, 
    $threadService,
	$postSearchService,
	$actionLoggerService,
	$postRedirectService,
	$deletedPostsService,
	$fileService,
	$capcodeService,
	$userCapcodes,
	$transactionManager,
	$moduleEngine, 
	$templateEngine,
	$postRenderingPolicy,
);

// ───────────────────────────────────────
// API
// ───────────────────────────────────────
$boardApi = new boardApi($boardService);
$threadApi = new threadApi($threadService);