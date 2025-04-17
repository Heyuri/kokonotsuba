<?php
//Handle GET mode values for koko
class modeHandler {
	private readonly array $config;
	private readonly board $board;
	private readonly globalHTML $globalHTML;
	private readonly overboard $overboard;
	private readonly pageRenderer $pageRenderer;
	private readonly pageRenderer $adminPageRenderer;
	private readonly mixed $FileIO;
	private readonly mixed $PIO;
	private readonly boardIO $boardIO;
	private readonly AccountIO $AccountIO;
	private readonly ActionLogger $actionLogger;
	private readonly softErrorHandler $softErrorHandler;
	private readonly staffAccountFromSession $staffSession;
	private readonly postValidator $postValidator;

	private moduleEngine $moduleEngine;
	private templateEngine $templateEngine;
	private templateEngine $adminTemplateEngine;
	
	public function __construct(board $board) {
		// Validate required directories before anything else
		if (!file_exists($board->getFullConfigPath())) {
			throw new \RuntimeException("Board's config file <i>" . $board->getFullConfigPath() . "</i> was not found.");
		}

		if (!file_exists($board->getBoardStoragePath())) {
			throw new \RuntimeException("Board's storage directory <i>" . $board->getBoardStoragePath() . "</i> does not exist.");
		}

		$this->board = $board;
		$this->config = $board->loadBoardConfig();

		// Global HTML helper
		$this->globalHTML = new globalHTML($board);

		// Module and Template Engines
		$this->moduleEngine = new moduleEngine($board);
		$this->templateEngine = $board->getBoardTemplateEngine();
		$this->overboard = new overboard($this->config, $this->moduleEngine, $this->templateEngine);

		// Admin Template Engine Setup
		$adminTemplateFile = getBackendDir() . 'templates/admin.tpl';
		$dependencies = [
			'config'	=> $this->config,
			'boardData'	=> [
				'title'		=> $board->getBoardTitle(),
				'subtitle'	=> $board->getBoardSubTitle()
			]
		];
		$this->adminTemplateEngine = new templateEngine($adminTemplateFile, $dependencies);

		// Page Renderers
		$this->adminPageRenderer = new pageRenderer($this->adminTemplateEngine, $this->globalHTML);
		$this->pageRenderer = new pageRenderer($this->templateEngine, $this->globalHTML);

		// soft error page handler
		$this->softErrorHandler = new softErrorHandler($board);

		// account from session
		$this->staffSession = new staffAccountFromSession;

		// post + ip validator
		$IPValidator = new IPValidator($this->config, new IPAddress);
		$this->postValidator = new postValidator($this->board, $this->config, $this->globalHTML, $IPValidator);
	
		// File I/O and Logging
		$this->boardIO = boardIO::getInstance();
		$this->FileIO = PMCLibrary::getFileIOInstance();
		$this->PIO = PIOPDO::getInstance();
		$this->AccountIO = AccountIO::getInstance();
		$this->actionLogger = ActionLogger::getInstance();
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
					$this->PIO
				);
				$route->registerPostToDatabase();
			},
			'admin'	=> function() {
				$route = new adminRoute(
					$this->board,
					$this->config,
					$this->globalHTML,
					$this->staffSession,
					$this->moduleEngine,
					$this->AccountIO
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
					$this->config,
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
					$this->staffSession, 
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
				$this->actionLogger, 
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
