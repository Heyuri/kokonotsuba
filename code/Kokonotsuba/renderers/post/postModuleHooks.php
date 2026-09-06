<?php

namespace Kokonotsuba\renderers\post;

use Kokonotsuba\interfaces\IBoard;
use Kokonotsuba\module_classes\moduleEngine;

/**
 * The hook points a post render offers modules, in the order they fire.
 *
 * Arguments are passed as references because listeners declare them that way and
 * call_user_func_array() warns when handed a plain value for a by-reference parameter.
 */
final class postModuleHooks {
	public function __construct(
		private readonly IBoard $board,
		private readonly moduleEngine $moduleEngine,
	) {}

	/** Staff controls for the post info line: the thread's or reply's, then every post's. */
	public function adminControls(postRenderContext $ctx): string {
		if (!$ctx->adminMode) {
			return '';
		}

		$post = $ctx->post;
		$html = '';

		$this->moduleEngine->dispatch($ctx->isOp ? 'ThreadAdminControls' : 'ReplyAdminControls', [&$html, &$post]);
		$this->moduleEngine->dispatch('PostAdminControls', [&$html, &$post]);

		return $html;
	}

	/**
	 * Let modules rework the bound template values before the block is parsed.
	 *
	 * Post sees everything; PostComment gets the comment html alone; BelowComment and the
	 * css-class hooks fill placeholders of their own; ThreadReply or OpeningPost fires last.
	 */
	public function dispatch(array &$templateValues, postRenderContext $ctx): void {
		$board = $this->board;
		$post = $ctx->post;
		$threadPosts = $ctx->threadPosts;
		$adminMode = $ctx->adminMode;

		$this->moduleEngine->dispatch('Post', [&$templateValues, &$post, &$threadPosts, &$board, &$adminMode]);

		// The flag tells listeners this is a full thread view rather than a listing;
		// the comment truncator uses it to skip truncation inside threads.
		$this->moduleEngine->dispatch('PostComment', [&$templateValues['{$COM}'], &$post, $ctx->isThreadView()]);

		$templateValues['{$BELOW_COMMENT}'] = '';
		$this->moduleEngine->dispatch('BelowComment', [&$templateValues['{$BELOW_COMMENT}'], &$post, &$threadPosts, &$adminMode]);

		$templateValues['{$MODULE_ATTACHMENT_CSS_CLASSES}'] = '';
		$this->moduleEngine->dispatch('AttachmentCssClass', [&$templateValues['{$MODULE_ATTACHMENT_CSS_CLASSES}'], &$post, &$adminMode]);

		$templateValues['{$MODULE_POST_CSS_CLASSES}'] = '';
		$this->moduleEngine->dispatch('PostCssClass', [&$templateValues['{$MODULE_POST_CSS_CLASSES}'], &$post]);

		if ($ctx->isOp) {
			// The thread row rides along so listeners can read thread-level state (sticky, theme,
			// counts) without a lookup per opening post. Null on render paths that have no thread
			// to hand, such as search hits and API output.
			$this->moduleEngine->dispatch('OpeningPost', [&$templateValues, &$post, &$threadPosts, $ctx->thread]);
		} else {
			$this->moduleEngine->dispatch('ThreadReply', [&$templateValues, &$post, &$threadPosts]);
		}
	}
}
