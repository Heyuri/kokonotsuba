<?php

class postDateFormatter {
	private readonly array $config;

	public function __construct(array $config) {
		$this->config = $config;
	}

	public function format(int $time): string {
		$youbi = [_T('sun'), _T('mon'), _T('tue'), _T('wed'), _T('thu'), _T('fri'), _T('sat')];
		$offset = $this->config['TIME_ZONE'] * 60 * 60;
		$weekday = $youbi[gmdate('w', $time + $offset)];

		return '<span class="postDate">' . gmdate('Y/m/d', $time + $offset) . '</span>'
			. '<span class="postDay">(' . $weekday . ')</span>'
			. '<span class="postTime">' . gmdate('H:i:s', $time + $offset) . '</span>';
	}
}
