<?php
/**
 * Kokonotsuba! Module Engine, derived from pixmicat's moduleEngine
 *
 */

 class moduleEngine {
	public array $ENV;
	public array $moduleInstance;
	public array $moduleLists;
	public array $hookPoints;
	public bool $loaded;
	public array $CHPList;
	public array $hooks;
	public board $board;

	/* Constructor */
	public function __construct(board $board, array $ENV = []) {
		$this->loaded = false; // Has the modules and hooks been loaded
		$this->board = $board; // Board page is loaded from
		
		$config = $board->loadBoardConfig();
		$moduleList = $config['ModuleList'];


		// Default environment values
		$defaultENV = array(
			'MODULE.PATH' => getBackendDir().'module/',
			'MODULE.PAGE' =>  $config['PHP_SELF'].'?mode=module&load=',
			'MODULE.LOADLIST' => $moduleList,
		);

		$this->ENV = array_merge($defaultENV, $ENV);
		
		$this->hooks = array_flip(array(
			'Head', 'Toplink', 'LinksAboveBar', 'PostInfo', 'AboveTitle',
			'PostForm', 'PostFormFile', 'PostFormSubmit',
			'GlobalMessage', 'BlotterPreview', 'ThreadFront', 'ThreadRear', 'ThreadPost', 'ThreadReply',
			'Foot', 'ModulePage', 'RegistBegin', 'RegistBeforeCommit', 'RegistAfterCommit', 'PostOnDeletion',
			'AdminList', 'AdminFunction', 'Authenticate', 'ThreadOrder'
		));

		$this->hookPoints = array(); // Hook points
		$this->moduleInstance = array(); // Module instances
		$this->moduleLists = array(); // Module class name list
		$this->CHPList = array(); // Custom hook point list
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
		return isset($this->hookPoints['ModulePage']);
	}

	/* Get module data by name from LOADLIST */
	public function getModuleDataByName(string $moduleName): array {
		return array($moduleName => $this->ENV['MODULE.LOADLIST'][$moduleName]);
	}

	/* Load extension modules */
	public function loadModules(mixed $specificModule = false): void {
		$loadlist = $this->ENV['MODULE.LOADLIST'];
	
		foreach ($loadlist as $moduleFileName => $moduleStatus) {
			// Skip if loading a specific module and this one isn't it
			if ($specificModule !== false && $moduleFileName !== $specificModule) {
				continue;
			}
	
			// Skip if not enabled
			if (!$moduleStatus) {
				continue;
			}
	
			// Skip if already instantiated
			if (isset($this->moduleInstance[$moduleFileName])) {
				continue;
			}
	
			$mpath = $this->ENV['MODULE.PATH'] . $moduleFileName . '.php';
	
			// Safely include once and instantiate if file exists
			if (is_file($mpath)) {
				include_once($mpath);
	
				// Check if class exists and hasn’t already been declared
				if (!class_exists($moduleFileName)) {
					error_log("Module class '$moduleFileName' does not exist after include.");
					continue;
				}
	
				// Track it
				$this->moduleLists[] = $moduleFileName;
				$this->moduleInstance[$moduleFileName] = new $moduleFileName($this);
			}
		}
	}	

	/* Get loaded module list */
	public function getLoadedModules(): array {
		if (!$this->loaded) $this->init();
		return $this->moduleLists;
	}

	/* Get module instance */
	public function getModuleInstance(string $module): mixed {
		return $this->moduleInstance[$module] ?? null;
	}

	/* Get method list for a specific module */
	public function getModuleMethods(string $module): array {
		if (!$this->loaded) $this->init();
		return array_search($module, $this->moduleLists) !== false ? get_class_methods($module) : array();
	}

	/* Get the URL for a module's standalone page */
	public function getModulePageURL(string $name): string {
		return $this->ENV['MODULE.PAGE'] . $name;
	}

	/* Automatically hook module methods to a hook point and return the reference */
	private function &__autoHookMethods(string $hookPoint): array {
		if (!isset($this->hookPoints[$hookPoint])) {
			$this->hookPoints[$hookPoint] = array();
		}
	
		if (isset($this->hooks[$hookPoint]) && empty($this->hookPoints[$hookPoint])) {
			foreach ($this->moduleLists as $m) {
				if (method_exists($this->moduleInstance[$m], 'autoHook' . $hookPoint)) {
					$this->hookModuleMethod(
						$hookPoint,
						array(&$this->moduleInstance[$m], 'autoHook' . $hookPoint)
					);
				}
			}
		}
	
		return $this->hookPoints[$hookPoint];
	}
	

	/* Attach module method to a specific hook point */
	public function hookModuleMethod(string $hookPoint, array $methodObject): void {
		if (!isset($this->hooks[$hookPoint])) {
			if (!isset($this->CHPList[$hookPoint])) $this->CHPList[$hookPoint] = 1;
		} else if (!isset($this->hookPoints[$hookPoint]) && $hookPoint != 'ModulePage') {
			if (!$this->loaded) $this->init();
			$this->__autoHookMethods($hookPoint);
		}
		$this->hookPoints[$hookPoint][] = $methodObject;
	}

	/* Execute module methods at a hook point */
	public function useModuleMethods(string $hookPoint, array $parameter): void {
		if (!$this->loaded) $this->init();
		$arrMethod =& $this->__autoHookMethods($hookPoint);
		$imax = count($arrMethod);
		for ($i = 0; $i < $imax; $i++) {
			call_user_func_array($arrMethod[$i], $parameter);
		}
	}

	/* Add a custom hook point */
	public function addCHP(string $CHPName, array $methodObject): void {
		$this->hookModuleMethod($CHPName, $methodObject);
	}

	/* Call a custom hook point */
	public function callCHP(string $CHPName, array $parameter): void {
		if (!$this->loaded) $this->init();
		if (isset($this->CHPList[$CHPName])) {
			$this->useModuleMethods($CHPName, $parameter);
		}
	}

}