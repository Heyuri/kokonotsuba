<?php

namespace Kokonotsuba\module_classes\traits\listeners;

/**
 * Content between the post filter form and the results, on the pages that list posts from every
 * board (the manage-posts table and the recent-posts feed).
 *
 * $filteredIp is the address or wildcard range the page is filtered on, and $filteredTokenHash
 * the browser token hash, either empty when that filter is not set. $canViewIp says whether the
 * reader may be shown them.
 */
trait ManagePostsHostPanelListenerTrait {
	protected function listenManagePostsHostPanel(string $methodName, int $priority = 0): void {
		$this->moduleContext->moduleEngine->addListener('ManagePostsHostPanel',
			function(string &$html, string &$filteredIp, bool $canViewIp, string &$filteredTokenHash) use ($methodName) {
				$this->$methodName($html, $filteredIp, $canViewIp, $filteredTokenHash);
			},
			$priority
		);
	}
}
