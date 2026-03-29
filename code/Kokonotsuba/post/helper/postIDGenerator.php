<?php

namespace Kokonotsuba\post\helper;

use Kokonotsuba\account\staffAccountFromSession;
use Kokonotsuba\board\board;
use Kokonotsuba\ip\IPAddress;
use Kokonotsuba\request\request;
use Kokonotsuba\userRole;

// generate post id for a user/moderator
class postIdGenerator {
	private readonly array $config;
	private board $board;
    private readonly staffAccountFromSession $staffSession;
    private readonly request $request;

	public function __construct(array $config, board $board, staffAccountFromSession $staffSession, request $request) {
		$this->config = $config;
		$this->board = $board;
        $this->staffSession = $staffSession;
        $this->request = $request;
    }

	public function generate(?string $email, int $time, int $threadNumber): string {
		$roleLevel = $this->staffSession->getRoleLevel();

		if ($roleLevel == userRole::LEV_ADMIN) {
			return ' ADMIN';
		} elseif ($roleLevel == userRole::LEV_MODERATOR) {
			return ' MODERATOR';
		} elseif (stristr($email, 'sage')) {
			return ' Heaven';
		} else {
			$ip = new IPAddress($this->request->getRemoteAddr());
			$idSeed = $this->config['IDSEED'];
			$postNo = $threadNumber ? $threadNumber : ($this->board->getLastPostNoFromBoard() + 1);
			$baseString = $ip . $idSeed . $postNo;

			switch ($this->config['ID_MODE']) {
				case 0:
					return substr(crypt(md5($baseString), 'id'), -8);
				case 1:
					$baseString .= gmdate('Ymd', $time + $this->config['TIME_ZONE'] * 60 * 60);
					return substr(crypt(md5($baseString), 'id'), -8);
			}
		}
		return '';
	}
}
