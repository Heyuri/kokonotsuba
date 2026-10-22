<?php

namespace Kokonotsuba\routers;

/**
 * One koko.php mode. Built per request from its routeTable entry, then handled once.
 */
interface route {
	public function handle(): void;
}
