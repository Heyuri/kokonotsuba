<?php

/**
 * Abstract base class for modules.
 *
 * Provides common functionality and context access for module implementations, 
 * such as configuration retrieval and template rendering support.
 * 
 * Modules extending this class can override the `initialize()` method to set up 
 * their state, and must implement `getName()` and `getVersion()` to define 
 * their identity.
 *
 */

namespace Kokonotsuba\ModuleClasses;

use Kokonotsuba\ModuleClasses\moduleContext;
use templateEngine;

abstract class abstractModule {
	public function __construct(
		protected readonly moduleContext $moduleContext, 
		protected readonly string $moduleName
	) {
		$this->initialize();
	}

	abstract public function getName(): string;
	
	abstract public function getVersion(): string;

	abstract public function initialize(): void; 

	protected function getConfig(string $key, $default = null) {
		return $this->moduleContext->board->getConfigValue($key, $default);
	}

	public function getModulePageURL(array $params = [], bool $forHtml = true, bool $useRequestUri = false): string {
		$params['mode'] = 'module';
		$params['load'] = $this->moduleName;

		$separator = $forHtml ? '&amp;' : '&';
		$query = http_build_query($params, '', $separator);

		$boardUrl = $this->moduleContext->board->getBoardURL(true);
		$requestUri = getCurrentUrlNoQuery(); 

		$baseUrl = $useRequestUri ? $requestUri : $boardUrl;

		return $baseUrl . '?' . $query;
	}
	
	protected function initModuleTemplateEngine(string $configKey, string $defaultValue): templateEngine {
		// get the desired template for the module
		$moduleTemplateName = $this->getConfig($configKey, $defaultValue);

		// create a copy of the templateEngine from the module context
		$moduleTemplateEngine = clone $this->moduleContext->templateEngine;

		// then set the template to use for the module page page
		$moduleTemplateEngine->setTemplateFile($moduleTemplateName);

		// now return the clone
		return $moduleTemplateEngine;
	}

	public function generateJavascriptUrl(string $fileName): string {
		// get the static url value
		$staticUrl = $this->getConfig('STATIC_URL');

		// get the base url for js files
		$javascriptBaseUrl = $staticUrl . 'js/';

		// set the url that module javascript is stored in
		$moduleJavascripBasetUrl = $javascriptBaseUrl . 'module/';

		// now generate the url of the js file
		$javascriptFileUrl = $moduleJavascripBasetUrl . $fileName;

		// now return the generated url
		return $javascriptFileUrl;
	}

	public function generateScriptHtml(string $url, bool $defer = false): string {
		// specify whether it should be defered
		if($defer) {
			// set the flag as 'defer'
			$deferString = 'defer';
		} 
		// otherwise it should be blank so the script loaded isn't defered
		else {
			// set flag as an empty string
			$deferString = '';
		}

		// generate the script html element
		$scriptHtml = '<script src="' . htmlspecialchars($url) . '" ' . htmlspecialchars($deferString) . '></script>';

		// return the script html
		return $scriptHtml;
	}

	public function generateScriptHeader(string $jsFileName, bool $defer = false): string {
		// generate the url path of the module javascript
		// this prevents the user from needing to refresh + wait through the rebuild
		$jsFileUrl = $this->generateJavascriptUrl($jsFileName);

		// generate the script html for including the module js
		// defer
		$jsHtml = $this->generateScriptHtml($jsFileUrl, $defer);

		// return the link html
		return $jsHtml;
	}

	public function buildWidgetEntry(
		string $url,
		string $action,
		string $label,
		string $subMenu
	): array {
		// assemble the widget
		$widget = [
			// url
			'href' => $url,
			
			// action, essentially a label the js will use internally
			'action' => $action,

			// label, what appears in the drop-menu list
			'label' => $label,

			// sub-menu
			'subMenu' => $subMenu
		];

		// return assembled widget
		return $widget;
	}

	public function generateToggleWidget(string &$moduleHeader, string $jsName, string $templateHtml): void {
		// generate the script header
		$jsHtml = $this->generateScriptHeader($jsName, true);

		// then append it to the header
		$moduleHeader .= $jsHtml;

		// append template html to header
		$moduleHeader .= $templateHtml;
	}

	public function generateTemplate(string $templateClass, string $content): string {
		// wrap it in template tags
		$templateHtml = '
			<template id="' . $templateClass . '">
			' . $content . '
			</template>';

		// return html
		return $templateHtml;
	}
}
