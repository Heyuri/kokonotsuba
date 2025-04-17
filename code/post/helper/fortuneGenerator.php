<?php

class fortuneGenerator {
	private readonly array $fortunes;

	public function __construct(array $fortunes) {
		$this->fortunes = $fortunes;
	}

	public function apply(string &$com): void {
		$index = array_rand($this->fortunes);
		$total = count($this->fortunes);
		$color = sprintf(
			"%02x%02x%02x",
			127 + 127 * sin(2 * M_PI * $index / $total),
			127 + 127 * sin(2 * M_PI * $index / $total + 2 / 3 * M_PI),
			127 + 127 * sin(2 * M_PI * $index / $total + 4 / 3 * M_PI)
		);
		$com .= "<p class=\"fortune\" style=\"color: #$color;\">Your fortune: " . $this->fortunes[$index] . "</p>";
	}
}
