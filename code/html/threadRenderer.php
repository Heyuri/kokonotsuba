<?php

/*
* thread html renderer for Kokonotsuba!
* Handles high-level output for threads. The actual html resides in templates/
*/ 

class threadRenderer {
	private globalHTML $globalHTML;
	private templateEngine $templateEngine;
	private postRenderer $postRenderer;


	public function __construct(globalHTML $globalHTML, templateEngine $templateEngine, postRenderer $postRenderer) {
		$this->globalHTML = $globalHTML;
		$this->templateEngine = $templateEngine;

		$this->postRenderer = $postRenderer;
	}

	/**
	 * Main render function to build full HTML of thread and replies.
	 */
	public function render(array $threads,
			bool $isReplyMode,
			array $thread,
			array $posts, 
			int $hiddenReply, 
			string $thread_uid, 
			bool $killSensor,  
			bool $adminMode = false, 
			int $threadIterator = 0, 
			string $overboardBoardTitleHTML = '', 
			string $crossLink = '',
			array $templateValues = []
		): string {
		$thread_uid = $thread['thread_uid'];

		$threadResno = $thread['post_op_number'];

		$thdat = '';
		
		// whether this is reply mode
		$replyMode = $isReplyMode;
		
		// whether this is thread (index) mode
		$threadMode = !$isReplyMode;


		// number of replies
		$replyCount = count($posts);
	
		// render posts for a thread
		foreach ($posts as $i => $post) {
				$thdat .= $this->renderSinglePost($posts,
						$post,
						$i,
						$threadMode,
						$adminMode,
						$threads,
						$threadIterator,
						$hiddenReply,
						$killSensor,
						$threadResno,
						$replyMode,
						$replyCount,
						$overboardBoardTitleHTML,
						$crossLink,
						$templateValues
			);
		}
	
		$thdat .= $this->templateEngine->ParseBlock('THREADSEPARATE', $thread_uid ? array('{$RESTO}' => $threadResno) : array());
		return $thdat;
	}
	
	/*
	* Render an individual post for a thread
	*/
	private function renderSinglePost(
		array $threadPosts,
		array $post,
		int $i,
		bool $threadMode,
		bool $adminMode,
		array $currentPageThreads,
		int $threadIterator,
		int $hiddenReply,
		bool $killSensor,
		int $threadResno,
		bool $replyMode,
		int $replyCount,
		string $overboardBoardTitleHTML,
		string $crossLink,
		array $templateValues,
	): string {
		$isReply = $i > 0;


		$postFormExtra = $warnBeKill = $warnOld = $warnEndReply = $warnHidePost = $threadNav = '';

		// Navigation
		if ($threadMode) {
			$threadNav = $this->globalHTML->buildThreadNavButtons($currentPageThreads, $threadIterator);
		}

		// Hidden reply notice
		if (!$isReply && $hiddenReply) {
			$warnHidePost = '<div class="omittedposts">'._T('notice_omitted', $hiddenReply).'</div>';
		}

		// append board title to thread 
		$templateValues['{$BOARD_THREAD_NAME}'] = $overboardBoardTitleHTML;

		// bind post op number to resto
		if ($replyMode) {
			$templateValues['{$RESTO}'] = $threadResno;
		}
		

		$postHtml = $this->postRenderer->render($post,
			$templateValues,
			$threadResno,
			$killSensor,
			$threadPosts,
			$adminMode,
			$postFormExtra,
			$warnBeKill,
			$warnOld,
			$warnHidePost,
			$warnEndReply,
			$threadNav,
			$replyCount,
			$threadMode,
			$crossLink
		);

		return $postHtml;
	}

}