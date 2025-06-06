<?php
class mod_pushpost extends moduleHelper {
	// Tweet judgment start tag
	private $PUSHPOST_SEPARATOR = '[MOD_PUSHPOST_USE]';
	// The maximum number of tweets displayed in the discussion thread (if exceeded, it will be automatically hidden, all hidden: 0)
	private $PUSHPOST_DEF = 5;
	private $PUSH_POST_MAX_CHAR = 0;
	private $mypage;

	public function __construct(moduleEngine $moduleEngine, boardIO $boardIO, pageRenderer $pageRenderer, pageRenderer $adminPageRenderer) {
		parent::__construct($moduleEngine, $boardIO, $pageRenderer, $adminPageRenderer);
		$this->PUSH_POST_MAX_CHAR = $this->config['ModuleSettings']['PUSHPOST_CHARACTER_LIMIT'];
		$this->mypage = $this->getModulePageURL();
	}

	public function getModuleName() {
		return $this->moduleNameBuilder('Push Post');
	}

	public function getModuleVersionInfo() {
		return '7th.Release (v140529)';
	}

	public function autoHookThreadPost(&$arrLabels, $post, $threadPosts, $isReply) {
		$PIO = PIOPDO::getInstance();
		$pushcount = '';

		if ($post['status'] != '') {
			$f = $PIO->getPostStatus($post['post_uid']);
			$pushcount = $f->value('mppCnt'); // Number of pushes
		}

		$arrLabels['{$QUOTEBTN}'] .= '&nbsp;<a href="' .
			$this->getModulePageURL(['no' => $post['no']]) .
			'" onclick="return mod_pushpostShow(' . $post['no'] . ')">' .
			$pushcount . $this->_T('pushbutton') . '</a>';

		if (strpos($arrLabels['{$COM}'], $this->PUSHPOST_SEPARATOR . '<br>') !== false) {
			if ($isReply || $pushcount <= $this->PUSHPOST_DEF) {
				$arrLabels['{$COM}'] = str_replace(
					$this->PUSHPOST_SEPARATOR . '<br>', 
					'<div class="pushpost">', 
					$arrLabels['{$COM}']
				) . '</div>';
			} else {
				$delimiter = strpos($arrLabels['{$COM}'], $this->PUSHPOST_SEPARATOR . '<br>');
				if ($this->PUSHPOST_DEF > 0) {
					$push_array = explode('<br>', substr($arrLabels['{$COM}'], $delimiter + strlen($this->PUSHPOST_SEPARATOR . '<br>')));
					$pushs = '<div class="pushpost">...<br>' . implode('<br>', array_slice($push_array, 0 - $this->PUSHPOST_DEF)) . '</div>';
				} else {
					$pushs = '';
				}
				$arrLabels['{$COM}'] = substr($arrLabels['{$COM}'], 0, $delimiter) . $pushs;
				$arrLabels['{$WARN_BEKILL}'] .= '<span class="warn_txt2">' . $this->_T('omitted') . '<br></span>' . "\n";
			}
		}
	}

	public function autoHookThreadReply(&$arrLabels, $post, $threadPosts, $isReply) {
		$this->autoHookThreadPost($arrLabels, $post, $threadPosts, $isReply);
	}

	public function autoHookRegistBegin(&$name, &$email, &$sub, &$com, $upfileInfo, $accessInfo, $isReply) {
		if (strpos($com, $this->PUSHPOST_SEPARATOR . "\r\n") !== false) {
			$com = str_replace($this->PUSHPOST_SEPARATOR . "\r\n", "\r\n", $com);
	 	}
	}

	public function autoHookAdminList(&$modFunc, $post, $isres) {
		$modFunc .= '[<a href="' . $this->getModulePageURL([
			'post_uid' => $post['post_uid']
		]) . '">push post</a>]';
	}

	public function ModulePage() {
		$PIO = PIOPDO::getInstance();
		$globalHTML = new globalHTML($this->board);
		$post_uid = $_GET['post_uid'] ?? null;
		if (!isset($post_uid)) $globalHTML->error('No post selected');

		$htmlOutput = '';

		$globalHTML->drawPushPostForm($htmlOutput, $this->PUSH_POST_MAX_CHAR, $this->mypage);

	}

}
