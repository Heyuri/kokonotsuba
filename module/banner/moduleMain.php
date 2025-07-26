<?php

namespace Kokonotsuba\Modules\banner;

use Kokonotsuba\ModuleClasses\abstractModuleMain;

use RuntimeException;

class moduleMain extends abstractModuleMain {
	private readonly string $myPage;
	private readonly string $bannerPath;
	private readonly string $staticUrl;

	public function getName(): string {
		return 'Kokonotsuba Banner Module';
	}

	public function getVersion(): string {
		return 'Kokonotsuba 2024';
	}

	public function initialize(): void {
		$this->myPage = $this->getModulePageURL();

		$this->bannerPath = $this->getConfig('ModuleSettings.BANNER_PATH');

		$this->staticUrl = $this->getConfig('STATIC_URL');

		$this->moduleContext->moduleEngine->addListener('PageTop', function (string &$pageTopHtml) {
			$this->onRenderPageTop($pageTopHtml);  // Call the method to modify the form
		});
	}

	private function getAllFilesFromDirectory($directory) {
		if (!is_dir($directory)) {
			throw new RuntimeException('Invalid directory: ' . $directory);
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
	public function onRenderPageTop(&$html) {
		$html .= '
      <div id="bannerContainer">
        <img width="300" height="100" src="' .$this->myPage.'" id="banner" title="Click to change">
      </div>';
	}

	private function outputbannerJSON() {
		header('Location: ', );
		$bannerDirectoryJSON = json_encode($this->getAllFilesFromDirectory($this->bannerPath),  JSON_PRETTY_PRINT);

		echo $bannerDirectoryJSON;
	}

	private function drawBannerRedirect() {
		$bannerImageArray = $this->getRandomFilesFromDirectory($this->bannerPath, 1); 
		$bannerImage = '';
		$bannerURL = '';
		if ($bannerImageArray !== false && !empty($bannerImageArray)) {
			$bannerImage = $bannerImageArray[0];  // Get the first (and only) file from the array
			$bannerURL = $this->staticUrl.'image/banner/'.$bannerImage;
		} else {
			$bannerImage = 'defaultbanner.png';
			$bannerURL = $this->staticUrl.'image/default/'.$bannerImage;
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
