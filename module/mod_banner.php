<?php
class mod_banner extends moduleHelper {
	private $mypage;
	public function __construct(moduleEngine $moduleEngine, boardIO $boardIO, pageRenderer $pageRenderer, pageRenderer $adminPageRenderer) {
		parent::__construct($moduleEngine, $boardIO, $pageRenderer, $adminPageRenderer);		$this->mypage = $this->getModulePageURL();
	}

	public function getModuleName() {
		return __CLASS__.' : Kokonotsuba Banner Module';
	}

	public function getModuleVersionInfo() {
		return 'Kokonotsuba 2024';
	}

	private function getAllFilesFromDirectory($directory) {
		$globalHTML = new  globalHTML($this->board);
		if (!is_dir($directory)) {
			$globalHTML->error('Invalid directory: ' . $directory);
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
		$html .= '
      <div id="bannerContainer">
        <img width="300" height="100" src="' .$this->mypage.'" id="banner" title="Click to change">
      </div>';
	}

	private function outputbannerJSON() {
		header('Location: ', );
		$bannerDirectoryJSON = json_encode($this->getAllFilesFromDirectory($this->config['ModuleSettings']['BANNER_PATH']),  JSON_PRETTY_PRINT);

		echo $bannerDirectoryJSON;
	}

	private function drawBannerRedirect() {
		$bannerImageArray = $this->getRandomFilesFromDirectory($this->config['ModuleSettings']['BANNER_PATH'], 1); 
		$bannerImage = '';
		$bannerURL = '';
		if ($bannerImageArray !== false && !empty($bannerImageArray)) {
			$bannerImage = $bannerImageArray[0];  // Get the first (and only) file from the array
			$bannerURL = $this->config['STATIC_URL'].'image/banner/'.$bannerImage;
		} else {
			$bannerImage = 'defaultbanner.png';
			$bannerURL = $this->config['STATIC_URL'].'image/default/'.$bannerImage;
		}

		header('Location: '. $bannerURL); //redirect to image
	}

	public function ModulePage() {
		if(isset($_GET['bannerjson'])) {
			$this->outputbannerJSON();
			return;
		} else $this->drawBannerRedirect();

	}

}
