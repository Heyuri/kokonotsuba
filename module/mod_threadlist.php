<?php
class mod_threadlist extends ModuleHelper {
	private $THREADLIST_NUMBER, $FORCE_SUBJECT, $SHOW_IN_MAIN, $THREADLIST_NUMBER_IN_MAIN, $SHOW_FORM, $HIGHLIGHT_COUNT = -1;

	public function __construct($PMS) {
		parent::__construct($PMS);

		$this->THREADLIST_NUMBER = $this->config['ModuleSettings']['THREADLIST_NUMBER'];
		$this->FORCE_SUBJECT = $this->config['ModuleSettings']['FORCE_SUBJECT'];
		$this->THREADLIST_NUMBER_IN_MAIN = $this->config['ModuleSettings']['THREADLIST_NUMBER_IN_MAIN'];
		$this->SHOW_FORM = $this->config['ModuleSettings']['SHOW_FORM'];
		$this->HIGHLIGHT_COUNT = $this->config['ModuleSettings']['HIGHLIGHT_COUNT'];		
		$this->SHOW_IN_MAIN = $this->config['ModuleSettings']['SHOW_IN_MAIN'];
		
		$this->attachLanguage(array(
			'zh_TW' => array(
				'modulename' => '討論串列表',
				'no_title' => '發文不可以沒有標題喔',
				'link' => '主題列表',
				'main_title' => '主題一覽',
				'page_title' => '列表模式',
				'date' => '日期'
			),
			'en_US' => array(
				'modulename' => 'Thread list',
				'no_title' => 'Non-Titled posts not accepted',
				'link' => 'Thread List',
				'main_title' => 'Thread overview',
				'page_title' => 'List mode',
				'date' => 'Date'
			)
		), 'en_US');
	}

	public function getModuleName() {
		return $this->moduleNameBuilder($this->_T('modulename'));
	}

	public function getModuleVersionInfo() {
		return 'Koko BBS Release 1';
	}

	public function autoHookRegistBeforeCommit(&$name, &$email, &$sub, &$com,
		&$category, &$age, $dest, $isReply, $imgWH, &$status) {
		if ($this->FORCE_SUBJECT && !$isReply && $sub == $this->config['DEFAULT_NOTITLE']) {
			error($this->_T('no_title'), $dest);
		}
	}

	public function autoHookToplink(&$linkbar, $isReply) {
		$linkbar .= '[<a href="'.$this->getModulePageURL().'">'.$this->_T('link').'</a>]';
	}

	public function autoHookThreadFront(&$txt, $isReply) {
		$PIO = PMCLibrary::getPIOInstance();
		$PTE = PMCLibrary::getPTEInstance();
		
		if($this->SHOW_IN_MAIN && !$isReply) {
			$dat = ''; // HTML Buffer
			$plist = $PIO->fetchThreadList(0, $this->THREADLIST_NUMBER_IN_MAIN); // Numbers are sorted from large to small
			self::$PMS->useModuleMethods('ThreadOrder', array($isReply, 0, 0, &$plist)); // "ThreadOrder" Hook Point

		    $dat .= '<center id="topiclist">
<table class="postlists" cellpadding="1" cellspacing="1" width="95%"><td>';
			for ($i=0; $i<count($plist); $i++) {
				$post = $PIO->fetchPosts($plist[$i]); // Take out data
				if (!($i%2)) $dat.= '';
				
				/* If a post is made w/out subject, it will use the comment instead.
				When that applies, if the comment is less than 10 chars, it displays completely. 
				Otherwise, just show the first 10 characters and then show it continues... */
				
			if (mb_strlen(strip_tags($post[0]['com'])) <= 10){
					$CommentTitle = strip_tags($post[0]['com']);
				} else {
					$CommentTitle = mb_substr(strip_tags($post[0]['com']),0,10,'UTF-8') . "...";
					//Yahoo! ^_^
				}
				
				$dat.= sprintf('<span<!--%d--> <a href="%s">%s (%d)</a></span>',
					$post[0]['no'],
					$this->config['PHP_SELF'].'?res='.$post[0]['no'], $post[0]['sub'] ? $post[0]['sub'] : $CommentTitle,
					$PIO->postCount($post[0]['no']) - 1
				);
			}
			if ($i%2) $dat.= '';
			$dat .= '</td></table>
</center>';
			$dat.= $PTE->ParseBlock('REALSEPARATE',array());
		    $txt .= $dat;
		}
	}

