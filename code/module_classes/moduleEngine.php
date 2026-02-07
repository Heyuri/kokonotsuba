<?php

use Kokonotsuba\ModuleClasses\abstractModuleAdmin;
use Kokonotsuba\ModuleClasses\moduleContext;
use Kokonotsuba\Root\Constants\userRole;

/**
 * Kokonotsuba! Module Engine, derived from pixmicat's moduleEngine
 *
 */

 class moduleEngine {
	public array $ENV;
	public array $moduleInstance;
	public array $moduleLists;
	private bool $loaded;
	private readonly moduleEngineContext $moduleEngineContext;
	private hookDispatcher $hookDispatcher;

	/* Constructor */
	public function __construct(moduleEngineContext $moduleEngineContext, array $ENV = []) {
		$this->loaded = false; // Has the modules and hooks been loaded
		$this->moduleEngineContext = $moduleEngineContext;
		$this->hookDispatcher = new HookDispatcher(); // Initialize the hook dispatcher

		// Default environment values
		$defaultENV = array(
			'MODULE.PATH' => getBackendDir().'module/',
			'MODULE.PAGE' =>  $moduleEngineContext->liveIndexFile.'?mode=module&load=',
			'MODULE.LOADLIST' => $moduleEngineContext->moduleList,
		);

		$this->ENV = array_merge($defaultENV, $ENV);
		$this->moduleLists = [];
		$this->moduleInstance = [];

		$this->init();
	}

	/* Initialize and load modules */
	public function init(): bool {
		if ($this->loaded) return true;
		$this->loaded = true;
		$this->loadModules();
		return true;
	}

	/* Load a specific module only */
	public function onlyLoad(string $specificModule): bool {
		if (!array_key_exists($specificModule, $this->ENV['MODULE.LOADLIST'])) return false;

		$this->loadModules($specificModule);

		return in_array($specificModule, $this->moduleLists, true);
	}

	/* Load extension modules */
	public function loadModules(mixed $specificModule = false): void {
		$loadlist = $this->ENV['MODULE.LOADLIST'];

		if($loadlist === null) {
			return;
		}
		
		$adminTemplatePath = getBackendDir() . 'templates/admin.tpl';

		$dependencies = [
			'config' => $this->moduleEngineContext->config
		];

		$adminTemplateEngine = new templateEngine($adminTemplatePath, $dependencies);

		// Create the admin page renderer
		$adminPageRenderer = new pageRenderer($adminTemplateEngine, $this, $this->moduleEngineContext->board);

		// Prepare the module context
		$moduleContext = new moduleContext(
			$this->moduleEngineContext->board, 
			$this->moduleEngineContext->templateEngine, 
			$this->moduleEngineContext->config, 
			$this->moduleEngineContext->postRepository, 
			$this->moduleEngineContext->postService,
			$this->moduleEngineContext->threadRepository, 
			$this->moduleEngineContext->threadService,
			$adminPageRenderer, // Pass adminPageRenderer here
			$this,
			$this->moduleEngineContext->boardService,
			$this->moduleEngineContext->postSearchService,
			$this->moduleEngineContext->quoteLinkService,
			$this->moduleEngineContext->actionLoggerService,
			$this->moduleEngineContext->postRedirectService,
			$this->moduleEngineContext->deletedPostsService,
			$this->moduleEngineContext->fileService,
			$this->moduleEngineContext->capcodeService,
			$this->moduleEngineContext->userCapcodes,
			$this->moduleEngineContext->transactionManager,
			$this->moduleEngineContext->postRenderingPolicy,
		);

		foreach ($loadlist as $moduleName => $moduleStatus) {
			// Skip if loading a specific module and this one isn't it
			if ($specificModule !== false && $moduleName !== $specificModule) {
				continue;
			}

			// Skip if not enabled
			if (!$moduleStatus) {
				continue;
			}

			// Skip if already instantiated
			if (isset($this->moduleInstance[$moduleName])) {
				continue;
			}

			$modulePath = $this->ENV['MODULE.PATH'] . $moduleName . '/';
			
			// Safely include once and instantiate if file exists
			if (is_dir($modulePath)) {
				$moduleAdminPath = $modulePath . 'moduleAdmin.php';
				$moduleJavascriptPath = $modulePath . 'moduleJavascript.php';
				$moduleMainPath = $modulePath . 'moduleMain.php';

				if (file_exists($moduleMainPath)) {
					include_once $moduleMainPath;
				}
				
				if (file_exists($moduleJavascriptPath)) {
					include_once $moduleJavascriptPath;
				}
				
				if (file_exists($moduleAdminPath)) {
					include_once $moduleAdminPath;
				}

				// Track it
				$this->moduleLists[] = $moduleName;
				
				$moduleNamespace = "\\Kokonotsuba\\Modules\\{$moduleName}\\";

				$moduleMain = $moduleNamespace . 'moduleMain';
				$moduleJavascript = $moduleNamespace . 'moduleJavascript';
				$moduleAdmin = $moduleNamespace . 'moduleAdmin';

				// Instantiate and store module instances if classes exist
				if (class_exists($moduleMain)) {
					$this->moduleInstance[$moduleName]['moduleMain'] = new $moduleMain($moduleContext, $moduleName);
				}
				if (class_exists($moduleJavascript)) {
					$this->moduleInstance[$moduleName]['moduleJavascript'] = new $moduleJavascript($moduleContext, $moduleName);
				}
				if (class_exists($moduleAdmin)) {
					$this->moduleInstance[$moduleName]['moduleAdmin'] = new $moduleAdmin($moduleContext, $moduleName);
				}
			}

		}
	}

	/* Add a listener to a hook */
	public function addListener(string $hookPoint, callable $listener, int $priority = 0): void {
		$this->hookDispatcher->addListener($hookPoint, $listener, $priority);
	}

	public function addRoleProtectedListener(userRole $requiredRole, string $event, callable $listener, int $priority = 0, bool $throwException = false): void {;
		$currentRole = getRoleLevelFromSession();

		$this->hookDispatcher->addRoleProtectedListener(
			$event,
			$listener,
			$requiredRole,
			$currentRole,
			$priority,
			$throwException
		);
	}

	/* Dispatch an event (trigger hook point) */
	public function dispatch(string $hookPoint, array $parameters = []): void {
		$this->hookDispatcher->dispatch($hookPoint, $parameters);
	}

	/* Call a custom hook point */
	public function callCHP(string $CHPName, array $parameters): void {
		$this->dispatch($CHPName, $parameters);
	}

}