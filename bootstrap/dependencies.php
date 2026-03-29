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

$adminPageRenderer = new pageRenderer($adminTemplateEngine, $moduleEngine, $board, $request);

// ───────────────────────────────────────
// Error Handling & Authentication
// ───────────────────────────────────────
$softErrorHandler = new softErrorHandler($board->getBoardHead('Error!'), $board->getBoardFooter(), $board->getConfigValue('STATIC_INDEX_FILE'), $templateEngine, $staffAccountFromSession, $request);
$loginSessionHandler = new loginSessionHandler($request, $config['STAFF_LOGIN_TIMEOUT']);
$authenticationHandler = new authenticationHandler();

$adminLoginController = new adminLoginController(
	$actionLoggerService,
	$accountRepository,
	$loginSessionHandler,
	$authenticationHandler,
	$softErrorHandler
);

$IPValidator = new IPValidator($config, new IPAddress($request->getRemoteAddr()), $request);
$postValidator = new postValidator($config, $IPValidator, $threadRepository, $threadService, $fileService, $request);

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
	$quoteLinkService, 
    $threadService,
	$moduleEngine,
	$templateEngine,
	$postRenderingPolicy,
	$container,
	$request,
);

// ───────────────────────────────────────
// API
// ───────────────────────────────────────
$boardApi = new boardApi($boardService, $request);
$threadApi = new threadApi($threadService, $request);

// ───────────────────────────────────────
// Register in container
// ───────────────────────────────────────
$container->set('board', $board);
$container->set('config', $config);
$container->set('templateEngine', $templateEngine);
$container->set('moduleEngine', $moduleEngine);
$container->set('adminTemplateEngine', $adminTemplateEngine);
$container->set('adminPageRenderer', $adminPageRenderer);
$container->set('softErrorHandler', $softErrorHandler);
$container->set('loginSessionHandler', $loginSessionHandler);
$container->set('adminLoginController', $adminLoginController);
$container->set('postValidator', $postValidator);
$container->set('postDateFormatter', $postDateFormatter);
$container->set('overboard', $overboard);
$container->set('boardApi', $boardApi);
$container->set('threadApi', $threadApi);
$container->set('boardPathService', $boardPathService);