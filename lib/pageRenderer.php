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
}