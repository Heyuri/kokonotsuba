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
	}
	
	private function onRenderPostAdminControls(&$modfunc, $post): void {
		$fh = new FlagHelper($post['status']);

		// attach the button if the post has a gif and the file hasn't deleted
		if ($post['ext'] === '.gif' && !$fh->value('fileDeleted')) {
			$animatedGifButtonUrl = $this->getModulePageURL(
				[
					'post_uid' => $post['post_uid']
				],
				false,
				true
			);

			$modfunc.= '<span class="adminFunctions adminGIFFunction">[<a href="' . htmlspecialchars($animatedGifButtonUrl) . '&post_uid=' . htmlspecialchars($post['post_uid']) . '"' . ($fh->value('agif') ? ' title="Use still image of GIF">g' : ' title="Use animated GIF">G') . '</a>]</span>';
		}
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