<?php

class moduleloadedRoute {
	public function __construct(
		private readonly array $config,
		private board $board,
		private moduleEngine $moduleEngine,
		private readonly staffAccountFromSession $staffSession,
	) {}

	/* Displays loaded module information */
	public function listModules(): void {
		$dat = '';
		
		$dat .= $this->board->getBoardHead();

		$roleLevel = $this->staffSession->getRoleLevel();
		$links = '[<a href="' . $this->config['STATIC_INDEX_FILE'] . '?' . time() . '">' . _T('return') . '</a>]';
		$this->moduleEngine->dispatch('LinksAboveBar', array(&$links, 'modules', $roleLevel));

		$dat .= $links.'<h2 class="theading2">'._T('module_info_top').'</h2>
</div>

<div id="modules">
';

		/* Module Loaded */
		$dat .= _T('module_loaded') . '<ul>';
		foreach ($this->moduleEngine->getLoadedModules() as $m) {
			$dat .= '<li>' . $m . "</li>\n";
		}
		$dat .= "</ul><hr>\n";

		/* Module Information */
		$dat .= _T('module_info') . '<ul>';
		foreach ($this->moduleEngine->moduleInstance as $m) {
			$dat .= '<li>' . $m->getModuleName() . '<div>' . $m->getVersion()  . "</div></li>\n";
		}
		$dat .= '</ul><hr>
		</div>
		';
		$dat .= $this->board->getBoardFooter();
		echo $dat;
	}
}
