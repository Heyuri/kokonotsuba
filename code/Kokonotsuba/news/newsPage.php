<?php

namespace Kokonotsuba\news;

/** One page of news entries and where it sits among the rest. */
final class newsPage {
	/** @param newsEntry[] $entries */
	public function __construct(
		public readonly array $entries,
		public readonly int $page,
		public readonly int $pageCount,
		public readonly int $total,
	) {}
}
