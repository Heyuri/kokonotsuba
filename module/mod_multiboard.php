<?php
// Multiboard capabilities module for kokonotsuba
class mod_multiboard extends moduleHelper {

	private $mypage;

	public function __construct($moduleEngine) {
		parent::__construct($moduleEngine);
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
