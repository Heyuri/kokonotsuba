<?php
/* mod_pm : Personal Messages for Trips (Pre-Alpha) */

class mod_pm extends moduleHelper {
	private $MESG_LOG = '';
	private $MESG_CACHE = '';
	private $myPage;
	private $trips;
	private $lastno;

	public function __construct(moduleEngine $moduleEngine, boardIO $boardIO, pageRenderer $pageRenderer, pageRenderer $adminPageRenderer) {
		parent::__construct($moduleEngine, $boardIO, $pageRenderer, $adminPageRenderer);

		$this->MESG_LOG = $this->config['ModuleSettings']['PM_DIR'] . 'tripmesg.log';
		$this->MESG_CACHE = $this->config['ModuleSettings']['PM_DIR'] . 'tripmesg.cc';

		$this->trips = [];
		$this->myPage = $this->getModulePageURL();
	}

	public function getModuleName() {
		return 'mod_pm';
	}

	public function getModuleVersionInfo() {
		return 'mod_pm : Personal Messages for Trip (Pre-Alpha) (v140606)';
	}

	public function autoHookToplink(&$linkbar, $isReply) {
		$linkbar .= '[<a href="' . $this->myPage . '">Inbox</a>] [<a href="' . $this->myPage . '&amp;action=write">Write PM</a>]' . "\n";
	}

	public function autoHookThreadPost(&$arrLabels, $post, $threadPosts, $isReply) {
		if (!$this->config['ModuleSettings']['APPEND_TRIP_PM_BUTTON_TO_POST']) return;
		if (strpos($post['name'], '◆') === false) return;

		[$trip] = explode('◆', $post['name']);
		$tripSanitized = strip_tags($trip);

		if ($trip) {
			$arrLabels['{$NAME}'] = $post['name'] . '<a href="' . $this->myPage . '&action=write&t=' . $tripSanitized . '" style="text-decoration: overline underline" title="PM">❖</a>';
		}
	}

	public function autoHookThreadReply(&$arrLabels, $post, $threadPosts, $isReply) {
		$this->autoHookThreadPost($arrLabels, $post, $threadPosts, $isReply);
	}

	private function _tripping($str) {
		$salt = preg_replace('/[^\.-z]/', '.', substr($str . 'H.', 1, 2));
		$salt = strtr($salt, ':;<=>?@[\\]^_`', 'ABCDEFGabcdef');
		return substr(crypt($str, $salt), -10);
	}

	private function _loadCache() {
		if ($this->trips) return true;

		if ($logs = @file($this->MESG_CACHE)) {
			$this->lastno = trim($logs[0]);
			$this->trips = unserialize($logs[1]);
			return true;
		}

		return $this->_rebuildCache();
	}

	private function _rebuildCache() {
		$this->trips = [];

		if ($logs = @file($this->MESG_LOG)) {
			if (!$this->lastno && isset($logs[0])) {
				$this->lastno = intval(substr($logs[0], strpos($logs[0], ',')));
			}

			foreach ($logs as $log) {
				list($mno, $trip, $pdate) = explode(',', trim($log));
				if (isset($this->trips[$trip])) {
					$this->trips[$trip]['c']++;
				} else {
					$this->trips[$trip] = ['c' => 1, 'd' => $pdate];
				}
			}

			uasort($this->trips, function ($a, $b) {
				return $b['d'] <=> $a['d'] ?: $a['c'] <=> $b['c'];
			});

			$this->_writeCache();
			return true;
		}

		$this->_writeCache();
		return false;
	}

	private function _writeCache() {
		$this->_write($this->MESG_CACHE, $this->lastno . "\n" . serialize($this->trips));
	}

	private function _write($file, $data) {
		$rp = fopen($file, "w");
		flock($rp, LOCK_EX);
		@fputs($rp, $data);
		flock($rp, LOCK_UN);
		fclose($rp);
		chmod($file, 0666);
	}

