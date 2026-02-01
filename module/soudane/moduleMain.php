<?php

namespace Kokonotsuba\Modules\soudane;

use BoardException;
use DatabaseConnection;
use IPAddress;
use Kokonotsuba\ModuleClasses\abstractModuleMain;

require_once __DIR__ . '/soudaneRepository.php';
require_once __DIR__ . '/soudaneService.php';

class moduleMain extends abstractModuleMain {
	private string $moduleUrl;
	private bool $enableYeah;
	private bool $enableNope;
	private bool $enableScore;      // score
	private bool $showScoreOnly;    // property for showing "+" and "-" buttons only
	private soudaneService $soudaneService;

	public function getName(): string {
		return 'Soundane';
	}

	public function getVersion(): string  {
		return 'Koko BBS Release';
	}

	public function initialize(): void {
		// Load settings to enable/disable each button, score display, and score-only mode
		$this->enableYeah = $this->getConfig('ModuleSettings.ENABLE_YEAH', true);
		$this->enableNope = $this->getConfig('ModuleSettings.ENABLE_NOPE', true);
		$this->enableScore = $this->getConfig('ModuleSettings.ENABLE_SCORE', false);        // Score display toggle
		$this->showScoreOnly = $this->getConfig('ModuleSettings.SHOW_SCORE_ONLY', false);   // Score-only button mode

		$this->moduleUrl = $this->getModulePageURL([], false);

		// get database connection and database setting
		$databaseConnection = DatabaseConnection::getInstance();
		$soudaneTable = getDatabaseSettings()['SOUDANE_TABLE'];

		// init soudane repo
		$soudaneRepository = new soudaneRepository($databaseConnection, $soudaneTable);

		// init soudane service
		$soudaneService = new soudaneService($soudaneRepository);

		// set property
		$this->soudaneService = $soudaneService;

		$this->moduleContext->moduleEngine->addListener('Post', function (&$arrLabels, $post) {
			$this->onRenderPost($arrLabels, $post);
		});
		
		$this->moduleContext->moduleEngine->addListener('ModuleHeader', function(string &$moduleHeader) {
			$this->onGenerateModuleHeader($moduleHeader);
		});	
	}

	private function renderVoteButton(
		int $postUid,
		string $type, // 'yeah' | 'nope'
		int $voteCount,
		string $title,
		string $wrapperClass
	): string {
		$class = $voteCount > 0 ? 'hasVotes' : 'noVotes';
		$buttonText = $this->getVoteButtonText($type, $voteCount);

		$postUidEsc = htmlspecialchars((string) $postUid, ENT_QUOTES, 'UTF-8');
		$moduleUrlEsc = htmlspecialchars($this->moduleUrl, ENT_QUOTES, 'UTF-8');

		return
			'<span class="' . htmlspecialchars($wrapperClass) . '" title="' . htmlspecialchars($title) . '">' .
				'<a id="vote_' . $type . '_' . $postUidEsc . '" ' .
				'class="sod ' . htmlspecialchars($class) . '" ' .
				'href="javascript:soudane.vote(\'' . $postUidEsc . '\', \'' . $type . '\', \'' . $moduleUrlEsc . '\');">' .
					htmlspecialchars($buttonText) .
				'</a>' .
			'</span>';
	}

	private function renderScore(int $postUid, string $scoreText): string {
    	return
    	    '<span class="soundaneScoreContainer">
				<span id="vote_score_' . htmlspecialchars((string) $postUid, ENT_QUOTES, 'UTF-8') . '" ' .
    	          'class="voteScore">' .
    	        htmlspecialchars($scoreText) . '
				</span>
			</span>';
	}

	private function onRenderPost(array &$arrLabels, array $post): void {
		$postUid = $post['post_uid'];

		$arrLabels['{$POSTINFO_EXTRA}'] .= ' <span class="soudaneContainer">';

		// enable nope
		if ($this->enableNope) {
			$logNope = $this->loadVotes($postUid, 'nope');
			$arrLabels['{$POSTINFO_EXTRA}'] .= $this->renderVoteButton(
				$postUid,
				'nope',
				count($logNope),
				'Disagree with this post',
				'soudaneNope'
			);
		}

		// add a space so Nope & Yeah aren't touching each other
		if ($this->enableNope && $this->enableYeah) {
			$arrLabels['{$POSTINFO_EXTRA}'] .= ' ';
		}

		// If SHOW_SCORE_ONLY is enabled, show score in between the "-" and "+"
		if ($this->enableScore && $this->showScoreOnly) {
			$score = $this->getScore($postUid);
			$arrLabels['{$POSTINFO_EXTRA}'] .=
				$this->renderScore($postUid, $score) . ' ';
		}

		// yeah vote
		if ($this->enableYeah) {
			$logYeah = $this->loadVotes($postUid, 'yeah');
			$arrLabels['{$POSTINFO_EXTRA}'] .= $this->renderVoteButton(
				$postUid,
				'yeah',
				count($logYeah),
				'Agree with this post',
				'soudane'
			);
		}

		// If SHOW_SCORE_ONLY is not enabled, display the score separately
		if ($this->enableScore && !$this->showScoreOnly) {
			$score = _T('score_pre_text', $this->getScore($postUid));
			$arrLabels['{$POSTINFO_EXTRA}'] .=
				' ' . $this->renderScore($postUid, $score);
		}

		$arrLabels['{$POSTINFO_EXTRA}'] .= '</span>';
	}

