<?php

namespace Kokonotsuba\renderers;

use Kokonotsuba\interfaces\IBoard;
use Kokonotsuba\module_classes\moduleEngine;
use Kokonotsuba\post\Post;
use Kokonotsuba\renderers\post\postCommentRenderer;
use Kokonotsuba\renderers\post\postElementGenerator;
use Kokonotsuba\renderers\post\postMenu;
use Kokonotsuba\renderers\post\postModuleHooks;
use Kokonotsuba\renderers\post\postRenderContext;
use Kokonotsuba\renderers\post\postTemplateBinder;
use Kokonotsuba\renderers\post\postWarnings;
use Kokonotsuba\renderers\post\postWidget;
use Kokonotsuba\request\request;
use Kokonotsuba\template\templateEngine;
use Kokonotsuba\thread\Thread;

/**
 * Draws one post through the board's OP or REPLY template block.
 *
 * The stages live under renderers/post/ and run in a fixed order: the comment becomes html,
 * staff controls and menu entries are collected, the post's own values are bound, modules get
 * their hooks, then the menu is drawn and the block parsed. A renderer is tied to the board it
 * was built for; boardRendererFactory hands out one per board.
 */
class postRenderer {
	private readonly attachmentRenderer $attachmentRenderer;
	private readonly postCommentRenderer $commentRenderer;
	private readonly postTemplateBinder $binder;
	private readonly postModuleHooks $hooks;
	private readonly postWidget $widgets;

	public function __construct(
		private readonly IBoard $board,
		array $config,
		moduleEngine $moduleEngine,
		private readonly templateEngine $templateEngine,
		array $quoteLinksFromBoard,
		request $request
	) {
		$this->attachmentRenderer = new attachmentRenderer($board, $moduleEngine, $templateEngine);
		$this->commentRenderer = new postCommentRenderer($board, new commentFormatter($config), $quoteLinksFromBoard);
		$this->binder = new postTemplateBinder(
			$board,
			$config,
			$moduleEngine,
			$this->attachmentRenderer,
			new postElementGenerator($board),
			new postWarnings($config, $request->getRequestTime())
		);
		$this->hooks = new postModuleHooks($board, $moduleEngine);
		$this->widgets = new postWidget($moduleEngine);
	}

	/** Quote links for the posts about to be drawn, fetched once per page by the caller. */
	public function setQuoteLinks(array $quoteLinks): void {
		$this->commentRenderer->setQuoteLinks($quoteLinks);
	}

	/**
	 * Render a post to html.
	 *
	 * $templateValues is taken by reference: the values bound here are left in it for the caller,
	 * which threadRenderer relies on when it walks a thread.
	 *
	 * @param Post    $post           Post to draw. Its comment is rewritten to html in place.
	 * @param array   $templateValues Placeholders already bound by the caller (thread-level ones).
	 * @param int     $threadResno    Number of the thread the post is linked back to.
	 * @param bool    $killSensor     The thread is about to fall off the board.
	 * @param Post[]  $threadPosts    The other posts drawn with it, for listeners that need them.
	 * @param bool    $adminMode      Draw the staff-only controls and widgets.
	 * @param string  $postFormExtra  Html appended to the post info line.
	 * @param string  $warnHidePost   The omitted-replies notice, on the OP only.
	 * @param int     $replyCount     Replies the thread holds.
	 * @param bool    $threadMode     True in a board index listing (truncated), false in a thread view.
	 * @param string  $crossLink      Board url prefix when linking to another board.
	 * @param bool    $renderAsOp     Draw a reply through the OP block so it stands on its own.
	 * @param ?Thread $thread         Thread row, when the render path has one to hand.
	 */
	public function render(
		Post $post,
		array &$templateValues,
		int $threadResno,
		bool $killSensor,
		array $threadPosts,
		bool $adminMode,
		string $postFormExtra,
		string $warnHidePost,
		int $replyCount,
		bool $threadMode = true,
		string $crossLink = '',
		bool $renderAsOp = false,
		?Thread $thread = null
	): string {
		// Bundle the arguments so every stage below reads the same facts about this render
		$ctx = new postRenderContext(
			post: $post,
			threadResno: $threadResno,
			threadPosts: $threadPosts,
			adminMode: $adminMode,
			replyCount: $replyCount,
			threadMode: $threadMode,
			crossLink: $crossLink,
			killSensor: $killSensor,
			renderAsOp: $renderAsOp,
			thread: $thread,
			repliesPerPage: (int)$this->board->getConfigValue('REPLIES_PER_PAGE', 200),
		);

		// Stored comment to html (escaping, links, quote links, quotes), rewritten on the post
		// itself because the PostComment listeners read it from there
		$this->commentRenderer->apply($ctx);

		// The dropdown menu. Staff entries are collected first so they lead the menu; everyone's
		// entries are added after the hooks below have run
		$menu = new postMenu();
		if ($ctx->adminMode) {
			$this->widgets->addModerateWidgets($menu, $ctx);
		}

		// Staff controls (delete, ban, ...) appended to the post info line
		$postFormExtra .= $this->hooks->adminControls($ctx);

		// Everything drawn from the post row itself: name, subject, tag, links, attachments, warnings
		$templateValues = $this->binder->bind($templateValues, $ctx, $postFormExtra, $warnHidePost);

		// Modules rework the bound values (Post, PostComment, BelowComment, css classes, OpeningPost/ThreadReply)
		$this->hooks->dispatch($templateValues, $ctx);

		// Finish the menu with the entries every viewer gets, then draw it
		$this->widgets->addWidgets($menu, $ctx);
		$templateValues['{$POST_MENU}'] = $menu->toHtml();

		// A reply drawn on its own (search hits, previews) borrows the OP block
		return $this->templateEngine->ParseBlock($ctx->usesOpBlock() ? 'OP' : 'REPLY', $templateValues);
	}

	/**
	 * Html for a set of attachment rows, as the post's own attachments are drawn.
	 *
	 * @param array $attachments Attachment rows.
	 * @param bool  $isDeleted   The post is a deleted one shown to staff.
	 * @param bool  $adminMode   The viewer is staff.
	 */
	public function processAttachments(array $attachments, bool $isDeleted, bool $adminMode): string {
		return $this->attachmentRenderer->renderAttachments($attachments, $isDeleted, $adminMode);
	}
}
