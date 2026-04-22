<?php
//Handle GET mode values for koko

namespace Kokonotsuba\routers;

use Kokonotsuba\containers\appContainer;
use Kokonotsuba\board\board;
use Kokonotsuba\routers\routes\registRoute;
use Kokonotsuba\routers\routes\statusRoute;
use Kokonotsuba\routers\routes\adminRoute;
use Kokonotsuba\routers\routes\moduleRoute;
use Kokonotsuba\routers\routes\moduleloadedRoute;
use Kokonotsuba\routers\routes\accountRoute;
use Kokonotsuba\routers\routes\boardsRoute;
use Kokonotsuba\routers\routes\overboardRoute;
use Kokonotsuba\routers\routes\handleAccountActionRoute;
use Kokonotsuba\routers\routes\handleBoardRequestsRoute;
use Kokonotsuba\routers\routes\usrdelRoute;
use Kokonotsuba\routers\routes\rebuildRoute;
use Kokonotsuba\routers\routes\actionLogRoute;
use Kokonotsuba\routers\routes\managePostsRoute;
use Kokonotsuba\routers\routes\jsonApiRoute;
use Kokonotsuba\routers\routes\defaultRoute;
use Kokonotsuba\profiler\excimerProfiler;

use function Kokonotsuba\libraries\CheckSupportGZip;

class modeHandler {
	public function __construct(
		private appContainer $container	
	) {}

	/**
	 * Determine the profiler category for the current request, or null if not profiled.
	 */
	private function getProfilerCategory(string $mode): ?string {
		if ($mode === 'regist') {
			return 'posting';
		}
		if ($mode === 'rebuild') {
			return 'rebuild';
		}
		if ($mode === 'module') {
			$load = $this->container->get('request')->getParameter('load', default: '');
			if ($load === 'adminDel') {
				return 'deleting';
			}
		}
		return null;
	}

	public function validateBoard(board $board): void {
		if (!file_exists($board->getFullConfigPath())) {
			die("Board's config file <i>" . $board->getFullConfigPath() . "</i> was not found.");
		}

		if (!file_exists($board->getBoardStoragePath())) {
			die("Board's storage directory <i>" . $board->getBoardStoragePath() . "</i> does not exist.");
		}
	}

