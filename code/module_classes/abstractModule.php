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

	protected function getModulePageURL(array $params = [], bool $forHtml = true, bool $useRequestUri = false): string {
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
}
