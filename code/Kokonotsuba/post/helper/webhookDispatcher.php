<?php

namespace Kokonotsuba\post\helper;

use Kokonotsuba\board\board;

class webhookDispatcher {
    private board $board;
	private readonly array $config;
    

	public function __construct(board $board, array $config) {
		$this->board = $board;
        $this->config = $config;
	}

	public function dispatch(int $threadNumber, int $no): void {
		$url = $this->board->getBoardURL() . $this->config['LIVE_INDEX_FILE'] . "?res=" . ($threadNumber ? $threadNumber : $no);
		$msg = ($threadNumber ? 'New post' : 'New thread');

		$this->sendWebhook($this->config['IRC_WH'], $msg . " <$url#p$no>", true);
		$this->sendWebhook($this->config['DISCORD_WH'], $msg . " <$url#p$no>", false);
	}

	private function sendWebhook(?string $url, string $content, bool $isSSL): void {
		if (empty($url)) return;

		// Validate URL scheme
		$scheme = parse_url($url, PHP_URL_SCHEME);
		if (!in_array($scheme, ['http', 'https'], true)) return;

		// Block requests to private/reserved IP ranges (SSRF protection)
		$host = parse_url($url, PHP_URL_HOST);
		if ($host === false || $host === null) return;
		$ip = gethostbyname($host);
		if ($ip !== false && !filter_var($ip, \FILTER_VALIDATE_IP, \FILTER_FLAG_NO_PRIV_RANGE | \FILTER_FLAG_NO_RES_RANGE)) {
			return;
		}

		$options = [
			'http' => [
				'method' => 'POST',
				'header' => 'content-type:application/x-www-form-urlencoded',
				'content' => http_build_query(['content' => $content]),
				'timeout' => 5
			]
		];

		if ($isSSL) {
			$options['ssl'] = [
				'verify_peer' => true,
				'verify_peer_name' => true
			];
		}

		@file_get_contents($url, false, stream_context_create($options));
	}
}
