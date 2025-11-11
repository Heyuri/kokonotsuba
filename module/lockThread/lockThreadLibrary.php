<?php

namespace Kokonotsuba\Modules\lockThread;

function getLockIndicator(string $staticUrl): string {
	// assemble lock icon url
	$lockIconUrl = $staticUrl . 'image/locked.png';

	// assemble lock html
	$lockIconUrl = '<img src="' . $lockIconUrl . '" class="icon lockIcon" height="16" width="16" title="Locked thread">';

	// return lock html
	return $lockIconUrl;
}