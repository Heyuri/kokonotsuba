<?php
// ───────────────────────────────────────
// Dependencies
// ───────────────────────────────────────
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

// ───────────────────────────────────────
// Session & Validation
// ───────────────────────────────────────
$staffAccountFromSession = new staffAccountFromSession;

$IPValidator = new IPValidator($config, new IPAddress);
$postValidator = new postValidator($board, $config, $IPValidator, $threadRepository, $softErrorHandler, $threadService, $postService, $fileService);

// ───────────────────────────────────────
// Policies
// ───────────────────────────────────────
$postPolicy = new postPolicy($config['AuthLevels'], $staffAccountFromSession->getRoleLevel());

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
	$templateEngine
);

// ───────────────────────────────────────
// API
// ───────────────────────────────────────
$boardApi = new boardApi($boardService);
$threadApi = new threadApi($threadService);