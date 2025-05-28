<?php

/*
* thread html renderer for Kokonotsuba!
* Handles high-level output for threads. The actual html resides in templates/
*/ 

class threadRenderer {
	private array $config;
	private globalHTML $globalHTML;
	private templateEngine $templateEngine;
	private postRenderer $postRenderer;


	public function __construct(array $config, globalHTML $globalHTML, templateEngine $templateEngine, postRenderer $postRenderer) {
		$this->config = $config;
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
			bool $killSensor,  
			bool $adminMode = false, 
			int $threadIterator = 0, 
			string $overboardBoardTitleHTML = '', 
			string $crossLink = '',
			array $templateValues = []
		): string {

		$threadResno = $thread['post_op_number'];
		
		// whether this is reply mode
		$replyMode = $isReplyMode;
		
		// whether this is thread (index) mode
		$threadMode = !$isReplyMode;


		// number of replies
		$replyCount = count($posts);
	
		$threadHtml = '';

		$templateValues['{$REPLIES}'] = '';

		$templateValues['{$THREAD_OP}'] = '';

		$templateValues = $this->getThreadPlaceholders($thread, $templateValues);
		
		// render posts for a thread
		foreach ($posts as $i => $post) {
			$postHtml = $this->renderSinglePost($posts,
						$post,
						$i,
						$threadMode,
						$adminMode,
						$hiddenReply,
						$killSensor,
						$threadResno,
						$replyMode,
						$replyCount,
						$crossLink,
						$templateValues
			);
			if($i === 0) {
				$templateValues['{$THREAD_OP}'] = $postHtml;
			} else {
				$templateValues['{$REPLIES}'] .= $postHtml;
			}
		}
		
		// append board title to thread 
		$templateValues['{$BOARD_THREAD_NAME}'] = $overboardBoardTitleHTML;

		$templateValues['{$THREAD_NO}'] = $threadResno;

		$templateValues['{$THREADNAV}'] = '';

		// Navigation
		if ($threadMode) {
			$templateValues['{$THREADNAV}'] = $this->globalHTML->buildThreadNavButtons($threads, $threadIterator);
		}

		$threadHtml .= $this->templateEngine->ParseBlock('THREAD', $templateValues);
		$threadHtml .= $this->templateEngine->ParseBlock('THREADSEPARATE', []);
		return $threadHtml;
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
		int $hiddenReply,
		bool $killSensor,
		int $threadResno,
		bool $replyMode,
		int $replyCount,
		string $crossLink,
		array &$templateValues,
	): string {
		$isReply = $i > 0;

		$postFormExtra = $warnBeKill = $warnOld = $warnEndReply = $warnHidePost = '';

		// Hidden reply notice
		if (!$isReply && $hiddenReply) {
			$warnHidePost = '<div class="omittedposts">'._T('notice_omitted', $hiddenReply).'</div>';
		}

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
			$replyCount,
			$threadMode,
			$crossLink
		);

		return $postHtml;
	}


	private function getThreadPlaceholders(array $thread, array $templateValues): array {
		$threadUid = $thread['thread_uid'];
		$postOpNumber = $thread['post_op_number'];
		$postOpPostUid = $thread['post_op_post_uid'];
		$boardUid = $thread['boardUID'];
		$lastReplyTime = $thread['last_reply_time'];
		$lastBumpTime = $thread['last_bump_time'];
		$threadCreatedTime = $thread['thread_created_time'];

		// format thread created time
		$postDateFormatter = new postDateFormatter($this->config);
		$formattedThreadCreatedTime = $postDateFormatter->formatFromDateString($threadCreatedTime);

		$threadPlaceholders = bindThreadValuesToTemplate($threadUid, 
			$postOpNumber, 
			$postOpPostUid, 
			$boardUid, 
			$lastReplyTime, 
			$lastBumpTime, 
			$threadCreatedTime,
			$formattedThreadCreatedTime);
		
		return array_merge($templateValues, $threadPlaceholders);
	}
}