<?php

namespace Kokonotsuba\Modules\filter;

use Kokonotsuba\module_classes\abstractModuleMain;
use Kokonotsuba\module_classes\traits\listeners\PostWidgetListenerTrait;
use Kokonotsuba\module_classes\traits\listeners\AttachmentWidgetListenerTrait;
use Kokonotsuba\module_classes\traits\listeners\IncludeScriptTrait;

class moduleMain extends abstractModuleMain {
	use PostWidgetListenerTrait;
	use AttachmentWidgetListenerTrait;
	use IncludeScriptTrait;

	public function getName(): string {
		return 'Kokonotsuba Filter JS';
	}

	public function getVersion(): string  {
		return 'VER. 9001';
	}

	public function initialize(): void {
		// add post widget listener
		$this->listenPostWidget('onRenderPostWidget');

		// add attachment widget listener for hide image
		$this->listenAttachmentWidget('onRenderAttachmentWidget');

		// include the filter js for the hide post widget
		$this->registerScript('filter.js');
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

	private function onRenderAttachmentWidget(array &$widgetArray, array &$fileData): void {
		$widgetArray[] = $this->buildWidgetEntry('#', 'hideImage', 'Hide image', '');
	}

}
