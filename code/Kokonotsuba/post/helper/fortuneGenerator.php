<?php

namespace Kokonotsuba\post\helper;

use Kokonotsuba\post\commentMarker;

class fortuneGenerator {
	private readonly array $fortunes;

	public function __construct(array $fortunes) {
		$this->fortunes = $fortunes;
	}

	/**
	 * Draw a fortune and append its marker to the comment.
	 *
	 * Only the index is stored, so the comment stays plain text; commentFormatter turns the
	 * marker into the coloured line at render time.
	 */
	public function apply(string &$com): void {
		if (empty($this->fortunes)) {
			return;
		}

		$com .= "\n" . commentMarker::make('fortune', (string)array_rand($this->fortunes));
	}
}
