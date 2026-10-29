<?php

namespace Kokonotsuba\kernel\providers;

use Kokonotsuba\api\boardApi;
use Kokonotsuba\api\threadApi;
use Kokonotsuba\containers\appContainer;
use Kokonotsuba\error\softErrorHandler;
use Kokonotsuba\log_in\adminLoginController;
use Kokonotsuba\overboard;
use Kokonotsuba\post\helper\postDateFormatter;
use Kokonotsuba\post\postValidator;
use Kokonotsuba\template\pageRenderer;
use Kokonotsuba\template\templateEngine;

/** The board this request was made to (its boardUID.ini), and everything drawn from it. */
final class boardProvider {
	public static function register(appContainer $container): void {
		// The backend's own directory holds a .backend marker; a request run from it has no board.
		if (file_exists('.backend')) {
			die("You are trying to access the instance's backend");
		}

		$request = $container->get('request');

		$board = $container->get('boardService')->getBoardFromBootstrapFile('./boardUID.ini');

		if (!file_exists($board->getBoardStoragePath())) {
			die("Invalid board setup");
		}

		$config = $board->loadBoardConfig();

		$templateEngine = $board->getBoardTemplateEngine();
		$moduleEngine = $board->getModuleEngine();

		// Deletion is announced through the module engine, which only exists once the board does.
		$container->get('postDeletionService')->setModuleEngine($moduleEngine);

		$adminTemplateEngine = new templateEngine(getBackendDir() . 'templates/admin', [
			'config'	=> $config,
			'boardData'	=> [
				'title'		=> $board->getBoardTitle(),
				'subtitle'	=> $board->getBoardSubTitle()
			]
		]);

		$adminPageRenderer = new pageRenderer($adminTemplateEngine, $moduleEngine, $board, $request);

		$softErrorHandler = new softErrorHandler($board->getBoardHead('Error!'), $board->getBoardFooter(), $board->getConfigValue('STATIC_INDEX_FILE'), $templateEngine, $container->get('staffAccountFromSession'), $request);

		$adminLoginController = new adminLoginController(
			$container->get('actionLoggerService'),
			$container->get('accountRepository'),
			$container->get('loginSessionHandler'),
			$container->get('authenticationHandler'),
			$softErrorHandler,
			$container->get('loginAttemptService'),
			$request
		);

		$threadRepository = $container->get('threadRepository');
		$threadService = $container->get('threadService');

		$postValidator = new postValidator($config, $container->get('IPValidator'), $threadRepository, $threadService, $container->get('fileService'), $request);

		// TIME_ZONE is a per-board setting, so dates stay on the board's clock.
		$postDateFormatter = new postDateFormatter($config['TIME_ZONE']);

		$overboard = new overboard(
			$board,
			$config,
			$softErrorHandler,
			$threadRepository,
			$container->get('quoteLinkService'),
			$threadService,
			$moduleEngine,
			$templateEngine,
			$container->get('postRenderingPolicy'),
			$container,
			$request,
		);

		$boardApi = new boardApi($container->get('boardService'), $request);
		$threadApi = new threadApi($threadService, $request);

		$container->set('board', $board);
		$container->set('config', $config);
		$container->set('templateEngine', $templateEngine);
		$container->set('moduleEngine', $moduleEngine);
		$container->set('adminTemplateEngine', $adminTemplateEngine);
		$container->set('adminPageRenderer', $adminPageRenderer);
		$container->set('softErrorHandler', $softErrorHandler);
		$container->set('adminLoginController', $adminLoginController);
		$container->set('postValidator', $postValidator);
		$container->set('postDateFormatter', $postDateFormatter);
		$container->set('overboard', $overboard);
		$container->set('boardApi', $boardApi);
		$container->set('threadApi', $threadApi);
	}
}
