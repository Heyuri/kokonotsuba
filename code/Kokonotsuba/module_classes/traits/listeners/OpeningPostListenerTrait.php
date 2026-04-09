<?php

namespace Kokonotsuba\module_classes\traits\listeners;

use Kokonotsuba\post\Post;

trait OpeningPostListenerTrait {
	protected function listenOpeningPost(string $methodName, int $priority = 0): void {
		$this->moduleContext->moduleEngine->addListener('OpeningPost',
			function(array &$templateValues, Post &$post, array &$threadPosts) use ($methodName) {
				$this->$methodName($templateValues, $post, $threadPosts);
			},
			$priority
		);
	}

	/**
	 * Register an indicator icon on opening posts that shows/hides based on a condition.
	 *
	 * @param string   $indicatorClass  CSS class suffix (e.g. 'lock', 'sticky', 'autosage')
	 * @param string   $innerHtml       The indicator HTML content (icon/text)
	 * @param callable(Post): bool $isActiveCheck  Returns true when the indicator should be visible.
	 */
	protected function registerOpeningPostIndicator(string $indicatorClass, string $innerHtml, callable $isActiveCheck, int $priority = 0): void {
		$this->moduleContext->moduleEngine->addListener('OpeningPost',
			function(array &$templateValues, Post &$post) use ($indicatorClass, $innerHtml, $isActiveCheck) {
				$hiddenClass = $isActiveCheck($post) ? '' : ' indicatorHidden';
				$templateValues['{$POSTINFO_EXTRA}'] .= '<span class="indicator indicator-' . $indicatorClass . $hiddenClass . '">' . $innerHtml . '</span>';
			},
			$priority
		);
	}
}
