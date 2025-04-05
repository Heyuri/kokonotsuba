<?php
class mod_soudane extends moduleHelper {
	private $SOUDANE_DIR_YEAH = '';
	private $SOUDANE_DIR_NOPE = '';
	private $mypage;
	private $enableYeah;
	private $enableNope;
	private $enableScore;      // Renamed from "difference" to "score"
	private $showScoreOnly;    // New property for showing "+" and "-" buttons only

	public function __construct($moduleEngine) {
		parent::__construct($moduleEngine);
		$globalHTML = new globalHTML($this->board);

		// Load settings to enable/disable each button, score display, and score-only mode
		$this->enableYeah = $this->config['ModuleSettings']['ENABLE_YEAH'] ?? true;
		$this->enableNope = $this->config['ModuleSettings']['ENABLE_NOPE'] ?? true;
		$this->enableScore = $this->config['ModuleSettings']['ENABLE_SCORE'] ?? false;        // Score display toggle
		$this->showScoreOnly = $this->config['ModuleSettings']['SHOW_SCORE_ONLY'] ?? false;   // Score-only button mode

		// Define storage directories
		$this->SOUDANE_DIR_YEAH = getBackendGlobalDir().'soudane/';
		$this->SOUDANE_DIR_NOPE = getBackendGlobalDir().'soudane2/';
		
		$this->mypage = str_replace('&amp;', '&', $this->getModulePageURL());

		// Create directories if they do not exist
		if ($this->enableYeah && !is_dir($this->SOUDANE_DIR_YEAH)) {
			@mkdir($this->SOUDANE_DIR_YEAH);
		}
		if ($this->enableNope && !is_dir($this->SOUDANE_DIR_NOPE)) {
			@mkdir($this->SOUDANE_DIR_NOPE);
		}

		// Check write permissions
		if ($this->enableYeah && !is_writable($this->SOUDANE_DIR_YEAH)) {
			$globalHTML->error('ERROR: Cannot write to SOUDANE_DIR_YEAH!');
		}
		if ($this->enableNope && !is_writable($this->SOUDANE_DIR_NOPE)) {
			$globalHTML->error('ERROR: Cannot write to SOUDANE_DIR_NOPE!');
		}
	}

	public function getModuleName() {
		return __CLASS__ . ' : K! Soudane Merged';
	}

	public function getModuleVersionInfo() {
		return 'Koko BBS Release 1 Merged';
	}

	private function _loadVotes($post_uid, $type) {
		$dir = $type === 'yeah' ? $this->SOUDANE_DIR_YEAH : $this->SOUDANE_DIR_NOPE;
		$log = @file($dir . "$post_uid.dat");
		return is_array($log) ? array_map('rtrim', $log) : [];
	}

	private function _getVoteButtonText($type, $count) {
		// If no votes exist, display "+" or "-" regardless of settings
		if ($count === 0) {
			return $type === 'yeah' ? '+' : '-';
		}
		// If SHOW_SCORE_ONLY is enabled, always show "+" or "-" without counts
		if ($this->showScoreOnly) {
			return $type === 'yeah' ? '+' : '-';
		}
		// Otherwise, show "Yeah x" or "Nope x" with counts
		return $type === 'yeah' ? "Yeah x$count" : "Nope x$count";
	}

	private function _getScore($post_uid) {
		$yeahVotes = count($this->_loadVotes($post_uid, 'yeah'));
		$nopeVotes = count($this->_loadVotes($post_uid, 'nope'));
		return $yeahVotes - $nopeVotes;
	}

