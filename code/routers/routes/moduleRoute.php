<?php

// module route - handle module selection

class moduleRoute {
	private readonly globalHTML $globalHTML;
	private moduleEngine $moduleEngine;

	public function __construct(globalHTML $globalHTML, moduleEngine $moduleEngine) {
		$this->globalHTML = $globalHTML;
		$this->moduleEngine = $moduleEngine;
	}

	public function handleModule(): void {
		$load = $_GET['load'] ?? $_POST['load'] ?? '';
		if ($this->moduleEngine->onlyLoad($load)) {
			$this->moduleEngine->moduleInstance[$load]->ModulePage();
		} else {
			$this->globalHTML->error("Module Not Found(" . htmlspecialchars($load) . ")");
		}
	}
}
