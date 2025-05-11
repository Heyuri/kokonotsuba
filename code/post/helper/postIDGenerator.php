<?php


// generate post id for a user/moderator
class postIdGenerator {
	private readonly array $config;
	private readonly mixed $PIO;
    private readonly staffAccountFromSession $staffSession;

	public function __construct(array $config, mixed $PIO, staffAccountFromSession $staffSession) {
		$this->config = $config;
		$this->PIO = $PIO;
        $this->staffSession = $staffSession;
    }

	public function generate($email, $time, $thread_uid) {
		$roleLevel = $this->staffSession->getRoleLevel();

        if ($this->config['DISP_ID']) { // ID display enabled
			if ($roleLevel == \Kokonotsuba\Root\Constants\userRole::LEV_ADMIN && $this->config['DISP_ID'] == 2) {
				return ' ID:ADMIN';
			} elseif ($roleLevel == \Kokonotsuba\Root\Constants\userRole::LEV_MODERATOR && $this->config['DISP_ID'] == 2) {
				return ' ID:MODERATOR';
			} elseif (stristr($email, 'sage') && $this->config['DISP_ID'] == 2) {
				return ' ID:Heaven';
			} else {
				$ip = new IPAddress;
				$idSeed = $this->config['IDSEED'];
				$postNo = $thread_uid ? $thread_uid : ($this->PIO->getLastPostNoFromBoard() + 1);
				$baseString = $ip . $idSeed . $postNo;

				switch ($this->config['ID_MODE']) {
					case 0:
						return ' ID:' . substr(crypt(md5($baseString), 'id'), -8);
					case 1:
						$baseString .= gmdate('Ymd', $time + $this->config['TIME_ZONE'] * 60 * 60);
						return ' ID:' . substr(crypt(md5($baseString), 'id'), -8);
				}
			}
		}
		return '';
	}
}