	public function autoHookHead(&$txt, $isReply) {
		$txt .= '
<script>
	function vote(post_uid, type) {
		var xmlhttp = new XMLHttpRequest();
		xmlhttp.open("GET", "'.$this->mypage.'&post_uid=" + encodeURIComponent(post_uid) + "&type=" + type);
		var elem = document.getElementById("vote_" + type + "_" + post_uid);
		elem.innerHTML = "&hellip;";
		xmlhttp.onreadystatechange = function() {
			if (xmlhttp.readyState == 4) {
				elem.innerHTML = xmlhttp.responseText;
				updateScore(post_uid);

				// Check and update class from noVotes to hasVotes if needed
				if (elem.classList.contains("noVotes")) {
					elem.classList.remove("noVotes");
					elem.classList.add("hasVotes");
				}
			}
		};
		xmlhttp.send(null);
	}

	function updateScore(post_uid) {
		var scoreElem = document.getElementById("vote_score_" + post_uid);
		var xmlhttp = new XMLHttpRequest();
		xmlhttp.open("GET", "'.$this->mypage.'&post_uid=" + encodeURIComponent(post_uid) + "&type=score");
		xmlhttp.onreadystatechange = function() {
			if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
				scoreElem.innerHTML = xmlhttp.responseText;
			}
		};
		xmlhttp.send(null);
	}
</script>';
	}

	public function autoHookThreadPost(&$arrLabels, $post, $isReply) {
		$post_uid = $post['post_uid'];

		$arrLabels['{$POSTINFO_EXTRA}'] .= ' <span class="soudaneContainer">';

		if ($this->enableNope) {
			$logNope = $this->_loadVotes($post_uid, 'nope');
			$classNope = count($logNope) > 0 ? 'hasVotes' : 'noVotes';
			$buttonTextNope = $this->_getVoteButtonText('nope', count($logNope));
			$arrLabels['{$POSTINFO_EXTRA}'] .= '<span class="soudane2"><a id="vote_nope_'.$post_uid.'" class="sod '.$classNope.'" href="javascript:vote(\''.$post_uid.'\', \'nope\');">'.$buttonTextNope.'</a></span>';
		}

		if ($this->enableNope && $this->enableYeah) {
			$arrLabels['{$POSTINFO_EXTRA}'] .= ' ';
		}

		// If SHOW_SCORE_ONLY is enabled, show score in between the "-" and "+"
		if ($this->enableScore && $this->showScoreOnly) {
			$score = $this->_getScore($post_uid);
			$arrLabels['{$POSTINFO_EXTRA}'] .= '<span id="vote_score_'.$post_uid.'" class="voteScore">'.$score.'</span> ';
		}

		if ($this->enableYeah) {
			$logYeah = $this->_loadVotes($post_uid, 'yeah');
			$classYeah = count($logYeah) > 0 ? 'hasVotes' : 'noVotes';
			$buttonTextYeah = $this->_getVoteButtonText('yeah', count($logYeah));
			$arrLabels['{$POSTINFO_EXTRA}'] .= '<span class="soudane"><a id="vote_yeah_'.$post_uid.'" class="sod '.$classYeah.'" href="javascript:vote(\''.$post_uid.'\', \'yeah\');">'.$buttonTextYeah.'</a></span>';
		}

		// If SHOW_SCORE_ONLY is not enabled, display the score separately
		if ($this->enableScore && !$this->showScoreOnly) {
			$score = $this->_getScore($post_uid);
			$arrLabels['{$POSTINFO_EXTRA}'] .= '<span id="vote_score_'.$post_uid.'" class="voteScore">Score: '.$score.'</span>';
		}

		$arrLabels['{$POSTINFO_EXTRA}'] .= '</span>';
	}

	public function autoHookThreadReply(&$arrLabels, $post, $isReply) {
		$this->autoHookThreadPost($arrLabels, $post, $isReply);
	}
	
	public function ModulePage() {
		$PIO = PIOPDO::getInstance();
		$post_uid = $_GET['post_uid'] ?? '';
		$type = $_GET['type'] ?? '';
		if (!$post_uid || !in_array($type, ['yeah', 'nope', 'score'])) die('Invalid parameters.');

		if (!count($PIO->fetchPosts($post_uid))) die('Post not found!');
		
		if ($type === 'score') {
			echo $this->_getScore($post_uid);
			exit;
		}

		$log = $this->_loadVotes($post_uid, $type);
		$ip = new IPAddress;
		if (!in_array($ip, $log)) array_push($log, $ip);

		$dir = $type === 'yeah' ? $this->SOUDANE_DIR_YEAH : $this->SOUDANE_DIR_NOPE;
		file_put_contents($dir . "$post_uid.dat", implode("\r\n", $log));
		
		echo $this->_getVoteButtonText($type, count($log));
	}
}
