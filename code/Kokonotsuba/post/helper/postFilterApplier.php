<?php

namespace Kokonotsuba\post\helper;

use Kokonotsuba\post\helper\fortuneGenerator;
use function Puchiko\strings\autoLink;

class postFilterApplier {
	private readonly array $config;
	private readonly fortuneGenerator $fortune;

	public function __construct(array $config, fortuneGenerator $fortunGenerator) {
		$this->config = $config;
		$this->fortune = $fortunGenerator;
	}

	public function applyFilters(string &$com, string &$email): void {
		if ($this->config['AUTO_LINK']) {
			$com = autoLink($com, $this->config['REF_URL']);
		}

		if ($this->config['FORTUNES'] && stristr($email, 'fortune')) {
			$this->fortune->apply($com);
		}
	}
}
