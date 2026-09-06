<?php

namespace Kokonotsuba\post\helper;

use Kokonotsuba\post\helper\fortuneGenerator;

class postFilterApplier {
	private readonly array $config;
	private readonly fortuneGenerator $fortune;

	public function __construct(array $config, fortuneGenerator $fortunGenerator) {
		$this->config = $config;
		$this->fortune = $fortunGenerator;
	}

	/**
	 * Apply the post-time filters.
	 *
	 * Autolinking used to happen here and is now done by commentFormatter, so links follow the
	 * board's current AUTO_LINK and REF_URL rather than the ones in force when the post was made.
	 * A fortune has to be drawn once, at post time, so it stays.
	 */
	public function applyFilters(string &$com, string &$email): void {
		if ($this->config['FORTUNES'] && stristr($email, 'fortune')) {
			$this->fortune->apply($com);
		}
	}
}
