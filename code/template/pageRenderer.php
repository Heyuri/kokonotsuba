<?php

class pageRenderer {
	private ?templateEngine $templateEngine;
	private moduleEngine $moduleEngine;
	private board $board;

	// depend on templateEngine
	public function __construct(?templateEngine $templateEngine, moduleEngine $moduleEngine, IBoard $board) {
		$this->templateEngine = $templateEngine;
		$this->moduleEngine = $moduleEngine;
		$this->board = $board;
	}
	
	// get template block content with header and footer
	public function ParsePage(string $templateBlock = '', array $templateValues = array(), bool $isAdmin = false): string {
		$htmlOutput = '';
		$thead = '';

		$config = $this->board->loadBoardConfig();

		$liveIndexFile = $config['LIVE_INDEX_FILE'];
		$staticIndexFile = $config['STATIC_INDEX_FILE'];
		
		$htmlOutput .= $this->board->getBoardHead($this->board->getBoardTitle());

		if($isAdmin) {
			$htmlOutput .= generateAdminLinkButtons($liveIndexFile, $staticIndexFile, $this->moduleEngine);
			$htmlOutput .= drawAdminTheading($thead, new staffAccountFromSession);
		}

		$htmlOutput .= $this->templateEngine->ParseBlock($templateBlock, $templateValues);

		$htmlOutput .= $this->board->getBoardFooter(false);

		return $htmlOutput;
	}

	/* parse block - so you dont need to call an instance of templateEngine on its own when using pageRenderer */
	public function ParseBlock(string $templateBlock = '', array $templateValues = array()) {
		$htmlOutput = '';

		$htmlOutput .= $this->templateEngine->ParseBlock($templateBlock, $templateValues);
	
		return $htmlOutput;
	}
}