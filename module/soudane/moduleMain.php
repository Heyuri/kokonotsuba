<?php

namespace Kokonotsuba\Modules\soudane;

use BoardException;
use Exception;
use IPAddress;
use Kokonotsuba\ModuleClasses\abstractModuleMain;

class moduleMain extends abstractModuleMain {
	private $SOUDANE_DIR_YEAH = '';
	private $SOUDANE_DIR_NOPE = '';
	private $mypage;
	private $enableYeah;
	private $enableNope;
	private $enableScore;      // Renamed from "difference" to "score"
	private $showScoreOnly;    // New property for showing "+" and "-" buttons only

	public function getName(): string {
		return 'K! Soudane Merged';
	}

	public function getVersion(): string  {
		return 'Koko BBS Release';
	}

	public function initialize(): void {
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
			throw new BoardException('ERROR: Cannot write to SOUDANE_DIR_YEAH!');
		}
		if ($this->enableNope && !is_writable($this->SOUDANE_DIR_NOPE)) {
			throw new BoardException('ERROR: Cannot write to SOUDANE_DIR_NOPE!');
		}

		$this->moduleContext->moduleEngine->addListener('Post', function (&$arrLabels, $post) {
			$this->onRenderPost($arrLabels, $post);
		});
		
		$this->moduleContext->moduleEngine->addListener('Head', function (&$headHtml) {
			$this->onRenderHead($headHtml);
		});
	}

	private function _loadVotes($post_uid, $type) {
		// Determine the file path based on the vote type
		$dir = $type === 'yeah' ? $this->SOUDANE_DIR_YEAH : $this->SOUDANE_DIR_NOPE;
		$filePath = $dir . "$post_uid.dat";
	
		// Check if the file exists and is readable
		if (!file_exists($filePath)) {
			// File doesn't exist, return an empty array
			return [];
		}
	
		if (!is_readable($filePath)) {
			// File exists but is not readable, return an empty array
			return [];
		}
	
		// Try reading the file contents
		try {
			$log = file($filePath);
			if (is_array($log)) {
				return array_map('rtrim', $log); // Trim each line of the file
			} else {
				// If file is not an array, return empty array
				return [];
			}
		} catch (Exception $e) {
			// Handle any unexpected errors (e.g., permissions issues, filesystem errors)
			return [];
		}
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

	private function _getScore(string $post_uid) {
		$yeahVotes = count($this->_loadVotes($post_uid, 'yeah'));
		$nopeVotes = count($this->_loadVotes($post_uid, 'nope'));
		return $yeahVotes - $nopeVotes;
	}

	private function onRenderHead(string &$headHtml) {
		$headHtml .= '
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

	private function onRenderPost(array &$arrLabels, array $post): void {
		$post_uid = $post['post_uid'];

		$arrLabels['{$POSTINFO_EXTRA}'] .= ' <span class="soudaneContainer">';

		if ($this->enableNope) {
			$logNope = $this->_loadVotes($post_uid, 'nope');
			$classNope = count($logNope) > 0 ? 'hasVotes' : 'noVotes';
			$buttonTextNope = $this->_getVoteButtonText('nope', count($logNope));
			$arrLabels['{$POSTINFO_EXTRA}'] .= '<span class="soudane2" title="Disagree with this post"><a id="vote_nope_'.$post_uid.'" class="sod '.$classNope.'" href="javascript:vote(\''.$post_uid.'\', \'nope\');">'.$buttonTextNope.'</a></span>';
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
			$arrLabels['{$POSTINFO_EXTRA}'] .= '<span class="soudane" title="Agree with this post"><a id="vote_yeah_'.$post_uid.'" class="sod '.$classYeah.'" href="javascript:vote(\''.$post_uid.'\', \'yeah\');">'.$buttonTextYeah.'</a></span>';
		}

		// If SHOW_SCORE_ONLY is not enabled, display the score separately
		if ($this->enableScore && !$this->showScoreOnly) {
			$score = $this->_getScore($post_uid);
			$arrLabels['{$POSTINFO_EXTRA}'] .= '<span id="vote_score_'.$post_uid.'" class="voteScore">Score: '.$score.'</span>';
		}

		$arrLabels['{$POSTINFO_EXTRA}'] .= '</span>';
	}

	public function ModulePage() {
		$post_uid = $_GET['post_uid'] ?? '';
		$type = $_GET['type'] ?? '';
		if (!$post_uid || !in_array($type, ['yeah', 'nope', 'score'])) {
			throw new BoardException('Invalid parameters.');
		}

		if (!count($this->moduleContext->postRepository->getPostByUid($post_uid))) {
			throw new BoardException('Post not found!');
		}

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