	public function handle() {
		if ($this->container->get('config')['GZIP_COMPRESS_LEVEL'] && ($Encoding = CheckSupportGZip($this->container->get('request')))) {
			ob_start();
			ob_implicit_flush(0);
		}
	
		$mode = $this->container->get('request')->getParameter('mode', 'GET')
			?? $this->container->get('request')->getParameter('mode', 'POST', '');
	
		$routes = [
			'regist'	=> function() {
				$route = new registRoute(
					$this->container->get('board'),
					$this->container->get('config'),
					$this->container->get('postValidator'),
					$this->container->get('staffAccountFromSession'),
					$this->container->get('transactionManager'),
					$this->container->get('moduleEngine'),
					$this->container->get('actionLoggerService'),
					$this->container->get('cookieService'),
					$this->container->get('postRepository'),
					$this->container->get('postService'),
					$this->container->get('fileService'),
					$this->container->get('threadRepository'),
					$this->container->get('threadService'),
					$this->container->get('quoteLinkService'),
					$this->container->get('request')
				);
				$route->registerPostToDatabase();
			},
			'status' => function() {
				$route = new statusRoute(
					$this->container->get('board'),
					$this->container->get('config'),
					$this->container->get('templateEngine'),
					$this->container->get('moduleEngine'),
					$this->container->get('threadRepository'),
					$this->container->get('postRepository')
				);
				$route->drawStatus();
			},
			'admin'	=> function() {
				$route = new adminRoute(
					$this->container->get('board'),
					$this->container->get('adminLoginController'),
					$this->container->get('adminPageRenderer'),
					$this->container->get('request'),
				);
				$route->drawAdminPage();
			},
			'module' => function() {
				$route = new moduleRoute(
					$this->container->get('moduleEngine'),
					$this->container->get('softErrorHandler'),
					$this->container->get('request')
				);
				$route->handleModule();
			},
			'moduleloaded' => function() {
				$route = new moduleloadedRoute(
					$this->container->get('config'), 
					$this->container->get('board'),
					$this->container->get('moduleEngine'),
					$this->container->get('staffAccountFromSession'), 
					$this->container->get('moduleEngine')
				);
				$route->listModules();
			},
			'account' => function() {
				$route = new accountRoute(
					$this->container->get('config'),
					$this->container->get('staffAccountFromSession'),
					$this->container->get('softErrorHandler'),
					$this->container->get('accountRepository'),
					$this->container->get('adminTemplateEngine'),
					$this->container->get('adminPageRenderer'),
					$this->container->get('request')
				);
				$route->drawAccountPage();
			},
			'viewStaffAccount' => function() {
				$route = new accountRoute(
					$this->container->get('config'),
					$this->container->get('staffAccountFromSession'),
					$this->container->get('softErrorHandler'),
					$this->container->get('accountRepository'),
					$this->container->get('adminTemplateEngine'),
					$this->container->get('adminPageRenderer'),
					$this->container->get('request')
				);
				$route->drawStaffAccountPage();
			},
			'boards' => function() {
				$route = new boardsRoute(
					$this->container->get('config'), 
					$this->container->get('staffAccountFromSession'), 
					$this->container->get('softErrorHandler'), 
					$this->container->get('adminTemplateEngine'), 
					$this->container->get('adminPageRenderer'), 
					$this->container->get('boardService'),
					$this->container->get('board'),
					$this->container->get('request')
				);
				$route->drawBoardPage();
			},
			'overboard' => function() {
				$route = new overboardRoute(
					$this->container->get('config'), 
					$this->container->get('visibleBoards'),
					$this->container->get('boardRepository'),
					$this->container->get('board'), 
					$this->container->get('overboard'),
					$this->container->get('cookieService'),
					$this->container->get('request')
				);
				$route->drawOverboard();
			},
			'handleAccountAction' => function() {
				$route = new handleAccountActionRoute(
					$this->container->get('config'),
					$this->container->get('board'), 
					$this->container->get('accountService'),
					$this->container->get('actionLoggerService'),
					$this->container->get('softErrorHandler'), 
					$this->container->get('staffAccountFromSession'),
					$this->container->get('request')
				);
				$route->handleAccountRequests();
			},
			'handleBoardRequests' => function() {
				$route = new handleBoardRequestsRoute(
					$this->container->get('databaseConnection'),
					$this->container->get('config'), 
					$this->container->get('softErrorHandler'),
					$this->container->get('boardService'), 
					$this->container->get('boardPathService'),
					$this->container->get('transactionManager'),
					$this->container->get('postRepository'),
					$this->container->get('threadRepository'),
					$this->container->get('fileService'),
					$this->container->get('quoteLinkRepository'),
					$this->container->get('request')
				);
				$route->handleBoardRequests();
			},
			'usrdel' => function() {
				$route = new usrdelRoute(
					$this->container->get('config'),
					$this->container->get('actionLoggerService'), 
					$this->container->get('postService'),
					$this->container->get('deletedPostsService'), 
					$this->container->get('softErrorHandler'),
					$this->container->get('cookieService'),
					$this->container->get('postPolicy'),
					$this->container->get('currentUserId'),
					$this->container->get('request')
				);
				$route->userPostDeletion();
			},
			'rebuild' => function() {
				$route = new rebuildRoute(
					$this->container->get('board'),
					$this->container->get('softErrorHandler'), 
					$this->container->get('actionLoggerService') 
				);

				$route->handleRebuild();
			},

			'actionLog' => function() {
				$route = new actionLogRoute(
					$this->container->get('board'),
					$this->container->get('config'),
					$this->container->get('actionLoggerService'),
					$this->container->get('softErrorHandler'),
					$this->container->get('adminPageRenderer'),
					$this->container->get('boardList'),
					$this->container->get('postDateFormatter'),
					$this->container->get('request'),
				);
				$route->drawActionLog();
			},
			'managePosts' => function() {
				$route = new managePostsRoute(
					$this->container->get('board'),
					$this->container->get('config'),
					$this->container->get('moduleEngine'),
					$this->container->get('staffAccountFromSession'),
					$this->container->get('postRepository'),
					$this->container->get('postService'),
					$this->container->get('softErrorHandler'),
					$this->container->get('actionLoggerService'),
					$this->container->get('adminPageRenderer'),
					$this->container->get('boardList'),
					$this->container->get('deletedPostsService'),
					$this->container->get('postRenderingPolicy'),
					$this->container->get('currentUserId'),
					$this->container->get('request')
				);
				$route->drawManagePostsPage();
			},

			'api' => function() {
				$route = new jsonApiRoute(
					[
						'boardApi' => $this->container->get('boardApi'),
						'threadApi' => $this->container->get('threadApi'),
					],
					$this->container->get('request')
				);
				$route->routeApiRequests();
			}
		];

		// Start Excimer profiler if enabled and this route is profiled
		$profiler = null;
		$globalConfig = $this->container->get('globalConfig');
		if (!empty($globalConfig['EXCIMER_PROFILING'])) {
			$category = $this->getProfilerCategory($mode);
			if ($category !== null) {
				$outputPath = getBackendGlobalDir() . 'excimer';
				$profiler = new excimerProfiler($outputPath, $category);
				$profiler->start();
			}
		}
	
		if (isset($routes[$mode])) {
			$routes[$mode]();
		} else {
			$defaultRoute = new defaultRoute(
				$this->container->get('config'), 
				$this->container->get('board'), 
				$this->container->get('threadRepository'),
				$this->container->get('postRepository'),
				$this->container->get('postRedirectService'),
				$this->container->get('postRenderingPolicy'),
				$this->container->get('request')
			);
			$defaultRoute->handleDefault();
		}

		// Stop Excimer profiler and write output
		if ($profiler !== null) {
			$profiler->stop();
		}
	
		if ($this->container->get('config')['GZIP_COMPRESS_LEVEL'] && $Encoding) {
			$this->finalizeGzip($Encoding);
		}
	}	

	private function finalizeGzip($Encoding) {
		if (!ob_get_length()) exit; // No content, no need to compress
		header('Content-Encoding: ' . $Encoding);
		header('X-Content-Encoding-Level: ' . $this->container->get('config')['GZIP_COMPRESS_LEVEL']);
		header('Vary: Accept-Encoding');
		print gzencode(ob_get_clean(), $this->container->get('config')['GZIP_COMPRESS_LEVEL']); // Compressed content
	}

}
