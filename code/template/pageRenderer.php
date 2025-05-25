<?php

class pageRenderer {
	private $templateEngine, $globalHTML;

	// depend on templateEngine and globalHTML
	public function __construct(templateEngine $templateEngine, globalHTML $globalHTML) {
		$this->templateEngine = $templateEngine;
		$this->globalHTML = $globalHTML;
	}
	
	// get template block content with header and footer
	public function ParsePage(string $templateBlock = '', array $templateValues = array(), bool $isAdmin = false): string {
		$htmlOutput = '';
		
		$this->globalHTML->head($htmlOutput);
		if($isAdmin) {
			$htmlOutput .= $this->globalHTML->generateAdminLinkButtons();
			$htmlOutput .= $this->globalHTML->drawAdminTheading($thead, new staffAccountFromSession);
		}
		$htmlOutput .= $this->templateEngine->ParseBlock($templateBlock, $templateValues);
		$this->globalHTML->foot($htmlOutput);

		return $htmlOutput;
	}

	/* parse block - so you dont need to call an instance of templateEngine on its own when using pageRenderer */
	public function ParseBlock(string $templateBlock = '', array $templateValues = array()) {
		$htmlOutput = '';

		$htmlOutput .= $this->templateEngine->ParseBlock($templateBlock, $templateValues);
	
		return $htmlOutput;
	}
}