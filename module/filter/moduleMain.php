<?php

namespace Kokonotsuba\Modules\filter;

use Kokonotsuba\module_classes\abstractModuleMain;
use Kokonotsuba\module_classes\traits\IndicatorTrait;
use Kokonotsuba\module_classes\traits\listeners\PostWidgetListenerTrait;
use Kokonotsuba\module_classes\traits\listeners\AttachmentListenerTrait;
use Kokonotsuba\module_classes\traits\listeners\IncludeScriptTrait;
use Kokonotsuba\post\Post;

class moduleMain extends abstractModuleMain {
	use PostWidgetListenerTrait;
	use AttachmentListenerTrait;
	use IncludeScriptTrait;
	use IndicatorTrait;

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
		$this->listenAttachment('onRenderAttachment');

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

	private function onRenderAttachment(string &$imageBar, string &$imageHtml, string &$imageUrl, array &$fileData): void {
		$imageBar .= $this->renderIndicator('hideImage', '<a href="#" data-action="hideImage">Hide image</a>');
	}

}