	private function _postPM($from, $to, $topic, $mesg) {
		$globalHTML = new globalHTML($this->board);

		if (!preg_match('/^[0-9a-zA-Z\.\/]{10}$/', $to)) {
			$globalHTML->error("Incorrect Tripcode");
		}


		$from = sanitizeStr($from);
		$to = sanitizeStr($to);
		$topic = sanitizeStr($topic);
		$mesg = sanitizeStr($mesg);

		// truncate
		$from = substr($from, 0, $this->config['INPUT_MAX']);
		$to = substr($to, 0, $this->config['INPUT_MAX']);
		$topic = substr($topic, 0, $this->config['INPUT_MAX']);
		$mesg = substr($mesg, 0, $this->config['COMM_MAX']);

		// replace commas
		$from = str_replace(',', '&#44;', $from);
		$to = str_replace(',', '&#44;', $to);
		$topic = str_replace(',', '&#44;', $topic);
		$mesg = str_replace(',', '&#44;', $mesg);

		if (!$from && $this->config['ALLOW_NONAME']) {
			$from = $this->config['DEFAULT_NONAME'];
		}
		if (!$topic) $topic = $this->config['DEFAULT_NOTITLE'];
		if (!$mesg) $globalHTML->error("Please write a message");

		if (preg_match('/(.*?)[#＃](.*)/u', $from, $regs)) {
			$name = $regs[1];
			$cap = strtr($regs[2], ['&amp;' => '&']);
			$trip = $this->_tripping($cap);
			$from = $name . '<span class="postertrip">' . _T('trip_pre') . $trip . "</span>";
		}

		$from = str_replace(_T('admin'), '"' . _T('admin') . '"', $from);
		$from = str_replace(_T('deletor'), '"' . _T('deletor') . '"', $from);
		$from = str_replace('&' . _T('trip_pre'), '&amp;' . _T('trip_pre'), $from);

		$mesg = str_replace(',', '&#44;', $mesg);
		$mesg = str_replace("\n", '<br>', $mesg);

		$this->_loadCache();

		$logs = (++$this->lastno) . ",$to," . time() . ",$from,$topic,$mesg,{$_SERVER['REMOTE_ADDR']},\n" . @file_get_contents($this->MESG_LOG);
		$this->_write($this->MESG_LOG, $logs);

		$this->_rebuildCache();
	}

	private function _getPM($trip) {
		$trip = substr($trip, 1);
		$tripped = $this->_tripping($trip);
		$data = '';

		if ($logs = @file($this->MESG_LOG)) {
			foreach ($logs as $log) {
				list($mno, $totrip, $pdate, $from, $topic, $mesg) = explode(',', trim($log));
				if ($totrip === $tripped) {
					if (!$data) {
						$data = $this->templateEngine->ParseBlock('REALSEPARATE', []) .
							'<form action="' . $this->myPage . '" method="POST">' .
							'<input type="hidden" name="action" value="delete">' .
							'<input type="hidden" name="trip" value="' . $trip . '">';
					}

					$arrLabels = [
						'{$NO}' => $mno,
						'{$SUB}' => $topic,
						'{$NAME}' => $from,
						'{$NOW}' => date('Y-m-d H:i:s', $pdate),
						'{$COM}' => $mesg,
						'{$QUOTEBTN}' => $mno,
						'{$REPLYBTN}' => '',
						'{$IMG_BAR}' => '',
						'{$IMG_SRC}' => '',
						'{$WARN_OLD}' => '',
						'{$WARN_BEKILL}' => '',
						'{$WARN_ENDREPLY}' => '',
						'{$WARN_HIDEPOST}' => '',
						'{$NAME_TEXT}' => _T('post_name'),
						'{$RESTO}' => 1,
						'{$POSTINFO_EXTRA}' => '',
					];

					$data .= $this->templateEngine->ParseBlock('OP', $arrLabels);
					$data .= $this->templateEngine->ParseBlock('THREADSEPARATE', []);
				}
			}
		}

		return $data ?: "No information." . '</form>';
	}

	private function _latestPM() {
		$html = '
<table id="tableLatestPM" class="postlists">
	<caption><h3>Messages in the last 10 days</h3></caption>
	<thead>
		<tr>
			<th>Date sent</th>
			<th>Trip</th>
			<th>Messages</th>
		</tr>
	</thead>
	<tbody>';
		
		$this->_loadCache();

		foreach ($this->trips as $t => $v) {
				if ($v['d'] < time() - 864000) break;

				$html .= '
		<tr>
			<td>' . date('Y-m-d H:i:s', $v['d']) . ($v['d'] > time() - 86400 ? ' <span class="newPM">(new!)</span>' : '') . '</td>
			<td><span class="name">' . _T('trip_pre') . substr($t, 0, 5) . "</span>...</td>
			<td>{$v['c']} " . _T('info_basic_threads') . '</td>
		</tr>';
		}

		$html .= '
	</tbody>
</table>';

		return $html;
}


	private function _deletePM($no, $trip) {
		$tripped = $this->_tripping($trip);
		$found = false;

		if ($logs = @file($this->MESG_LOG)) {
			foreach ($no as $n) {
				foreach ($logs as $i => $log) {
					list($mno, $totrip) = explode(',', $log);
					if ($totrip == $tripped && $mno == $n) {
						$logs[$i] = '';
						$found = true;
						break;
					}
				}
			}

			if ($found) {
				$this->_write($this->MESG_LOG, implode('', $logs));
				$this->_rebuildCache();
			}
		}
	}

