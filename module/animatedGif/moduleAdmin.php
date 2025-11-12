<?php

namespace Kokonotsuba\Modules\animatedGif;

use BoardException;
use FlagHelper;
use Kokonotsuba\ModuleClasses\abstractModuleAdmin;
use Kokonotsuba\Root\Constants\userRole;

class moduleAdmin extends abstractModuleAdmin {
	public function getRequiredRole(): userRole {
		return \Kokonotsuba\Root\Constants\userRole::LEV_JANITOR;
	}

	public function getName(): string {
		return 'Animated gif toggle tool';
	}

	public function getVersion(): string {
		return 'Koko 2025';
	}

	public function initialize(): void {
		$this->moduleContext->moduleEngine->addRoleProtectedListener(
			$this->getRequiredRole(),
			'PostAdminControls',
			function(string &$modControlSection, array &$post) {
				$this->onRenderPostAdminControls($modControlSection, $post);
			}
		);
		
		$this->moduleContext->moduleEngine->addRoleProtectedListener(
			$this->getRequiredRole(),
			'ModeratePostWidget',
			function(array &$widgetArray, array &$post) {
				$this->onRenderPostWidget($widgetArray, $post);
			}
		);

		$this->moduleContext->moduleEngine->addRoleProtectedListener(
			$this->getRequiredRole(),
			'ModuleAdminHeader',
			function(&$moduleHeader) {
				$this->onGenerateModuleHeader($moduleHeader);
			}
		);
	}
	
	private function onRenderPostAdminControls(&$modfunc, $post): void {
		// post flag helper
		$postStatus = new FlagHelper($post['status']);

		// whether the post can have its gif animated
		$canAnimate = $this->isAnimatedGif($postStatus, $post['ext']);

		// attach the button if the post has a gif and the file hasn't deleted
		if ($canAnimate) {
			// anigif url
			$animatedGifButtonUrl = $this->generateAnimatedGifUrl($post['post_uid']);

			// add control to postinfoextra
			$modfunc.= '<noscript><span class="adminFunctions adminGIFFunction">[<a href="' . htmlspecialchars($animatedGifButtonUrl) . '"' . ($postStatus->value('agif') ? ' title="Use still image of GIF">g' : ' title="Use animated GIF">G') . '</a>]</span></noscript>';
		}
	}

	private function isAnimatedGif(FlagHelper $postStatus, string $extension): bool {
		// the following condition:
		// true if the extension is a gif - and the post doesn't have the fileDeleted attribute (i.e it hasn't been deleted)
		$canAnimate = $extension === '.gif' && !$postStatus->value('fileDeleted');
	
		// return condition
		return $canAnimate;
	}

	private function onRenderPostWidget(array &$widgetArray, array &$post): void {
		// get post status
		$postStatus = new FlagHelper($post['status']);

		// whether the post can have its gif animated
		$canAnimate = $this->isAnimatedGif($postStatus, $post['ext']);

		// return early if this post can't have its attachment animated
		if(!$canAnimate) {
			return;
		}

		// get anigif label
		$animatedGifLabel = $this->getAnimatedGifLabel($postStatus);
		
		// generate anigif url
		$animatedGifUrl = $this->generateAnimatedGifUrl($post['post_uid']);

		// build the widget entry
		$animatedGifWidget = $this->buildWidgetEntry(
			$animatedGifUrl, 
			'animateGif', 
			$animatedGifLabel, 
			''
		);

		// add the widget to the array
		$widgetArray[] = $animatedGifWidget;
	}

	private function getAnimatedGifLabel(FlagHelper $postStatus): string {
		// anigif flag
		$isAnimated = $postStatus->value('agif');

		// if the attachment is already animated then the action is to un-animated it
		if($isAnimated) {
			return 'Disable gif animation';
		}
		// if the thread isn't already animated then the action is to animate it
		else {
			return 'Animate gif';
		}
	}

	private function generateAnimatedGifUrl(int $postUid): string {
		// generate animated gif url
		$animatedGifUrl = $this->getModulePageURL(
			[
				'post_uid' => $postUid
			],
			false,
			true
		);

		// return url
		return $animatedGifUrl;
	}

	private function onGenerateModuleHeader(string &$moduleHeader): void {
		// generate the script header
		$jsHtml = $this->generateScriptHeader('animatedGif.js', true);

		// then append it to the header
		$moduleHeader .= $jsHtml;
	}

	public function ModulePage() {
		// get the post uid from request
		$postUid = $_GET['post_uid'] ?? null;

		// No post selected, so throw exception
		if($postUid === null) {
			throw new BoardException("No post selected.");
		}

		// get the post
		$post = $this->moduleContext->postRepository->getPostByUid($postUid);
		
		// throw an exception if the post doesn't exist (i.e it got deleted by the time the request was sent)
		if(!$post) {
			throw new BoardException("ERROR: Post does not exist.");
		}

		// only run the toggle code if the file is a gif
		if($post['ext'] && $post['ext'] == '.gif') {
			$flgh = new FlagHelper($post['status']);

			// throw exception if the file has been deleted
			if($flgh->value('fileDeleted')) {
				throw new BoardException('ERROR: attachment does not exist.');
			}

			// toggle the flag variable
			$flgh->toggle('agif');

			// update the post status in database
			$this->moduleContext->postRepository->setPostStatus($post['post_uid'], $flgh->toString());
			
			// generate log message
			$logMessage = $flgh->value('agif') ? 'Animated gif activated on No. ' . htmlspecialchars($post['no']) : 'Animated gif taken off of No. ' . htmlspecialchars($post['no']);
			
			// log mod action to database
			$this->moduleContext->actionLoggerService->logAction($logMessage, $post['boardUID']);
			
			// redirect back
			redirect('back', 0);
		} else {
			// it wasn't a gif, so throw an exception
			throw new BoardException('ERROR: Attached file is not a gif.');
		}
	}
}