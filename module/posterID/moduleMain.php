<?php

namespace Kokonotsuba\Modules\posterID;

use IPAddress;
use Kokonotsuba\ModuleClasses\abstractModuleMain;
use Kokonotsuba\Root\Constants\userRole;

class moduleMain extends abstractModuleMain {
	// Property for whether to display IDs or not
	private bool $DISPLAY_ID;

	public function getName(): string {
		return 'Kokonotsuba poster ID module';
	}

	public function getVersion(): string  {
		return 'VER. 9001';
	}

	public function initialize(): void {
		$this->DISPLAY_ID = $this->getConfig('ModuleSettings.DISP_ID', false);

		// only add the listener for displaying IDs if displaying IDs is enabled
		if($this->DISPLAY_ID) {
			$this->moduleContext->moduleEngine->addListener('Post', function (&$arrLabels, $post) {
				$this->onRenderPost($arrLabels, $post);
			});
		}

		// run the hook point to gen an ID.
		// IDs are generated for every post when the module is enabled
		$this->moduleContext->moduleEngine->addListener('RegistBeforeCommit', function ($name, &$email, &$emailForInsertion, &$sub, &$com, &$category, &$age, $file, $isReply, &$status, $thread, &$poster_hash) {
			$this->onBeforeCommit($poster_hash, $email, $thread);
		});
	}

	private function onRenderPost(array &$arrLabels, array $post): void {
		// bind the poster_hash to the placeholder
		$arrLabels['{$POSTER_HASH}'] = htmlspecialchars($post['poster_hash']);
	}

	private function onBeforeCommit(string &$poster_hash, string $email, array|false $thread): void {
		// get the role level from the session
		$roleLevel = getRoleLevelFromSession();

		// get the thread number for the hash method
		$threadNumber = $this->getThreadNumber($thread);

		// generate the hash for a user's post
		$poster_hash = generatePostHash(
			new IPAddress,
			$threadNumber, 
			$email, 
			$roleLevel,
			$this->getConfig('IDSEED'),
			empty($_POST['formModIdOveride']),
		);
	}

	private function getThreadNumber(array|false $thread): int {
		// if the thread doesn't exist then this means the post is a new thread - so the thread number will be the next post number
		if(!$thread) {
			// return the next post number
			return $this->moduleContext->board->getLastPostNoFromBoard() + 1;
		} 
		// if we're replying to a thread then get the thread number from the thread data
		else {
			// get the thread data
			$threadData = $thread['thread'];

			// get the thread number for the hash
			$threadNumber = $threadData['post_op_number'];
		
			// return threead number
			return $threadNumber;
		}
	}

}