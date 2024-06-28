<?php
// Multiboard capabilities module for kokonotsuba
class mod_multiboard extends ModuleHelper {

	private $mypage;

	public function __construct($PMS) {
		parent::__construct($PMS);
		$this->mypage = $this->getModulePageURL();
	}

	public function getModuleName() {
		return __CLASS__.' : Multiboard API';
	}

	public function getModuleVersionInfo() {
		return 'Kokonotsuba';
	}
	//configuration
	private function getConfig() {
	    return $conf = [
	        //boards to be visible to other modules
	        'boards' => [
	            'b' => [
	                'dbname' => 'boarddb',
	                'tablename' => 'imglog',
	                'boardname' => 'boardname',
	                'imgpath' => '/path/to/kokonotsuba/image/dir/',
	            ],
	            //add more boards here
	        ],
	        
	    ];
	} 
	public function ModulePage() {
		header('Content-Type: application/json');
		$dat = json_encode($this->getConfig(),  JSON_PRETTY_PRINT);
		
		echo $dat;
	}
}
