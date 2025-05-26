<?php

class agingHandler {
	private readonly array $config;
	private readonly mixed $threadSingleton;

	public function __construct(array $config, mixed $threadSingleton) {
		$this->config = $config;
		$this->threadSingleton = $threadSingleton;
	}

	public function apply(string $thread_uid, int $unixTime, string $postOpRoot, string &$email, bool &$age): void {
		if (!$thread_uid) return;

		if (
			$this->threadSingleton->getPostCountFromThread($thread_uid) <= $this->config['MAX_RES']
			|| $this->config['MAX_RES'] == 0
		) {
			$postOpUnixTimestamp = strtotime($postOpRoot);
			if (
				!$this->config['MAX_AGE_TIME']
				|| (($unixTime - $postOpUnixTimestamp) < ($this->config['MAX_AGE_TIME'] * 60 * 60))
			) {
				$age = true;
			}
		}

		if ($this->config['NOTICE_SAGE'] && stristr($email, 'sage')) {
			$age = false;

		}
	}
}
