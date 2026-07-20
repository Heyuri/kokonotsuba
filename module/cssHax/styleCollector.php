<?php

namespace Kokonotsuba\Modules\cssHax;

/*
* Collects thread styling so it can be written into <head> in one block.
*
* The overboard builds a separate module engine for every board it lists, which
* means several cssHax instances render threads for a single page. They all share
* one collector through the container so nothing is lost before the head is drawn.
*/
class styleCollector {
	private array $styles = [];

	public function add(string $style): void {
		// nothing to collect for an unthemed thread
		if ($style === '') {
			return;
		}

		$this->styles[] = $style;
	}

	public function isEmpty(): bool {
		return empty($this->styles);
	}

	// hand over everything collected so far and start fresh
	public function flush(): string {
		$styles = implode('', $this->styles);

		$this->styles = [];

		return $styles;
	}
}
