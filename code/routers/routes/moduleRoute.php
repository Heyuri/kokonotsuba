<?php

// module route - handle module selection

class moduleRoute {
	public function __construct(
		private moduleEngine $moduleEngine,
		private readonly softErrorHandler $softErrorHandler
	) {}

	public function handleModule(): void {
		$load = $_REQUEST['load'] ?? '';

		if ($this->moduleEngine->onlyLoad($load)) {
			$moduleInstance = $this->moduleEngine->moduleInstance[$load];
			
			$moduleMode = $_REQUEST['moduleMode'] ?? '';

			if ($moduleMode === 'admin') {
				if (method_exists($moduleInstance['moduleAdmin'], 'ModulePage')) {
					$moduleInstance['moduleAdmin']->authenticateRequest();
					$moduleInstance['moduleAdmin']->ModulePage();
				}
			} elseif ($moduleMode === 'javascript') {
				if (method_exists($moduleInstance['moduleJavascript'], 'ModulePage')) {
					$moduleInstance['moduleJavascript']->ModulePage();
				}
			} else {
				if (method_exists($moduleInstance['moduleMain'], 'ModulePage')) {
					$moduleInstance['moduleMain']->ModulePage();
				}
			}

		} else {
			$this->softErrorHandler->errorAndExit("Module Not Found(" . htmlspecialchars($load) . ")");
		}
	}
}
