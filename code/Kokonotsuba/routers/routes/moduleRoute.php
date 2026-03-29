<?php

// module route - handle module selection

namespace Kokonotsuba\routers\routes;

use Kokonotsuba\module_classes\moduleEngine;
use Kokonotsuba\error\softErrorHandler;
use Kokonotsuba\error\BoardException;
use Kokonotsuba\request\request;
use function Kokonotsuba\libraries\_T;

class moduleRoute {
	public function __construct(
		private moduleEngine $moduleEngine,
		private readonly softErrorHandler $softErrorHandler,
		private readonly request $request
	) {}

	public function handleModule(): void {
		$load = $this->request->getParameter('load', default: '');

		if ($this->moduleEngine->onlyLoad($load)) {
			$moduleInstance = $this->moduleEngine->moduleInstance[$load];
			
			$moduleMode = $this->request->getParameter('moduleMode', default: '');

			if ($moduleMode === 'admin') {
				if (isset($moduleInstance['moduleAdmin']) && method_exists($moduleInstance['moduleAdmin'], 'ModulePage')) {
					$moduleInstance['moduleAdmin']->authenticateRequest();
					$moduleInstance['moduleAdmin']->ModulePage();
				}
			} elseif ($moduleMode === 'javascript') {
				if (isset($moduleInstance['moduleJavascript']) && method_exists($moduleInstance['moduleJavascript'], 'ModulePage')) {
					$moduleInstance['moduleJavascript']->ModulePage();
				}
			} else {
				if (isset($moduleInstance['moduleMain']) && method_exists($moduleInstance['moduleMain'], 'ModulePage')) {
					$moduleInstance['moduleMain']->ModulePage();
				} else {
					throw new BoardException(_T('module_route_not_found'), 404);
				}
			}

		} else {
			$this->softErrorHandler->errorAndExit("Module Not Found(" . htmlspecialchars($load) . ")");
		}
	}
}
