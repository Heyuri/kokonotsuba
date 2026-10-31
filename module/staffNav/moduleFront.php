<?php

namespace Kokonotsuba\Modules\staffNav;

require_once __DIR__ . '/staffNavRenderer.php';

use Kokonotsuba\module_classes\abstractModuleFront;
use Kokonotsuba\userRole;

use function Kokonotsuba\libraries\html\getFrontAdminNavEntries;
use function Kokonotsuba\libraries\html\getStaffNavCoreEntries;

/**
 * The staff nav on the front page: the built-in modes aimed at the first listed board and the
 * front page group (News / Pages / Logo) that board staff navs carry too, then whatever answers
 * FrontStaffNavLinks. Staff only, so visitors' cached pages never carry it.
 */
class moduleFront extends abstractModuleFront {
	public function initialize(): void {
		$this->listenStaff('FrontHeader', function (string &$headHtml): void {
			$this->includeStylesheet('staffNav.css');
			$headHtml .= $this->scriptTag('staffNav.js?v=11');
		});

		$this->listenStaff('FrontTopNav', function (string &$topNav): void {
			$topNav .= $this->renderBar();
		});
	}

	private function renderBar(): string {
		$frontEntries = getFrontAdminNavEntries($this->context->container->get('frontPagePolicy'), $this->context->links);

		$boardUrl = $this->firstBoardUrl();
		$entries = $boardUrl === '' ? $frontEntries : getStaffNavCoreEntries(
			$boardUrl,
			$this->getConfig('AuthLevels.CAN_VIEW_ACTION_LOG', userRole::LEV_MODERATOR),
			frontAdminEntries: $frontEntries
		);

		$this->context->moduleEngine->dispatch('FrontStaffNavLinks', [&$entries]);

		return (new staffNavRenderer($this->context->templateEngine))->render($entries, $this->context->staffAccountFromSession);
	}
}
