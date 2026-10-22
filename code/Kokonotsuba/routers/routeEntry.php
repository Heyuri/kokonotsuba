<?php

namespace Kokonotsuba\routers;

use Closure;

/**
 * A routeTable row: how to build the route and what to check or record around it.
 */
final class routeEntry {
	/**
	 * @param Closure(\Kokonotsuba\containers\appContainer): route $factory
	 * @param csrfRule    $csrf    Checked by modeHandler before the route is built.
	 * @param string|null $profile Excimer profiler category, or null when the mode is not profiled.
	 */
	public function __construct(
		public readonly Closure $factory,
		public readonly csrfRule $csrf = csrfRule::NONE,
		public readonly ?string $profile = null,
	) {}
}