	private function onGenerateModuleHeader(string &$moduleHeader): void {
		// generate the soudane js <script> include
		$jsHtml = $this->generateScriptHeader('soudane.js', true);

		// append to module header
		$moduleHeader .= $jsHtml;

		// now build a meta tag to store the API endpoint for fetching votes
		$moduleHeader .= '<meta name="soudaneUrl" content="' . $this->getModulePageURL(['modPage' => 'soudaneApi']) . '">';
	}
	
	private function loadVotes(int $postUid, string $type): ?array {
		// fetch the votes and return them based on type
		$votes = $this->soudaneService->getVotes($postUid, $type);

		// return the votes
		return $votes;
	}	

	private function getVoteButtonText(string $type, int $count): string {
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

	private function getScore(string $postUid): int {
		$yeahVotes = count($this->loadVotes($postUid, 'yeah'));
		$nopeVotes = count($this->loadVotes($postUid, 'nope'));
		return $yeahVotes - $nopeVotes;
	}

	private function handleSoudaneApi(): void {
		// extract the post uids from url
		$postUidsParameter = $_GET['posts'] ?? [];

		// exit if none are found
		if (empty($postUidsParameter)) {
			renderJsonPage(['error' => 'No post IDs provided']);
			return;
		}

		// explode post uids to get array
		$postUids = explode(' ', $postUidsParameter);

		// sanitize for integers using array mapping
		$postUids = array_map('intval', $postUids);

		// now fetch associated vote counts
		$yeahCounts = $this->soudaneService->getYeahCounts($postUids); // array of yeah counts per post
		$nopeCounts = $this->soudaneService->getNopeCounts($postUids); // array of nope counts per post

		// init nope and yeah html arrays
		$yeahHtml = [];
		$nopeHtml = [];

		// calculate score per post by subtracting nope from yeah
		$scores = [];
		foreach ($postUids as $uid) {
			$yeahCount = $yeahCounts[$uid] ?? 0;
			$nopeCount = $nopeCounts[$uid] ?? 0;

			$yeahHtml[$uid] = $this->renderVoteButton(
				$uid,
				'yeah',
				$yeahCount,
				'Agree with this post',
				'soudane'
			);

			$nopeHtml[$uid] = $this->renderVoteButton(
				$uid,
				'nope',
				$nopeCount,
				'Disagree with this post',
				'soudaneNope'
			);

			$scores[$uid] = $this->renderScore(
				$uid,
				_T('score_pre_text', $yeahCount - $nopeCount)
			);
		}

		// form the json data
		$data = [];
		foreach ($postUids as $uid) {
			$data[$uid] = [
				'yeah' => $yeahHtml[$uid] ?? 0,
				'nope' => $nopeHtml[$uid] ?? 0,
				'score' => $scores[$uid]
			];
		}

		// now render the json page
		renderJsonPage($data);
	}

	public function ModulePage() {
		// get mod page parameter
		$modPage = $_GET['modPage'] ?? '';

		// if the mod page parameter is targetting the api endpoint then call a method to generate json
		if($modPage === 'soudaneApi') {
			$this->handleSoudaneApi();
		}

		// Retrieve the postUid from GET parameters, default to empty string if not provided
		$postUid = $_GET['postUid'] ?? '';

		// Retrieve the type from GET parameters, default to empty string if not provided
		$type = $_GET['type'] ?? '';
		
		// Validate that postUid is not empty and type is one of the allowed values
		if (!$postUid || !in_array($type, ['yeah', 'nope', 'score'])) {
			throw new BoardException('Invalid parameters.');
		}

		// Check if the post exists in the repository
		if (!$this->moduleContext->postRepository->getPostByUid(
			$postUid, 
			isActiveStaffSession()
			)) {
			throw new BoardException(_T('post_not_found'));
		}

		// If the type is 'score', calculate and output the score, then exit
		if ($type === 'score') {
			echo _T('score_pre_text', $this->getScore($postUid));
			exit;
		}

		// Load existing votes for the post and type
		$log = $this->loadVotes($postUid, $type);

		// Get the current user's IP address
		$ip = new IPAddress;

		$yeahIPs = !empty($log) ? array_column($log, 'ip_address') : [];

		// Check if the current IP has already voted; if not, add it to the log
		if (!in_array($ip, $yeahIPs)) {
			// add to yeah IPs so we can render changes upon a new vote right away
			$yeahIPs[] = $ip;

			// Save the new vote using the service
			$this->soudaneService->addVote($postUid, $ip, $type);
		}

		// Output the updated button text with the new vote count
		echo $this->getVoteButtonText($type, count($yeahIPs));
	}
}
