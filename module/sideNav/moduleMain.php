<?php

namespace Kokonotsuba\Modules\sideNav;

require_once __DIR__ . '/sideNavRepository.php';
require_once __DIR__ . '/sideNavRenderer.php';
require_once __DIR__ . '/sideNavActionType.php';

use Kokonotsuba\database\databaseConnection;
use Kokonotsuba\module_classes\abstractModuleMain;
use Kokonotsuba\module_classes\traits\listeners\ModuleHeaderListenerTrait;
use Kokonotsuba\module_classes\traits\listeners\SideNavListenerTrait;

/** The side navigation: a home link, then each group's links, rendered into every page. */
class moduleMain extends abstractModuleMain {
	use ModuleHeaderListenerTrait;
	use SideNavListenerTrait;

	/** Hides the nav before it paints when the reader (or the board default) turned it off. */
	private const HIDE_SCRIPT = '<script>(function(){var v=null;try{v=localStorage.getItem("sidenav")}catch(e){}'
		. 'var d=window.KOKO_DEFAULT_SETTINGS||{};'
		. 'if(v!==null?v==="false":d.sidenav===false)document.body.classList.add("sideNavHidden")})();</script>';

	private sideNavRepository $sideNavRepository;
	private ?string $html = null;

	public function getName(): string {
		return 'Side navigation';
	}

	public function getVersion(): string {
		return 'Koko 2026';
	}

	public function initialize(): void {
		$this->moduleContext->actionLoggerService->registerTypes(sideNavActionType::class);

		$this->sideNavRepository = new sideNavRepository(
			databaseConnection::getInstance(),
			$this->moduleContext->getTableName('SIDE_NAV_GROUP_TABLE'),
			$this->moduleContext->getTableName('SIDE_NAV_LINK_TABLE')
		);

		$this->listenModuleHeader('onModuleHeader');
		$this->listenSideNav('onRenderSideNav');
	}

	private function onModuleHeader(string &$moduleHeader): void {
		$this->includeStylesheet('sideNav.css', $moduleHeader);
		$this->includeScript('sideNav.js', $moduleHeader);
	}

	private function onRenderSideNav(string &$sideNav): void {
		$this->html ??= $this->renderNav();
		$sideNav .= $this->html;
	}

	private function renderNav(): string {
		$renderer = new sideNavRenderer(
			$this->moduleContext->adminPageRenderer,
			$this->moduleContext->boardService->getAllListedBoards() ?? [],
			(string) $this->getConfig('HOME'),
			$this->moduleContext->board->getBoardURL(true) . '?mode=overboard'
		);
		return self::HIDE_SCRIPT . $renderer->render($this->sideNavRepository->getLayout());
	}
}