	private function _getPostCounts($posts) {
		$PIO = PMCLibrary::getPIOInstance();

		$pc = array();
		foreach($posts as $post)
			$pc[$post] = $PIO->postCount($post);

		return $pc;
	}

	private function _kasort(&$a, $revkey = false, $revval = false) {
		$t = $u= array();
		foreach ($a as $k => &$v) { // flip array
			if (!isset($t[$v])) $t[$v] = array($k);
			else $t[$v][] = $k;
		}

		if ($revkey) krsort($t);
		else ksort($t);

		foreach ($t as $k=>&$vv) {
			if ($revval) rsort($vv);
			else sort($vv);
		}
		foreach ($t as $k=>&$vv) { // reconstruct array
			foreach ($vv as &$v)
				$u[$v] = $k;
		}
		$a = $u;
	}

	public function ModulePage() {
		$PIO = PMCLibrary::getPIOInstance();
		$thisPage = $this->getModulePageURL(); // Base position
		$dat = ''; // HTML Buffer
		$listMax = $PIO->threadCount(); // Total number of discussion strings
		$pageMax = ceil($listMax / $this->THREADLIST_NUMBER) - 1; // Maximum paging number
		$page = isset($_GET['page']) ? intval($_GET['page']) : 0; // Current page number
		$sort = isset($_GET['sort']) ? $_GET['sort'] : 'no';
		if ($page < 0 || $page > $pageMax) exit('Page out of range.'); // $page out of range

		if (strpos($sort, 'post') !== false) {
			$plist = $PIO->fetchThreadList();
			$pc = $this->_getPostCounts($plist);
			$this->_kasort($pc,$sort == 'postdesc',true);
			// Cut out the required size
			$plist = array_slice(
				array_keys($pc),
				$this->THREADLIST_NUMBER * $page,
				$this->THREADLIST_NUMBER
			);
		} else {
			$plist = $PIO->fetchThreadList($this->THREADLIST_NUMBER * $page, $this->THREADLIST_NUMBER, $sort == 'date' ? false : true); // Numbers are sorted from large to small
			self::$PMS->useModuleMethods('ThreadOrder', array(0,$page,0,&$plist)); // "ThreadOrder" Hook Point
			$pc = $this->_getPostCounts($plist);
		}
		$post = $PIO->fetchPosts($plist); // Take out the data
		$post_count = count($post);

		if($sort=='date' || strpos($sort, 'post') !== false) { // To rearrange the order
			$mypost = array();

			foreach($plist as $p) {
				while (list($k, $v) = each($post)) {
					if($v['no'] == $p) {
						$mypost[] = $v;
					    unset($post[$k]);
					    break;
				    }
				}
				reset($post);
			}
			$post = $mypost;
		}

		head($dat);
		$dat .= '<script>
var selectall = "";
function checkall(){
	selectall = selectall ? "" : "checked";
	var inputs = document.getElementsByTagName("input");
	for(x=0; x < inputs.length; x++){
		if(inputs[x].type == "checkbox" && parseInt(inputs[x].name)) {
			inputs[x].checked = selectall;
		}
	}
}
</script>';
		$dat .= '<div id="contents">
[<a href="'.$this->config['PHP_SELF2'].'?'.time().'">'._T('return').'</a>]
<center class="theading"><b>'.$this->_T('page_title').'</b></center>'.
($this->SHOW_FORM ? '<form action="'.$this->config['PHP_SELF'].'" method="post">' : '').'<table align="center" width="95%" class="postlists" cellspacing="0" cellpadding="0" border="1"><thead><tr>
'.($this->SHOW_FORM ? '<th><a href="javascript:checkall()">↓</a></th>' : '').'
<th><a href="'.$thisPage.'&sort=no">No.'.($sort == 'no' ? ' ▼' : '').'</a></th>
<th width="48%">'._T('form_topic').'</th>
<th>'._T('form_name').'</th>
<th><a href="'.$thisPage.'&sort='.($sort == 'postdesc' ? 'postasc' : 'postdesc').'">'._T('reply_btn').($sort == 'postdesc' ? ' ▼' : ($sort == 'postasc' ? ' ▲' : '')).'</a></th>
<th><a href="'.$thisPage.'&sort=date">'.$this->_T('date').($sort == 'date' ? ' ▼' : '').'</a></th></tr>
</thead><tbody>
';

		for ($i = 0; $i < $post_count; $i++) {
			list($no, $sub, $name, $now) = array($post[$i]['no'], $post[$i]['sub'], $post[$i]['name'], $post[$i]['now']);

			$rescount = $pc[$no] - 1;
			if ($this->HIGHLIGHT_COUNT > 0 && $rescount > $this->HIGHLIGHT_COUNT) {
				$rescount = '<span class="warning">'.$rescount.'</span>';
			}
			$dat .= '<tr>'.
				($this->SHOW_FORM ? '<td align="CENTER"><input type="checkbox" name="'.$no.'" value="delete"></td>' : '').
				'<td align="CENTER"><a href="'.$this->config['PHP_SELF'].'?res='.$no.'">'.$no.'</a></td><td><big class="title"><b>'.( $sub ? $sub : 'No Title' ).
				'</b></big></td><td><span class="name">'.$name.'</span></td><td align="CENTER">'.$rescount.'</td><td>'.$now.'</td></tr>';
		}

		$dat .= '</tbody></table>
<hr>
<table border="1" align="LEFT" id="pager"><tr>
';
		if ($page) {
			$dat .= '<td><a href="'.$thisPage.'&page='.($page - 1).'&sort='.$sort.'">'.
				_T('prev_page').'</a></td>';
		}
		else $dat .= '<td nowrap="nowrap">'._T('first_page').'</td>';
		$dat .= '<td>';
		for ($i = 0; $i <= $pageMax; $i++) {
			if($i==$page) $dat .= '[<b>'.$i.'</b>] ';
			else $dat .= '[<a href="'.$thisPage.'&page='.$i.'&sort='.$sort.'">'.$i.'</a>] ';
		}
		$dat .= '</td>';
		if ($page < $pageMax) {
			$dat .= '<td><a href="'.$thisPage.'&page='.($page + 1).'&sort='.$sort.'">'.
				_T('next_page').'</a></td>';
		}
		else $dat .= '<td nowrap="nowrap">'._T('last_page').'</td>';
		$dat .= '</tr></table>';
		if ($this->SHOW_FORM) {
			$pte_vals = array('{$DEL_HEAD_TEXT}' => '<input type="hidden" name="mode" value="usrdel">'._T('del_head'),
				'{$DEL_IMG_ONLY_FIELD}' => '<input type="checkbox" name="onlyimgdel" id="onlyimgdel" value="on">',
				'{$DEL_IMG_ONLY_TEXT}' => _T('del_img_only'),
				'{$DEL_PASS_TEXT}' => _T('del_pass'),
				'{$DEL_PASS_FIELD}' => '<input type="password" name="pwd" size="8" value="">',
				'{$DEL_SUBMIT_BTN}' => '<input type="submit" value="'._T('del_btn').'">');
			$dat .= PMCLibrary::getPTEInstance()->ParseBlock('DELFORM', $pte_vals).'</form>';
		}

		$dat .= '</div><br clear="both">';
		foot($dat);
		echo $dat;
	}
}
