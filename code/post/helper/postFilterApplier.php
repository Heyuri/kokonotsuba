<?php

class postFilterApplier {
	private readonly array $config;
	private readonly globalHTML $globalHTML;
	private readonly fortuneGenerator $fortune;

	public function __construct(array $config, globalHTML $globalHTML, fortuneGenerator $fortunGenerator) {
		$this->config = $config;
		$this->globalHTML = $globalHTML;
		$this->fortune = $fortunGenerator;
	}

	public function applyFilters(string &$com, string &$email): void {
		if ($this->config['AUTO_LINK']) {
			$com = $this->globalHTML->auto_link($com);
		}
		if ($this->config['FORTUNES'] && stristr($email, 'fortune')) {
			$this->fortune->apply($com, $email);
		}
		if ($this->config['ROLL'] && stristr($email, 'roll')) {
			applyRoll($com, $email);
		}
	}
}
