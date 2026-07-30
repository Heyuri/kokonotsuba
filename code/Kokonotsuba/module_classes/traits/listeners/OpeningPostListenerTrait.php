<?php

namespace Kokonotsuba\module_classes\traits\listeners;

use Kokonotsuba\post\Post;
use Kokonotsuba\thread\Thread;

trait OpeningPostListenerTrait {
	protected function listenOpeningPost(string $methodName, int $priority = 0): void {
		$this->moduleContext->moduleEngine->addListener('OpeningPost',
			function(array &$templateValues, Post &$post, array &$threadPosts, ?Thread $thread = null) use ($methodName) {
				$this->$methodName($templateValues, $post, $threadPosts, $thread);
			},
			$priority
		);
	}

	/**
	 * Register an indicator icon on opening posts that shows/hides based on a condition.
	 *
	 * @param string   $indicatorClass  CSS class suffix (e.g. 'lock', 'sticky', 'autosage')
	 * @param string   $innerHtml       The indicator HTML content (icon/text)
	 * @param callable(Post, ?Thread): bool $isActiveCheck  Returns true when the indicator should be
	 *                 visible. The Thread is whatever the renderer had to hand, so thread-level
	 *                 state can be read from it instead of looked up once per opening post; it is
	 *                 null where no thread was available. Checks that only need the Post may
	 *                 declare a single parameter and ignore it.
	 */
	protected function registerOpeningPostIndicator(string $indicatorClass, string $innerHtml, callable $isActiveCheck, int $priority = 0): void {
		$this->moduleContext->moduleEngine->addListener('OpeningPost',
			function(array &$templateValues, Post &$post, array &$threadPosts, ?Thread $thread = null) use ($indicatorClass, $innerHtml, $isActiveCheck) {
				$hiddenClass = $isActiveCheck($post, $thread) ? '' : ' indicatorHidden';
				$templateValues['{$POSTINFO_EXTRA}'] .= '<span class="indicator indicator-' . $indicatorClass . $hiddenClass . '">' . $innerHtml . '</span>';
			},
			$priority
		);
	}
}
