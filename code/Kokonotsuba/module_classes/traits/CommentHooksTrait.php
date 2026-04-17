<?php

namespace Kokonotsuba\module_classes\traits;

use Kokonotsuba\module_classes\moduleEngine;

use function Kokonotsuba\libraries\html\quote_unkfunc;
use function Puchiko\strings\autoLink;

/**
 * Shared comment-processing pipeline: dispatches PostComment hooks,
 * applies greentext quoting, and auto-links URLs.
 */
trait CommentHooksTrait {
	abstract protected function getModuleEngine(): moduleEngine;

	protected function applyCommentHooks(string $comment): string {
		$this->getModuleEngine()->dispatch('PostComment', [&$comment]);
		$comment = quote_unkfunc($comment);
		$comment = autoLink($comment);
		return $comment;
	}
}
