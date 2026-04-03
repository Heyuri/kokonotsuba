<?php

namespace Kokonotsuba\Modules\filter;

use Kokonotsuba\module_classes\abstractModuleMain;
use Kokonotsuba\module_classes\listeners\PostWidgetListenerTrait;
use Kokonotsuba\module_classes\listeners\ModuleHeaderListenerTrait;
use Kokonotsuba\post\Post;

class moduleMain extends abstractModuleMain {
	use PostWidgetListenerTrait;
	use ModuleHeaderListenerTrait;

	public function getName(): string {
		return 'Kokonotsuba Filter JS';
	}

	public function getVersion(): string  {
		return 'VER. 9001';
	}

	public function initialize(): void {
		// add post widget listener
		$this->listenPostWidget('onRenderPostWidget');

		// add module header listener
		$this->listenModuleHeader('onGenerateModuleHeader');
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
