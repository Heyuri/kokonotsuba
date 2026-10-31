<?php

namespace Kokonotsuba\module_classes;

use Kokonotsuba\account\staffAccountFromSession;
use Kokonotsuba\containers\appContainer;
use Kokonotsuba\front_pages\frontPageLinks;
use Kokonotsuba\template\templateEngine;

/** What a moduleFront gets to work with. There is no board: config is the site (global) config. */
final class frontModuleContext {
	public function __construct(
		public readonly appContainer $container,
		public readonly templateEngine $templateEngine,
		public readonly frontPageLinks $links,
		public readonly array $siteConfig,
		public readonly staffAccountFromSession $staffAccountFromSession,
		public readonly frontModuleEngine $moduleEngine,
	) {}
}
