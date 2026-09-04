<?php

namespace Kokonotsuba\renderers\post;

use Kokonotsuba\module_classes\moduleEngine;

/**
 * Collects the entries modules put on a post's dropdown menu.
 *
 * Each hook is handed an empty array and appends buildWidgetEntry() shapes to it. The thread
 * hooks also get the other posts and the thread row; the Moderate* hooks are only dispatched
 * for staff, so they never reach static html.
 */
final class postWidget {
	public function __construct(
		private readonly moduleEngine $moduleEngine,
	) {}

	/** Staff entries: the thread's or reply's moderation menu, then the ones every post gets. */
	public function addModerateWidgets(postMenu $menu, postRenderContext $ctx): void {
		$this->addEntries($menu, $ctx, 'ModerateThreadWidget', 'ModerateReplyWidget', 'ModeratePostWidget');
	}

	/** Everyone's entries, in the same order as the moderation ones. */
	public function addWidgets(postMenu $menu, postRenderContext $ctx): void {
		$this->addEntries($menu, $ctx, 'ThreadWidget', 'ReplyWidget', 'PostWidget');
	}

	private function addEntries(postMenu $menu, postRenderContext $ctx, string $threadHook, string $replyHook, string $postHook): void {
		if ($ctx->isOp) {
			$menu->append($this->collect($threadHook, [$ctx->post, $ctx->threadPosts, $ctx->thread]));
		} else {
			$menu->append($this->collect($replyHook, [$ctx->post]));
		}

		$menu->append($this->collect($postHook, [$ctx->post]));
	}

	/**
	 * Dispatch a widget hook and return what the listeners appended.
	 *
	 * Listeners declare their parameters by reference, and call_user_func_array() warns when
	 * handed a plain value for one, so every argument is passed as a reference.
	 */
	private function collect(string $hook, array $args): array {
		$widgets = [];
		$parameters = [&$widgets];

		foreach (array_keys($args) as $key) {
			$parameters[] = &$args[$key];
		}

		$this->moduleEngine->dispatch($hook, $parameters);

		return $widgets;
	}
}
