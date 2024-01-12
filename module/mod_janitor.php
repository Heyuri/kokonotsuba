<?php
// komeo 2023
class mod_janitor extends ModuleHelper {
	private $BANFILE = STORAGE_PATH.'bans.log.txt';
	private $mypage;

	public function __construct($PMS) {
		parent::__construct($PMS);
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
		$FileIO = PMCLibrary::getFileIOInstance();
		if (valid() != LEV_JANITOR) return;
		//if (!($ip=$this->_lookupPostIP($post['no']))) return;
		$modfunc.= '[<a href="'.$this->mypage.'&no='.$post['no'].'" title="Warn">W</a>]';
	}
	
	public function ModulePage() {
		$PIO = PMCLibrary::getPIOInstance();
		$PMS = PMCLibrary::getPMSInstance();
		
		if (valid() < LEV_JANITOR) error('403 Access denied');
		
		if ($_SERVER['REQUEST_METHOD']!='POST') { 
			$dat = '';
			head($dat);
			$dat .= '[<a href="'.PHP_SELF2.'?'.$_SERVER['REQUEST_TIME'].'">Return</a>]<br>
			<fieldset class="menu" style="display: inline-block;"><legend>Warn User</legend>
				<form action="'.PHP_SELF.'" method="POST">
					<input type="hidden" name="mode" value="module" />
					<input type="hidden" name="load" value="mod_janitor" />
					<label>Post No.<input type="number" name="no" min="0" value="'.($_GET['no']??'0').'" /></label><br />
					<label>Reason:<br />
						<textarea name="msg" cols="80" rows="6">No reason given.</textarea></label><br />
					<label>Public? <input type="checkbox" name="public">
					<center><input type="submit" value="Warn"></center>
			</form>
			</fieldset>
			';
			foot($dat);
			echo $dat;
		}
		else {
			$no = intval($_POST['no']);
			$post = $PIO->fetchPosts($no)[0];
			if (!$post) error('ERROR: That post does not exist.');
			$ip = $post['host'];
			$reason = str_replace(",", "&#44;", preg_replace("/[\r\n]/", '', nl2br($_POST['msg']??'')));
			if(!$reason) $reason='No reason given.';
			if ($_POST["public"]) {
				$post['com'] .= "<br \><br \><b class=\"warning\">($reason)</b> <img style=\"vertical-align: baseline;\" src=\"https://static.heyuri.net/image/hammer.gif\">";
				$PIO->updatePost($no, $post);
				$PIO->dbCommit();
				$parentNo = $post['resto'] ? $post['resto'] : $post['no'];
				deleteCache(array($parentNo));
			}
			$log = array_map('rtrim', file($this->BANFILE));
			$f = fopen($this->BANFILE, 'w');
			$rtime = $_SERVER['REQUEST_TIME'];
			fwrite($f, "$ip,$rtime,$rtime,$reason\r\n");
			logtime('Warned '.$ip, valid());
			
			fclose($f);
			updatelog();
			redirect('back', 0);
		}
	}
}