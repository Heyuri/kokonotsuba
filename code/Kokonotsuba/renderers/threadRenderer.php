<?php

/*
* thread html renderer for Kokonotsuba!
* Handles high-level output for threads. The actual html resides in templates/
*/ 

namespace Kokonotsuba\renderers;

use Kokonotsuba\module_classes\moduleEngine;
use Kokonotsuba\post\Post;
use Kokonotsuba\post\helper\postDateFormatter;
use Kokonotsuba\template\templateEngine;
use Kokonotsuba\thread\Thread;

use function Kokonotsuba\libraries\html\buildThreadNavButtons;
use function Kokonotsuba\libraries\_T;
use function Kokonotsuba\libraries\bindThreadValuesToTemplate;

class threadRenderer {

	public function __construct(
		private array $config, 
		private templateEngine $templateEngine, 
		private postRenderer $postRenderer,
		private moduleEngine $moduleEngine) {}

	/**
	 * Main render function to build full HTML of thread and replies.
	 */
	public function render(array $threadsInPage,
			bool $isReplyMode,
			Thread $thread,
			array $posts, 
			int $hiddenReply, 
			bool $killSensor,  
			bool $adminMode = false, 
			int $threadIterator = 0, 
			string $overboardBoardTitleHTML = '', 
			string $crossLink = '',
			array $templateValues = [],
			int $currentPage = 0,
			int $totalPages = 1,
			?int $recentRepliesCount = null
		): string {

		$threadResno = $thread->getOpNumber();
		
		// whether this is reply mode
		$replyMode = $isReplyMode;
		
		// whether this is thread (index) mode
		$threadMode = !$isReplyMode;


		// number of replies
		// number of posts excluding OP
		$replyCount = $thread->getPostCount() - 1;
	
		$threadHtml = '';

		$templateValues['{$REPLIES}'] = '';

		$templateValues['{$THREAD_OP}'] = '';

		$templateValues = $this->getThreadPlaceholders($thread, $templateValues);
		
		// thread CSS reference
		$threadCss = &$templateValues['{$MODULE_THREAD_CSS_CLASSES}'];

		// thread header reference
		$threadHeader = &$templateValues['{$MODULE_THREAD_HEADER}'];

		// Dispatch post css event
		$this->moduleEngine->dispatch('ThreadCssClass', [
			&$threadCss,
			&$thread
		]);

		// dispatch thread header
		$this->moduleEngine->dispatch('ModuleThreadHeader', [
			&$threadHeader, 
			&$thread
		]);

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
						$templateValues,
						$currentPage,
						$totalPages,
						$recentRepliesCount
			);
			if($i === 0) {
				$templateValues['{$THREAD_OP}'] = $postHtml;
			} else {
				$templateValues['{$REPLIES}'] .= $postHtml;
				$postSeparateHtml = '';
				$this->moduleEngine->dispatch('PostSeparate', [&$postSeparateHtml, $i - 1]);
				$templateValues['{$REPLIES}'] .= $postSeparateHtml;
			}
		}
		
		// append board title to thread 
		$templateValues['{$BOARD_THREAD_NAME}'] = $overboardBoardTitleHTML;

		$templateValues['{$THREAD_NO}'] = $threadResno;

		$templateValues['{$THREADNAV}'] = '';

		// Navigation
		if ($threadMode) {
			$templateValues['{$THREADNAV}'] = buildThreadNavButtons($threadsInPage, $threadIterator);
		}

		$threadHtml .= $this->templateEngine->ParseBlock('THREAD', $templateValues);
		$separateHtml = $this->templateEngine->ParseBlock('THREADSEPARATE', []);
		$this->moduleEngine->dispatch('ThreadSeparate', [&$separateHtml, $threadIterator]);
		$threadHtml .= $separateHtml;
		return $threadHtml;
	}
	
	/*
	* Render an individual post for a thread
	*/
	private function renderSinglePost(
		array $threadPosts,
		Post $post,
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
		int $currentPage = 0,
		int $totalPages = 1,
		?int $recentRepliesCount = null
	): string {
		$isReply = $i > 0;

		$postFormExtra = $warnHidePost = '';

		// Hidden reply notice
		if (!$isReply && $hiddenReply) {
			$warnHidePost = '<div class="omittedposts">'._T('notice_omitted', $hiddenReply).'</div>';
		}

		// Page viewing notice
		if (!$isReply && $recentRepliesCount !== null) {
			$shownReplies = count($threadPosts) - 1;
			$warnHidePost .= '<div class="omittedposts">'._T('notice_viewing_last_posts', $shownReplies, $shownReplies === 1 ? _T('post_singular') : _T('post_multiple')).'</div>';
		} elseif (!$isReply && $totalPages > 1) {
			$warnHidePost .= '<div class="omittedposts">'._T('notice_viewing_page', $currentPage, max(1, $totalPages)).'</div>';
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
			$warnHidePost,
			$replyCount,
			$threadMode,
			$crossLink
		);

		return $postHtml;
	}


	private function getThreadPlaceholders(Thread $thread, array $templateValues): array {
		$threadUid = $thread->getUid();
		$postOpNumber = $thread->getOpNumber();
		$postOpPostUid = $thread->getOpPostUid();
		$boardUid = $thread->getBoardUID();
		$lastReplyTime = $thread->getLastReplyTime();
		$lastBumpTime = $thread->getLastBumpTime();
		$threadCreatedTime = $thread->getCreatedTime();

		// format thread created time
		$postDateFormatter = new postDateFormatter($this->config['TIME_ZONE']);
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