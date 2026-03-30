<?php

namespace Kokonotsuba\Modules\filter;

use Kokonotsuba\module_classes\abstractModuleMain;
use Kokonotsuba\post\Post;

class moduleMain extends abstractModuleMain {
	public function getName(): string {
		return 'Kokonotsuba Filter JS';
	}

	public function getVersion(): string  {
		return 'VER. 9001';
	}

	public function initialize(): void {
		// add post widget listener
		$this->moduleContext->moduleEngine->addListener('PostWidget', function(array &$widgetArray, Post &$post) {
				$this->onRenderPostWidget($widgetArray);
		});

		// add module header listener
		$this->moduleContext->moduleEngine->addListener('ModuleHeader', function(&$moduleHeader) {
				$this->onGenerateModuleHeader($moduleHeader);
		});
	}

	private function onRenderPostWidget(array &$widgetArray): void {
		// build the widget entry
		$hideWidget = $this->buildWidgetEntry(
			'#',
			'hide',
			'Hide',
			''
		);

		// add the widget to the array
		$widgetArray[] = $hideWidget;
	}

	private function onGenerateModuleHeader(string &$moduleHeader): void {
		// include the filter js for the hide post widget
		$this->includeScript('filter.js', $moduleHeader);
	}
	
}
