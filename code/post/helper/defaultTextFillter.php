<?php

class defaultTextFiller {
	private array $config;

	public function __construct(array $config) {
		$this->config = $config;
	}

	public function fill(?string &$sub, ?string &$com): void {
		if (!$sub || preg_match("/^[ |　|]*$/", $sub)) {
			$sub = $this->config['DEFAULT_NOTITLE'];
		}
		if (!$com || preg_match("/^[ |　|\t]*$/", $com)) {
			$com = $this->config['DEFAULT_NOCOMMENT'];
		}
	}
}
