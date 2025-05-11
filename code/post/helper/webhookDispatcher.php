<?php

class webhookDispatcher {
    private readonly board $board;
	private readonly array $config;
    

	public function __construct(board $board, array $config) {
		$this->board = $board;
        $this->config = $config;
	}

	public function dispatch(int $threadNumber, int $no): void {
		$url = $this->board->getBoardURL() . $this->config['PHP_SELF'] . "?res=" . ($threadNumber ? $threadNumber : $no);
		$msg = ($threadNumber ? 'New post' : 'New thread');

		$this->sendWebhook($this->config['IRC_WH'], $msg . " <$url#p$no>", true);
		$this->sendWebhook($this->config['DISCORD_WH'], $msg . " <$url#p$no>", false);
	}

	private function sendWebhook(?string $url, string $content, bool $isSSL): void {
		if (empty($url)) return;

		$options = [
			'http' => [
				'method' => 'POST',
				'header' => 'content-type:application/x-www-form-urlencoded',
				'content' => http_build_query(['content' => $content])
			]
		];

		if ($isSSL) {
			$options['ssl'] = [
				'verify_peer' => false,
				'verify_peer_name' => false
			];
		}

		file_get_contents($url, false, stream_context_create($options));
	}
}
