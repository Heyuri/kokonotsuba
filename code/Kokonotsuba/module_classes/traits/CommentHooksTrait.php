<?php

namespace Kokonotsuba\module_classes\traits;

use Kokonotsuba\module_classes\moduleEngine;

use function Kokonotsuba\libraries\html\quote_unkfunc;

/**
 * Shared comment-processing pipeline: dispatches PostComment hooks
 * and applies greentext quoting.
 */
trait CommentHooksTrait {
	abstract protected function getModuleEngine(): moduleEngine;

	protected function applyCommentHooks(string $comment): string {
		$this->getModuleEngine()->dispatch('PostComment', [&$comment]);
		$comment = quote_unkfunc($comment);
		return $comment;
	}
}
