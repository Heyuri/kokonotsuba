<?php
// animated gif module made for kokonotsuba by deadking
// "forked" from the siokara mod for pixmicat

namespace Kokonotsuba\Modules\animatedGif;

use board;
use FlagHelper;
use Kokonotsuba\ModuleClasses\abstractModuleMain;
use PMCLibrary;

class moduleMain extends abstractModuleMain {
	public function getName(): string {
		return 'Kokonotsuba Animated GIF';
	}

	public function getVersion(): string  {
		return 'Koko BBS Release 1';
	}

	public function initialize(): void {

		$this->moduleContext->moduleEngine->addListener('RegistBeforeCommit', function ($name, &$email, &$sub, &$com, &$category, &$age, $file, $isReply, &$status, $thread) {
			$this->onBeforeCommit($file, $status);  // Call the method to modify the form
		});

		$this->moduleContext->moduleEngine->addListener('Post', function (&$arrLabels, $post, $threadPosts, $board) {
			$this->onRenderPost($arrLabels, $post, $board);
		});

		$this->moduleContext->moduleEngine->addListener('PostFormFile', function(string &$formFileSection) {
			$this->onRenderPostFormFile($formFileSection);
		});
	}

	public function onRenderPostFormFile(string &$file){
		$file.= '<div id="anigifContainer"><label id="anigifLabel" title="Makes GIF thumbnails animated"><input type="checkbox" name="anigif" id="anigif" value="on">Animated GIF</label></div>';
	}

	public function onBeforeCommit($file, &$status) {
		$mimeType = $file->getMimeType();

		// Don't include it directly on the page if its not a GIF
		if($mimeType !== 'image/gif') {
			return;
		}

		
		$anigifRequested = isset($_POST['anigif']);
		
		$flagHelper = new FlagHelper($status);
		if ($anigifRequested) {
			$flagHelper->toggle('agif');
			$status = $flagHelper->toString();
		}
	}

	public function onRenderPost(array &$arrLabels, array $post, board $board): void {
		$FileIO = PMCLibrary::getFileIOInstance();

		$fh = new FlagHelper($post['status']);
		if($fh->value('agif')) {
			$fileName = $post['tim'] . $post['ext'];
			// check if the file exists in here so time isn't wasted with checking if the file exists
			if(!$FileIO->imageExists($fileName, $board)) {
				return;
			}

			$fileSize = $FileIO->getImageFilesize($fileName, $board);
			
			if($fileSize >= $this->getConfig('ModuleSettings.MAX_SIZE_FOR_ANIMATED_GIF') * 1024) {
				return;
			}
			
			$imgURL = $FileIO->getImageURL($post['tim'].$post['ext'], $board);
			$arrLabels['{$IMG_SRC}'] = preg_replace('/<img src=".*"/U','<img src="'.$imgURL.'"',$arrLabels['{$IMG_SRC}']);
			$arrLabels['{$IMG_BAR}'].= '<span class="animatedGIFLabel imageOptions">[Animated GIF]</span>';
		}
	}
}
