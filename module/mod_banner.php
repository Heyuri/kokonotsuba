<?php
class mod_banner extends ModuleHelper {
	public function __construct($PMS) {
		parent::__construct($PMS);
	}

	public function getModuleName() {
		return __CLASS__.' : Kokonotsuba Banner Module';
	}

	public function getModuleVersionInfo() {
		return 'Kokonotsuba 2024';
	}
	
	private function getAllFilesFromDirectory($directory) {
		if (!is_dir($directory)) {
			error('Invalid directory: ' . $directory);
			return false;
		}

		$files = array_diff(scandir($directory), array('.', '..'));
	
		return $files;
	}

	
	private function getRandomFilesFromDirectory($directory, $numFiles = 1) {
		if (!is_dir($directory)) {
			return false;
		}

		$files = array_diff(scandir($directory), array('.', '..'));
	
		if (empty($files)) {
			return false;
		}
	
		shuffle($files);
	
		if ($numFiles > count($files)) {
			$numFiles = count($files);
		}
	
		$randomFiles = array_slice($files, 0, $numFiles);

		return $randomFiles;
	}

	// Banner
	public function autoHookAboveTitle(&$html) {
		$bannerImageArray = $this->getRandomFilesFromDirectory($this->config['ModuleSettings']['BANNER_PATH'], 1); 

		$bannerImage = '';
		if ($bannerImageArray !== false && !empty($bannerImageArray)) {
			$bannerImage = $bannerImageArray[0];  // Get the first (and only) file from the array
		}

		$html .= '<div id="bannerContainer">
					<img border="1" src="' . $this->config['STATIC_URL'] . 'image/banner/' . $bannerImage . '" 
					id="banner" style="max-width: 300px;" title="Click to change" 
					onclick="change()">
				  </div>';
	}
	
	public function ModulePage() {
		header('Content-Type: application/json');
		$bannerDirectoryJSON = json_encode($this->getAllFilesFromDirectory($this->config['ModuleSettings']['BANNER_PATH']),  JSON_PRETTY_PRINT);
		
		echo $bannerDirectoryJSON;
	}

}
