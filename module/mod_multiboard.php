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
	        'dbInfo' => [
	            'host'     => 'localhost',
	            'username' => 'mysqliusername',
	            'password' => 'mysqliuserpass',
	        ],
	        //boards to be visible to other modules
	        'boards' => [
	            'b' => [
	                'dbname' => 'boarddb',
	                'tablename' => 'imglog',
	                'boardname' => 'boardname',
	            ],
	            //add more boards here
	        ],
	        
	    ];
	} 
	public function ModulePage() {
		if (valid() < LEV_ADMIN) error('403 Access denied'); // ADMIN ONLY!
		
		header('Content-Type: application/json');
		$dat = json_encode($this->getConfig(),  JSON_PRETTY_PRINT);
		
		echo $dat;
	}
}
