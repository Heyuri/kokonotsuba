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
$postValidator = new postValidator($board, $config, $IPValidator, $threadRepository, $softErrorHandler, $threadService, $postService, $attachmentService, $FileIO);

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
	$attachmentService,
	$actionLoggerService,
	$postRedirectService,
	$deletedPostsService,
	$transactionManager,
	$moduleEngine, 
	$templateEngine
);
