<?php
//Handle GET mode values for koko
class modeHandler {
	private readonly board $board;
	private readonly array $config;
	private readonly globalHTML $globalHTML;
	private readonly overboard $overboard;
	private readonly pageRenderer $adminPageRenderer;
	private readonly mixed $FileIO;
	private readonly mixed $PIO;
	private readonly mixed $threadSingleton;
	private readonly boardIO $boardIO;
	private readonly AccountIO $AccountIO;
	private readonly ActionLogger $actionLogger;
	private readonly softErrorHandler $softErrorHandler;
	private readonly adminLoginController $adminLoginController;
	private readonly staffAccountFromSession $staffSession;
	private readonly postValidator $postValidator;

	private moduleEngine $moduleEngine;
	private templateEngine $templateEngine;
	private templateEngine $adminTemplateEngine;

	public function __construct(
		board $board,
		globalHTML $globalHTML,
		moduleEngine $moduleEngine,
		templateEngine $templateEngine,
		templateEngine $adminTemplateEngine,
		overboard $overboard,
		pageRenderer $adminPageRenderer,
		softErrorHandler $softErrorHandler,
		boardIO $boardIO,
		mixed $FileIO,
		mixed $PIO,
		mixed $threadSingleton,
		AccountIO $AccountIO,
		ActionLogger $actionLogger,
		adminLoginController $adminLoginController,
		staffAccountFromSession $staffSession,
		postValidator $postValidator
	) {
		$this->validateBoard($board);

		$this->board = $board;
		$this->config = $board->loadBoardConfig();

		$this->globalHTML = $globalHTML;
		$this->moduleEngine = $moduleEngine;
		$this->templateEngine = $templateEngine;
		$this->adminTemplateEngine = $adminTemplateEngine;
		$this->overboard = $overboard;
		$this->adminPageRenderer = $adminPageRenderer;
		$this->softErrorHandler = $softErrorHandler;
		$this->boardIO = $boardIO;
		$this->FileIO = $FileIO;
		$this->PIO = $PIO;
		$this->threadSingleton = $threadSingleton;
		$this->AccountIO = $AccountIO;
		$this->actionLogger = $actionLogger;
		$this->adminLoginController = $adminLoginController;
		$this->staffSession = $staffSession;
		$this->postValidator = $postValidator;
	}

	private function validateBoard(board $board): void {
		if (!file_exists($board->getFullConfigPath())) {
			die("Board's config file <i>" . $board->getFullConfigPath() . "</i> was not found.");
		}

		if (!file_exists($board->getBoardStoragePath())) {
			die("Board's storage directory <i>" . $board->getBoardStoragePath() . "</i> does not exist.");
		}
	}


	public function handle() {
		if ($this->config['GZIP_COMPRESS_LEVEL'] && ($Encoding = CheckSupportGZip())) {
			ob_start();
			ob_implicit_flush(0);
		}
	
		$mode = $_GET['mode'] ?? $_POST['mode'] ?? '';
	
		$routes = [
			'regist'	=> function() {
				$route = new registRoute(
					$this->board,
					$this->config,
					$this->globalHTML,
					$this->postValidator,
					$this->staffSession,
					$this->moduleEngine,
					$this->actionLogger,
					$this->FileIO,
					$this->PIO,
					$this->threadSingleton
				);
				$route->registerPostToDatabase();
			},
			'admin'	=> function() {
				$route = new adminRoute($this->config,
					$this->globalHTML,
					$this->adminLoginController,
					$this->staffSession,
					$this->adminPageRenderer,
				);
				$route->drawAdminPage();
			},
			'status' => function() {
				$route = new statusRoute(
					$this->board,
					$this->config,
					$this->globalHTML,
					$this->staffSession,
					$this->templateEngine,
					$this->moduleEngine,
					$this->PIO,
					$this->FileIO
				);
				$route->drawStatus();
			},
			'module' => function() {
				$route = new moduleRoute($this->globalHTML, $this->moduleEngine);
				$route->handleModule();
			},
			'moduleloaded' => function() {
				$route = new moduleloadedRoute(
					$this->config, 
					$this->globalHTML, 
					$this->staffSession, 
					$this->moduleEngine
				);
				$route->listModules();
			},
			'account' => function() {
				$route = new accountRoute(
					$this->staffSession,
					$this->globalHTML,
					$this->softErrorHandler,
					$this->AccountIO,
					$this->adminTemplateEngine,
					$this->adminPageRenderer
				);
				$route->drawAccountPage();
			},
			'boards' => function() {
				$route = new boardsRoute(
					$this->config, 
					$this->staffSession, 
					$this->softErrorHandler, 
					$this->globalHTML, 
					$this->adminTemplateEngine, 
					$this->adminPageRenderer, 
					$this->boardIO, 
					$this->board
				);
				$route->drawBoardPage();
			},
			'overboard' => function() {
				$route = new overboardRoute(
					$this->config, 
					$this->boardIO, 
					$this->board, 
					$this->overboard, 
					$this->globalHTML
				);
				$route->drawOverboard();
			},
			'handleAccountAction' => function() {
				$route = new handleAccountActionRoute(
					$this->config,
					$this->board, 
					$this->AccountIO,
					$this->actionLogger,
					$this->softErrorHandler, 
					$this->staffSession
				);
				$route->handleAccountRequests();
			},
			'handleBoardRequests' => function() {
				$route = new handleBoardRequestsRoute(
					$this->config, 
					$this->softErrorHandler, 
					$this->boardIO, 
					$this->globalHTML
				);
				$route->handleBoardRequests();
			},
			'usrdel' => function() {
				$route = new usrdelRoute(
					$this->config,
					$this->board, 
					$this->globalHTML, 
					$this->moduleEngine, 
					$this->actionLogger, 
					$this->PIO, 
					$this->FileIO
				);
				$route->userPostDeletion();
			},
			'rebuild' => function() {
				$route = new rebuildRoute(
					$this->config, 
					$this->board, 
					$this->softErrorHandler, 
					$this->actionLogger, 
					$this->globalHTML
				);
				$route->handleRebuild();
			}
		];
	
		if (isset($routes[$mode])) {
			$routes[$mode]();
		} else {
			$defaultRoute = new defaultRoute(
				$this->config, 
				$this->board, 
				$this->threadSingleton,
				$this->PIO, 
				$this->globalHTML
			);
			$defaultRoute->handleDefault();
		}
	
		if ($this->config['GZIP_COMPRESS_LEVEL'] && $Encoding) {
			$this->finalizeGzip($Encoding);
		}
	}	

	private function finalizeGzip($Encoding) {
		if (!ob_get_length()) exit; // No content, no need to compress
		header('Content-Encoding: ' . $Encoding);
		header('X-Content-Encoding-Level: ' . $this->config['GZIP_COMPRESS_LEVEL']);
		header('Vary: Accept-Encoding');
		print gzencode(ob_get_clean(), $this->config['GZIP_COMPRESS_LEVEL']); // Compressed content
	}

}
