<?php
class mod_wf implements IModule {
	private $FILTERS = array(
		'/omg/i' => 'zOMG!!!1'
	);

	public function __construct($PMS) {
	}

	public function getModuleName() {
		return __CLASS__.' : K! Word Filter';
	}

	public function getModuleVersionInfo() {
		return 'Koko BBS Release 1';
	}

	public function autoHookRegistBeforeCommit(&$name, &$email, &$sub, &$com, &$category, &$age, $dest, $isReply, $imgWH, &$status) {
		foreach ($this->FILTERS as $filterin=>$filterout) {
			$com = preg_replace($filterin, $filterout, $com);
		}
	}
}
