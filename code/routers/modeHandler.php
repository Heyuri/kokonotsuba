<?php
//Handle GET mode values for koko
class modeHandler {
	public function __construct(
		private routeDiContainer $routeDiContainer	
	) {}

	public function validateBoard(board $board): void {
		if (!file_exists($board->getFullConfigPath())) {
			die("Board's config file <i>" . $board->getFullConfigPath() . "</i> was not found.");
		}

		if (!file_exists($board->getBoardStoragePath())) {
			die("Board's storage directory <i>" . $board->getBoardStoragePath() . "</i> does not exist.");
		}
	}

	public function handle() {
		if ($this->routeDiContainer->config['GZIP_COMPRESS_LEVEL'] && ($Encoding = CheckSupportGZip())) {
			ob_start();
			ob_implicit_flush(0);
		}
	
		$mode = $_GET['mode'] ?? $_POST['mode'] ?? '';
	
		$routes = [
			'regist'	=> function() {
				$route = new registRoute(
					$this->routeDiContainer->board,
					$this->routeDiContainer->config,
					$this->routeDiContainer->postValidator,
					$this->routeDiContainer->staffAccountFromSession,
					$this->routeDiContainer->transactionManager,
					$this->routeDiContainer->moduleEngine,
					$this->routeDiContainer->actionLoggerService,
					$this->routeDiContainer->FileIO,
					$this->routeDiContainer->postRepository,
					$this->routeDiContainer->postService,
					$this->routeDiContainer->threadRepository,
					$this->routeDiContainer->threadService,
					$this->routeDiContainer->quoteLinkService,
					$this->routeDiContainer->softErrorHandler
				);
				$route->registerPostToDatabase();
			},
			'status' => function() {
				$route = new statusRoute(
					$this->routeDiContainer->board,
					$this->routeDiContainer->config,
					$this->routeDiContainer->templateEngine,
					$this->routeDiContainer->moduleEngine,
					$this->routeDiContainer->threadRepository,
					$this->routeDiContainer->postRepository,
					$this->routeDiContainer->FileIO
				);
				$route->drawStatus();
			},
			'admin'	=> function() {
				$route = new adminRoute(
					$this->routeDiContainer->board,
					$this->routeDiContainer->config,
					$this->routeDiContainer->adminLoginController,
					$this->routeDiContainer->adminPageRenderer,
				);
				$route->drawAdminPage();
			},
			'module' => function() {
				$route = new moduleRoute(
					$this->routeDiContainer->moduleEngine,
					$this->routeDiContainer->softErrorHandler
				);
				$route->handleModule();
			},
			'moduleloaded' => function() {
				$route = new moduleloadedRoute(
					$this->routeDiContainer->config, 
					$this->routeDiContainer->board,
					$this->routeDiContainer->moduleEngine,
					$this->routeDiContainer->staffAccountFromSession, 
					$this->routeDiContainer->moduleEngine
				);
				$route->listModules();
			},
			'account' => function() {
				$route = new accountRoute(
					$this->routeDiContainer->config,
					$this->routeDiContainer->staffAccountFromSession,
					$this->routeDiContainer->softErrorHandler,
					$this->routeDiContainer->accountRepository,
					$this->routeDiContainer->adminTemplateEngine,
					$this->routeDiContainer->adminPageRenderer
				);
				$route->drawAccountPage();
			},
			'boards' => function() {
				$route = new boardsRoute(
					$this->routeDiContainer->config, 
					$this->routeDiContainer->staffAccountFromSession, 
					$this->routeDiContainer->softErrorHandler, 
					$this->routeDiContainer->adminTemplateEngine, 
					$this->routeDiContainer->adminPageRenderer, 
					$this->routeDiContainer->boardService,
					$this->routeDiContainer->board
				);
				$route->drawBoardPage();
			},
			'overboard' => function() {
				$route = new overboardRoute(
					$this->routeDiContainer->config, 
					$this->routeDiContainer->visibleBoards,
					$this->routeDiContainer->boardRepository,
					$this->routeDiContainer->board, 
					$this->routeDiContainer->overboard
				);
				$route->drawOverboard();
			},
			'handleAccountAction' => function() {
				$route = new handleAccountActionRoute(
					$this->routeDiContainer->config,
					$this->routeDiContainer->board, 
					$this->routeDiContainer->accountService,
					$this->routeDiContainer->actionLoggerService,
					$this->routeDiContainer->softErrorHandler, 
					$this->routeDiContainer->staffAccountFromSession
				);
				$route->handleAccountRequests();
			},
			'handleBoardRequests' => function() {
				$route = new handleBoardRequestsRoute(
					$this->routeDiContainer->databaseConnection,
					$this->routeDiContainer->config, 
					$this->routeDiContainer->softErrorHandler,
					$this->routeDiContainer->boardService, 
					$this->routeDiContainer->boardPathService,
					$this->routeDiContainer->quoteLinkRepository
				);
				$route->handleBoardRequests();
			},
			'usrdel' => function() {
				$route = new usrdelRoute(
					$this->routeDiContainer->config,
					$this->routeDiContainer->board, 
					$this->routeDiContainer->moduleEngine, 
					$this->routeDiContainer->actionLoggerService, 
					$this->routeDiContainer->postRepository, 
					$this->routeDiContainer->postService,
					$this->routeDiContainer->deletedPostsService, 
					$this->routeDiContainer->softErrorHandler,
					$this->routeDiContainer->regularBoards,
					$this->routeDiContainer->FileIO,
					$this->routeDiContainer->postPolicy
				);
				$route->userPostDeletion();
			},
			'rebuild' => function() {
				$route = new rebuildRoute(
					$this->routeDiContainer->board,
					$this->routeDiContainer->softErrorHandler, 
					$this->routeDiContainer->actionLoggerService 
				);

				$route->handleRebuild();
			},

			'actionLog' => function() {
				$route = new actionLogRoute(
					$this->routeDiContainer->board,
					$this->routeDiContainer->config,
					$this->routeDiContainer->actionLoggerService,
					$this->routeDiContainer->softErrorHandler,
					$this->routeDiContainer->adminPageRenderer,
					$this->routeDiContainer->boardService,
					$this->routeDiContainer->regularBoards
				);
				$route->drawActionLog();
			},
			'managePosts' => function() {
				$route = new managePostsRoute(
					$this->routeDiContainer->board,
					$this->routeDiContainer->config,
					$this->routeDiContainer->moduleEngine,
					$this->routeDiContainer->boardService,
					$this->routeDiContainer->staffAccountFromSession,
					$this->routeDiContainer->postRedirectService,
					$this->routeDiContainer->postRepository,
					$this->routeDiContainer->postService,
					$this->routeDiContainer->softErrorHandler,
					$this->routeDiContainer->FileIO,
					$this->routeDiContainer->actionLoggerService,
					$this->routeDiContainer->adminPageRenderer,
					$this->routeDiContainer->regularBoards,
					$this->routeDiContainer->deletedPostsService
				);
				$route->drawManagePostsPage();
			}
		];
	
		if (isset($routes[$mode])) {
			$routes[$mode]();
		} else {
			$defaultRoute = new defaultRoute(
				$this->routeDiContainer->config, 
				$this->routeDiContainer->board, 
				$this->routeDiContainer->threadRepository,
				$this->routeDiContainer->postRepository,
				$this->routeDiContainer->softErrorHandler,
				$this->routeDiContainer->postRedirectService
			);
			$defaultRoute->handleDefault();
		}
	
		if ($this->routeDiContainer->config['GZIP_COMPRESS_LEVEL'] && $Encoding) {
			$this->finalizeGzip($Encoding);
		}
	}	

	private function finalizeGzip($Encoding) {
		if (!ob_get_length()) exit; // No content, no need to compress
		header('Content-Encoding: ' . $Encoding);
		header('X-Content-Encoding-Level: ' . $this->routeDiContainer->config['GZIP_COMPRESS_LEVEL']);
		header('Vary: Accept-Encoding');
		print gzencode(ob_get_clean(), $this->routeDiContainer->config['GZIP_COMPRESS_LEVEL']); // Compressed content
	}

}
