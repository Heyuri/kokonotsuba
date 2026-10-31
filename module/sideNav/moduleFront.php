<?php

namespace Kokonotsuba\Modules\sideNav;

require_once __DIR__ . '/sideNavRepository.php';
require_once __DIR__ . '/sideNavRenderer.php';

use Kokonotsuba\module_classes\abstractModuleFront;

use function Kokonotsuba\libraries\html\getBoardStyleSet;

/** The board side nav on the front page; its overboard link goes to the first listed board. */
class moduleFront extends abstractModuleFront {
	public function initialize(): void {
		$this->listen('FrontHeader', function (string &$headHtml): void {
			$this->includeStylesheet('sideNav.css');
			$headHtml .= $this->scriptTag('sideNav.js');
		});

		$this->listen('FrontSideNav', function (string &$sideNav): void {
			$sideNav .= $this->renderNav();
		});
	}

	private function renderNav(): string {
		$container = $this->context->container;
		$boardUrl = $this->firstBoardUrl();
		$tableNames = $container->get('tableNames');

		$repository = new sideNavRepository($container->get('databaseConnection'), $tableNames['SIDE_NAV_GROUP_TABLE'], $tableNames['SIDE_NAV_LINK_TABLE']);
		$renderer = new sideNavRenderer(
			$this->context->templateEngine,
			$container->get('boardService')->getAllListedBoards() ?? [],
			$this->context->links->home(),
			$boardUrl === '' ? '' : $boardUrl . '?mode=overboard',
			array_keys(getBoardStyleSet($this->context->siteConfig)[1])
		);

		return $renderer->renderForPage($repository->getLayout());
	}
}
