<?php

namespace Kokonotsuba\Modules\sticky;

function getStickyIndicator(string $staticUrl): string {
	// assemble sticky icon url
	$stickyIconUrl = $staticUrl . 'image/sticky.png';

	// assemble sticky html
	$stickyIconUrl = '<img src="' . $stickyIconUrl . '" class="icon stickyIcon" height="18" width="18" title="Sticky">';

	// return sticky html
	return $stickyIconUrl;
}