<?php
class mod_soudane extends ModuleHelper {
	private $SOUDANE_DIR = STORAGE_PATH.'soudane/';
	private $mypage;

	public function __construct($PMS) {
		parent::__construct($PMS);
		$this->mypage = str_replace('&amp;', '&', $this->getModulePageURL());
		if (!is_dir($this->SOUDANE_DIR)) {
			@mkdir($this->SOUDANE_DIR);
		}
		if (!is_writable($this->SOUDANE_DIR)) {
			error('ERROR: Cannot write to SOUDANE_DIR!');
		}
	}

	public function getModuleName() {
		return __CLASS__.' : K! Soudane';
	}

	public function getModuleVersionInfo() {
		return 'Koko BBS Release 1';
	}

	private function _soudane($no) {
		$log = @file($this->SOUDANE_DIR."$no.dat");
		if (!is_array($log)) return array();
		$log = array_map('rtrim', $log);
		return $log;
	}

	private function _soudaneTxt($log) {
		if ($count = count($log)) return "<small>yep&nbsp;&times;$count</small>";
		else return '&plus;';
	}

	public function autoHookHead(&$txt, $isReply){
		$txt .= '<script async="async">
function sd(sno) {
	var xmlhttp = false;
	if (typeof ActiveXObject != "undefined") {
		try {
			xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
		} catch (e) {
			xmlhttp = false;
		}
	}
	if (!xmlhttp && typeof XMLHttpRequest != "undefined") {
		xmlhttp = new XMLHttpRequest();
	}
	xmlhttp.open("GET", "'.$this->mypage.'&no="+sno);
	var sod = document.getElementById("sd"+sno);
	sod.innerHTML = "&hellip;";
	xmlhttp.onreadystatechange = function() {
		if (xmlhttp.readyState == 4 ) {
			sod.innerHTML = xmlhttp.responseText;
		}
	};
	xmlhttp.send(null);
}
</script>';
	}

	public function autoHookThreadPost(&$arrLabels, $post, $isReply) {
		$log = $this->_soudane($post['no']);
		$arrLabels['{$QUOTEBTN}'].= ' <a id="sd'.$post['no'].'" class="sod" href="javascript:sd('.$post['no'].');">'.
			$this->_soudaneTxt($log).'</a>';
	}

	public function autoHookThreadReply(&$arrLabels, $post, $isReply){
		$this->autoHookThreadPost($arrLabels, $post, $isReply);
	}
	
	public function ModulePage() {
		$PIO = PMCLibrary::getPIOInstance();
		$no = intval($_GET['no']??'');
		if (!$no) die('Bad No.');
		if (!count($PIO->fetchPosts($no))) die('Post not found!');
		$log = $this->_soudane($no);
		$ip = getREMOTE_ADDR();
		if (!in_array($ip, $log)) array_push($log, $ip);
		file_put_contents($this->SOUDANE_DIR."$no.dat", implode("\r\n", $log));
		echo $this->_soudaneTxt($log);
	}
}
