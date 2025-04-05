<?php

/**
 * moduleHelper
 * Preloads commonly used module engine functions for easier access
 */

abstract class moduleHelper implements IModule {
	protected moduleEngine $moduleEngine;
	protected board $board;
	protected array $config;
	protected templateEngine $templateEngine;
	protected array $moduleBoardList;
	protected array $boardList;
	private string $className;

	public function __construct(moduleEngine $moduleEngine) {
		$boardIO = boardIO::getInstance();

		$this->moduleEngine = $moduleEngine;
		$this->moduleBoardList = $boardIO->getAllBoards();
		$this->board = $moduleEngine->board;
		$this->config = $moduleEngine->board->loadBoardConfig();
		$this->templateEngine = $moduleEngine->board->getBoardTemplateEngine();
		$this->className = get_class($this);
		$this->boardList = $boardIO->getAllBoards();

		// Auto-register module page
		if (method_exists($this, 'ModulePage')) {
			$this->moduleEngine->hookModuleMethod('ModulePage', [$this->className]);
		}
	}

	/**
	 * Module name builder to assist with generating a consistent module name
	 *
	 * @param string $description Description of module functionality
	 * @return string Formatted module name
	 */
	protected function moduleNameBuilder(string $description): string {
		return "{$this->className} : $description";
	}

	/**
	 * Returns the module's standalone page URL with optional query parameters
	 *
	 * @param array $params URL parameter key-value pairs
	 * @return string Module standalone page URL
	 * @see http_build_query()
	 */
	protected function getModulePageURL(array $params = []): string {
		$query = count($params) !== 0
			? '&amp;' . http_build_query($params, '', '&amp;')
			: '';
		return $this->moduleEngine->getModulePageURL($this->className) . $query;
	}

	/**
	 * Hook a module method to a specific hook point
	 *
	 * @param string $hookPoint Hook point name
	 * @param callable $methodObject Executable function
	 */
	protected function hookModuleMethod(string $hookPoint, callable $methodObject): void {
		$this->moduleEngine->hookModuleMethod($hookPoint, $methodObject);
	}

	/**
	 * Add a custom hook point
	 *
	 * @param string $hookName Custom hook point name
	 * @param callable $callable Executable function
	 */
	protected function addCHP(string $hookName, callable $callable): void {
		$this->moduleEngine->addCHP($hookName, $callable);
	}

	/**
	 * Call a custom hook point
	 *
	 * @param string $hookName Custom hook point name
	 * @param array $params Function parameters
	 */
	protected function callCHP(string $hookName, array $params): void {
		$this->moduleEngine->callCHP($hookName, $params);
	}

	/**
	 * Attach translation resource strings
	 *
	 * @param array $languagePack Translation resource strings
	 * @param string $fallbackLang Fallback language
	 * @throws InvalidArgumentException If fallback language is not found
	 */
	protected function attachLanguage(array $languagePack, string $fallbackLang = 'en_US'): void {
		$languageKey = $this->boardConfig['PIXMICAT_LANGUAGE'] ?? $fallbackLang;
		if (isset($languagePack[$languageKey])) {
			$languagePack = $languagePack[$languageKey];
		} elseif (isset($languagePack[$fallbackLang])) {
			$languagePack = $languagePack[$fallbackLang];
		} else {
			throw new InvalidArgumentException(
				sprintf('Assigned locale: %s not found.', $fallbackLang)
			);
		}

		foreach (array_keys($languagePack) as $key) {
			$languagePack[$this->className . '_' . $key] = $languagePack[$key];
			unset($languagePack[$key]);
		}

		PMCLibrary::getLanguageInstance()->attachLanguage($languagePack);
	}

	/**
	 * Retrieve translated string from resource file
	 *
	 * @param mixed ...$args Translation key and other arguments
	 * @return string Translated string
	 * @see LanguageLoader->getTranslation()
	 */
	protected function _T(...$args): string {
		if (isset($args[0]) && !empty($args[0])) {
			$args[0] = $this->className . '_' . $args[0];
		}
		return call_user_func_array(
			[PMCLibrary::getLanguageInstance(), 'getTranslation'],
			$args
		);
	}
}
