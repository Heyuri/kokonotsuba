<?php

namespace Kokonotsuba\Modules\indexCommentTruncator;

use board;
use Kokonotsuba\ModuleClasses\abstractModuleMain;
use Throwable;

class moduleMain extends abstractModuleMain {
	// post comments over these limits are truncated (if viewed from the index)
	private int $characterPreviewLimit;
	private int $breakLinePreviewLimit;

	public function getName(): string {
		return 'Index comment truncator';
	}

	public function getVersion(): string {
		return 'Version 9001.';
	}

	public function initialize(): void {
		// get character preview limit config
		$this->characterPreviewLimit = $this->getConfig('ModuleSettings.CHARACTER_PREVIEW_LIMIT', 2500);
		
		// get line preview limit config
		$this->breakLinePreviewLimit = $this->getConfig('ModuleSettings.LINE_PREVIEW_LIMIT', 10);

		// add hook point listener for post
		$this->moduleContext->moduleEngine->addListener('Post', function(array &$templateValues, array &$post, array &$threadPosts, board &$board, bool &$adminMode) {
			$this->onRenderPost($templateValues['{$COM}'], $post);
		});
	}

	private function onRenderPost(string &$comment, array &$post): void {
		// truncate post comment for index view
		$this->truncatePostComment($comment, $post['no']);
	}

	private function truncatePostComment(string &$comment, int $postNumber): void {
		// return early if "res" is set in GET request
		// ("res" = 'response')
		// its only set when viewing/targetting a thread.
		// We don't want truncated comments while viewing the thread
		if(isset($_GET['res'])) {
			return;
		}
		
		// return early if the comment is empty because theres no comment/html to truncate
		if(empty($comment)) {
			return;
		}

		// Init copy of comment to be used during truncating
		// comment gets set to it if nothing fails
		$truncatedComment = $comment;

		// this flag exists to be checked later in the method
		// the check appends the message to the post
		$commentTooLong = false;

		// html entity decode to check character length.
		// this is to keep the count accurate to the amount of characters in the comment
		// otherwise, html entities get included in the count - which isn't accurate to the amount of characters rendered
		$rawComment = html_entity_decode($comment);

		// get the raw comment character length for check
		$commentLength = mb_strlen($rawComment);
		try {
			// if its over the character limit - truncate comment and set flag to true
			if($commentLength > $this->characterPreviewLimit) {
				// flag as too long
				$commentTooLong = true;

				// truncate comment
				$truncatedComment = truncateHtml($comment, $this->characterPreviewLimit);
			}

			// get the amount of break lines in the post
			$commentBreakLineCount = countHtmlLineBreaks($comment);

			// if its over the line limit - truncate comment line size and set flag to true
			if($commentBreakLineCount > $this->breakLinePreviewLimit) {
				// flag as too long
				$commentTooLong = true;

				// truncate comment past the line limit
				$truncatedComment = truncateHtmlByLineBreak($comment, $this->breakLinePreviewLimit - 1);
			}
		} 
		// If a throwable exception is caught then just end execution of the method
		catch (Throwable) {
			return;
		}

		// All successful
		// truncate!
		$comment = $truncatedComment;

		// if either condition was triggered then append the 'comment too long' message
		if($commentTooLong) {
			// append the message
			$this->appendLimitMessage($comment, $postNumber);
		}
	}

	private function appendLimitMessage(string &$comment, int $postNumber): void {
		// generate the post anchor tag in the thread
		$postAnchor = $this->generatePostAnchor($postNumber);
		
		// get message text from language loader
		$warning = _T('comment_too_long', $postAnchor);

		// then append to comment with 2 break lines
		$comment .= "<br><br>$warning";
	}

	private function generatePostAnchor(int $postNumber): string {
		// get post url
		$postUrl = $this->moduleContext->board->getBoardThreadURL($postNumber);

		// build anchor tag
		$postAnchor = '<a href="' . htmlspecialchars($postUrl) . '">No.' . htmlspecialchars($postNumber) . ' </a>';

		// return
		return $postAnchor;
	}
}
	