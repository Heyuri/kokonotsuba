<?php
// animated gif module made for kokonotsuba by deadking
// "forked" from the siokara mod for pixmicat

namespace Kokonotsuba\Modules\animatedGif;

use board;
use FlagHelper;
use Kokonotsuba\ModuleClasses\abstractModuleMain;
use PMCLibrary;

class moduleMain extends abstractModuleMain {
	private readonly string $myPage;

	public function getName(): string {
		return 'Kokonotsuba Animated GIF';
	}

	public function getVersion(): string  {
		return 'Koko BBS Release 1';
	}

	public function initialize(): void {
		$this->myPage = $this->getModulePageURL();

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
	
	/*public function autoHookAdminList(&$modfunc, $post) {
		$fh = new FlagHelper($post['status']);
		if ($post['ext'] == '.gif') {
			$modfunc.= '<span class="adminFunctions adminGIFFunction">[<a href="' . $this->myPage . '&post_uid=' . htmlspecialchars($post['post_uid']) . '"'.($fh->value('agif')?' title="Use still image of GIF">g':' title="Use animated GIF">G').'</a>]</span>';
		}
	}

	public function ModulePage() {
		$softErrorHandler->handleAuthError(\Kokonotsuba\Root\Constants\userRole::LEV_JANITOR);

		$post = $PIO->fetchPosts($_GET['post_uid'] ?? 0)[0];
		if(!count($post)) $softErrorHandler->errorAndExit('ERROR: Post does not exist.');
		if($post['ext'] && $post['ext'] == '.gif') {
			if(!$FileIO->imageExists($post['tim'].$post['ext'], $this->board)) {
				$softErrorHandler->errorAndExit('ERROR: attachment does not exist.');
			}
			$flgh = new FlagHelper($post['status']);
			$flgh->toggle('agif');
			$PIO->setPostStatus($post['post_uid'], $flgh->toString());
			
			$logMessage = $flgh->value('agif') ? "Animated gif activated on No. {$post['no']}" : "Animated gif taken off of No. {$post['no']}";
			$actionLoggerService->logAction($logMessage, $this->board->getBoardUID());
			
			redirect('back', 0);
		} else {
			$softErrorHandler->errorAndExit('ERROR: Post does not have attechment.');
		}
	}*/
}