	public function ModulePage() {
		$globalHTML = new globalHTML($this->board);
		$trip = $_REQUEST['t'] ?? '';
		$action = $_REQUEST['action'] ?? '';
		$dat = '';

		$globalHTML->head($dat);

		switch ($action) {
				case 'write':

						$dat.= '
[<a href="'.$this->config['PHP_SELF2'].'?'.$_SERVER['REQUEST_TIME'].'">Return</a>]
<div id="PMContainer">
	<h2 class="theading2">Send PM</h2>
	<div class="postformTable">
		<form id="pmform" action="' . $this->myPage . '" method="POST">
			<input type="hidden" name="action" value="post">
			<table cellpadding="1" cellspacing="2" id="postform_tbl" style="margin: 0 auto; text-align: left;">
				<tr>
					<td class="postblock"><label for="inputFrom">From</label></td>
					<td><input type="text" class="inputtext" name="from" id="inputFrom" value=""><span class="inputInfo">(format: yourname#tripcode)</span></td>
				</tr>
				<tr>
					<td class="postblock"><label for="inputTo">To</label></td>
					<td>' . _T('trip_pre') . '<input type="text" class="inputtext" name="t" id="inputTo" value="' . $trip . '" maxlength="10"></td>
				</tr>
				<tr>
					<td class="postblock"><label for="inputSubject">' . _T('form_topic') . '</label></td>
					<td><input type="text" class="inputtext" name="topic" id="inputSubject"><input type="submit" name="submit" value="' . _T('form_submit_btn') . '"></td>
				</tr>
				<tr>
					<td class="postblock"><label for="inputComment">' . _T('form_comment') . '</label></td>
					<td><textarea class="inputtext" name="content" id="inputComment"></textarea></td>
				</tr>
			</table>
		</form>
	</div>
</div>
<script>
	$g("pmform").from.value = getCookie("namec");
</script>
<hr>';
					
				break;

				case 'post':
					
						$this->_postPM($_POST['from'], $_POST['t'], $_POST['topic'], $_POST['content']);

						$from = $_POST['from'] ?? '';

						if (preg_match('/(.*?)[#＃](.*)/u', $from, $regs)) {
								$from = '<span class="postername">' . htmlspecialchars($regs[1]) . '</span>';
								$cap = strtr($regs[2], ['&amp;' => '&']);
								$from .= '<span class="postertrip">' . _T('trip_pre') . $this->_tripping($cap) . '</span>';
						}

						$dat .= '
[<a href="'.$this->config['PHP_SELF2'].'?'.$_SERVER['REQUEST_TIME'].'">Return</a>]
<div id="PMContainer">
	<h2 class="theading2">Message sent</h2>
	<div class="postformTable">
		<table cellpadding="1" cellspacing="1" id="postform_tbl">
			<tr><td class="postblock">From</td><td class="name">' . $from . '</td></tr>
			<tr><td class="postblock">To</td><td>' . _T('trip_pre') . htmlspecialchars($_POST['t']) . '</td></tr>
			<tr><td class="postblock">' . _T('form_topic') . '</td><td>' . htmlspecialchars($_POST['topic']) . '</td></tr>
			<tr><td class="postblock">' . _T('form_comment') . '</td><td><div class="comment">' . htmlspecialchars($_POST['content']) . '</div></td></tr>
		</table>
	</div>
</div>
<hr>';
				
						break;

				case 'delete':
						if (isset($_POST['trip'])) {
								$delno = [];

								foreach ($_POST as $key => $value) {
										if ($value === 'delete') {
												$delno[] = $key;
										}
								}

								if (!empty($delno)) {
										$this->_deletePM($delno, $_POST['trip']);
								}
						}
						// Fallthrough to inbox

				default:
				
						$dat .= '
[<a href="'.$this->config['PHP_SELF2'].'?'.$_SERVER['REQUEST_TIME'].'">Return</a>]
<div id="PMContainer">
	<h2 class="theading2">Inbox</h2>';

						$dat .= $this->_latestPM();

						$dat .= 'Check your inbox by inputting your password below
	<form id="pmform" action="' . $this->myPage . '" method="POST">
		<input type="hidden" name="action" value="check">
		<label>Trip:<input type="text" class="inputtext" name="trip" value="" size="28"></label>
		<input type="submit" name="submit" value="' . _T('form_submit_btn') . '">(Trip pass with #)
	</form>
</div>
<script>
	$g("pmform").trip.value = getCookie("namec").replace(/^[^#]*#/, "#");
</script>';


						if ($action === 'check' && isset($_POST['trip']) && str_starts_with($_POST['trip'], '#')) {
								$dat .= $this->_getPM($_POST['trip']);
						} else {
								$dat .= '<hr>';
						}
						break;
		}
		$globalHTML->foot($dat);
		echo $dat;

	}

}
