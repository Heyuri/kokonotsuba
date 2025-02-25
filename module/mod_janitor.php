<?php
// komeo 2023
class mod_janitor extends ModuleHelper {
	private $BANFILE = -1;
	private $mypage;

	public function __construct($PMS) {
		parent::__construct($PMS);
		
		$this->BANFILE = $this->board->getBoardStoragePath() . 'bans.log.txt';
		$this->mypage = $this->getModulePageURL();
		touch($this->BANFILE);
	}

	public function getModuleName() {
		return __CLASS__.' : Janitor tools';
	}

	public function getModuleVersionInfo() {
		return 'Koko BBS Release 1';
	}
	
	public function autoHookAdminList(&$modfunc, $post, $isres) {
		$staffSession = new staffAccountFromSession;
		if ($staffSession->getRoleLevel() != $this->config['roles']['LEV_JANITOR']) return;

		$modfunc.= '[<a href="'.$this->mypage.'&post_uid='.$post['post_uid'].'" title="Warn">W</a>]';
	}
	
	public function ModulePage() {
		$PIO = PIOPDO::getInstance();
		$PMS = PMS::getInstance();
		$actionLogger = ActionLogger::getInstance();

		$softErrorHandler = new softErrorHandler($this->board);
		$globalHTML = new globalHTML($this->board);
		$staffSession = new staffAccountFromSession;
		$softErrorHandler->handleAuthError($this->config['roles']['LEV_JANITOR']);

		$postUidFromGET = $_GET['post_uid'] ?? '';
		$postNumber = $PIO->resolvePostNumberFromUID($postUidFromGET);

		if ($_SERVER['REQUEST_METHOD']!='POST') { 
			$dat = '';
			$globalHTML->head($dat);
			$dat .= '[<a href="'.$this->config['PHP_SELF2'].'?'.$_SERVER['REQUEST_TIME'].'">Return</a>]<br>
			<fieldset class="menu" style="display: inline-block;"><legend>Warn User</legend>
				<form action="'.$this->config['PHP_SELF'].'" method="POST">
					<input type="hidden" name="mode" value="module">
					<input type="hidden" name="load" value="mod_janitor">
					<label> <span>Post Number '.$postNumber.'</span> </label><br>
					<input type="hidden" name="post_uid"  value="'.$postUidFromGET.'"></label><br>
					<label>Reason:<br>
						<textarea name="msg" cols="80" rows="6">No reason given.</textarea></label><br>
					<label>Public? <input type="checkbox" name="public">
					<center><input type="submit" value="Warn"></center>
			</form>
			</fieldset>
			';
			$globalHTML->foot($dat);
			echo $dat;
		}
		else {
			$post_uid = $_POST['post_uid'] ?? '';
			

			$post = $PIO->fetchPosts($post_uid)[0];
			if (!$post) $globalHTML->error('ERROR: That post does not exist.');
			$ip = $post['host'];
			$reason = str_replace(",", "&#44;", preg_replace("/[\r\n]/", '', nl2br($_POST['msg']??'')));
			if(!$reason) $reason='No reason given.';
			if ($_POST["public"]) {
				$post['com'] .= "<br \><br \><b class=\"warning\">($reason)</b> <img style=\"vertical-align: baseline;\" src=\"".$this->config['STATIC_URL']."/image/hammer.gif\">";
				$PIO->updatePost($post_uid, $post);
				$parentNo = $post['thread_uid'];
			}
			
			
			$log = array_map('rtrim', file($this->BANFILE));
			$f = fopen($this->BANFILE, 'w');
			$rtime = $_SERVER['REQUEST_TIME'];
			fwrite($f, "$ip,$rtime,$rtime,$reason\r\n");
			$actionLogger->logAction('Warned '.$ip.' for post: '.$postNumber, $this->board->getBoardUID());
			
			fclose($f);
			$this->board->rebuildBoard();
			redirect('back', 0);
		}
	}
}
