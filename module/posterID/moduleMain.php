<?php

namespace Kokonotsuba\Modules\posterID;

use IPAddress;
use Kokonotsuba\ModuleClasses\abstractModuleMain;

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

		// add the listener for displaying IDs
		$this->moduleContext->moduleEngine->addListener('Post', function (
			&$templateValues,
			&$data,
			&$threadPosts,
			&$board,
			&$adminMode) {
			$this->onRenderPost($templateValues, $data, $threadPosts);
		});

		// run the hook point to gen an ID.
		// IDs are generated for every post when the module is enabled
		$this->moduleContext->moduleEngine->addListener('RegistBeforeCommit', function ($name, &$email, &$emailForInsertion, &$sub, &$com, &$category, &$age, $file, $isReply, &$status, $thread, &$poster_hash) {
			$this->onBeforeCommit($poster_hash, $email, $thread);
		});
	}

	private function onRenderPost(array &$templateValues, array $post, array $threadPosts): void {
		// handle poster base hash binding/rendering
		$this->bindPosterBaseHash($templateValues, $post, $threadPosts);
	}

	private function bindPosterBaseHash(array &$templateValues, array $post, array $threadPosts): void {
		// return early if poster_hash is not set
		if (empty($post['poster_hash'])) {
			return;
		}

		// return early if threadPosts is empty
		if (empty($threadPosts)) {
			return;
		}

		// get the opening post
		$openingPost = $threadPosts[0];

		// check if the thread OP post's email contains 'showid'
		$threadShowId = !empty($openingPost['email']) && str_contains($openingPost['email'], 'showid');

		// return early if displaying IDs is disabled
		// allow if the OP post's email contains 'displayid' to always show the ID
		if ($this->DISPLAY_ID === false && $threadShowId === false) {
			return;
		}

		// bind the poster_hash to the placeholder
		$templateValues['{$POSTER_HASH}'] = htmlspecialchars($post['poster_hash']);

		// get count of posts by this poster hash
		$posterHashCount = $this->getPosterHashCount($threadPosts, $post['poster_hash']);

		// then bind the total amount of posts by that ID
		$templateValues['{$POSTER_HASH_COUNT}'] = htmlspecialchars(_T('poster_hash_count', $posterHashCount, _T($posterHashCount === 1 ? 'post_singular' : 'post_multiple')));
	}

	private function getPosterHashCount(array $threadPosts, string $posterHash): int {
		$count = 0;

		// loop through posts in the thread and count how many times this poster hash appears
		foreach($threadPosts as $post) {
			if($post['poster_hash'] === $posterHash) {
				$count++;
			}
		}

		return $count;
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
			!empty($_POST['formModIdOveride']),
		);
	}

	private function getThreadNumber(array|false $thread): int {
		// if the thread doesn't exist then this means the post is a new thread - so the thread number will be the next post number
		if(!$thread) {
			// return the next post number
			// note: its not incremented by 1 here since it was incremented when the post number was reserved/assigned earlier in regist
			return $this->moduleContext->board->getLastPostNoFromBoard();
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