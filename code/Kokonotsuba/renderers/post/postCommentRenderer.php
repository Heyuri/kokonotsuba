<?php

namespace Kokonotsuba\renderers\post;

use Kokonotsuba\interfaces\IBoard;
use Kokonotsuba\renderers\commentFormatter;

use function Kokonotsuba\libraries\html\generateQuoteLinkHtml;
use function Kokonotsuba\libraries\html\quote_unkfunc;

/**
 * Turns a post's stored comment into html, then layers the quote links and greentext on top.
 *
 * The formatter runs first because everything after it, and every PostComment listener,
 * expects html: quote markers as &gt;&gt; and line breaks as <br>.
 */
final class postCommentRenderer {
	/** @param array $quoteLinks Quote links for the posts about to be drawn, keyed as the quote link service returns them. */
	public function __construct(
		private readonly IBoard $board,
		private readonly commentFormatter $formatter,
		private array $quoteLinks,
	) {}

	public function setQuoteLinks(array $quoteLinks): void {
		$this->quoteLinks = $quoteLinks;
	}

	/**
	 * Rewrite the post's comment in place, since the listeners that run later read it off the post.
	 */
	public function apply(postRenderContext $ctx): void {
		$post = $ctx->post;

		$post->setComment($this->formatter->commentToHtml($post->getComment(), $post->getTextFormat()));

		$post->setComment(generateQuoteLinkHtml(
			$this->quoteLinks,
			$post,
			$ctx->threadResno,
			(bool)$this->board->getConfigValue('USE_QUOTESYSTEM'),
			$this->board,
			$ctx->repliesPerPage
		));

		$post->setComment(quote_unkfunc($post->getComment()));
	}
}
