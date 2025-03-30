<?php
class mod_threadlist extends ModuleHelper {
	// Configuration variables
	private $THREADLIST_NUMBER, $FORCE_SUBJECT, $SHOW_IN_MAIN, $THREADLIST_NUMBER_IN_MAIN, $SHOW_FORM, $HIGHLIGHT_COUNT = -1;

	public function __construct($PMS) {
		parent::__construct($PMS);

		// Initialize configuration from module settings
		$this->THREADLIST_NUMBER = $this->config['ModuleSettings']['THREADLIST_NUMBER'];
		$this->FORCE_SUBJECT = $this->config['ModuleSettings']['FORCE_SUBJECT'];
		$this->THREADLIST_NUMBER_IN_MAIN = $this->config['ModuleSettings']['THREADLIST_NUMBER_IN_MAIN'];
		$this->SHOW_FORM = $this->config['ModuleSettings']['SHOW_FORM'];
		$this->HIGHLIGHT_COUNT = $this->config['ModuleSettings']['HIGHLIGHT_COUNT'];
		$this->SHOW_IN_MAIN = $this->config['ModuleSettings']['SHOW_IN_MAIN'];

		// Attach language translations
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
				'no_title' => 'Non-titled posts not accepted',
				'link' => 'Thread list',
				'main_title' => 'Thread overview',
				'page_title' => 'List mode',
				'date' => 'Date'
			)
		), 'en_US');
	}

	// Get the module name
	public function getModuleName() {
		return $this->moduleNameBuilder($this->_T('modulename'));
	}

	// Get the module version information
	public function getModuleVersionInfo() {
		return 'Koko BBS Release 1';
	}

	// Automatically checks subject for posts before commit
	public function autoHookRegistBeforeCommit(&$name, &$email, &$sub, &$com,
												&$category, &$age, $dest, $isReply, $imgWH, &$status) {
		$globalHTML = new globalHTML($this->board);
		if ($this->FORCE_SUBJECT && !$isReply && $sub == $this->config['DEFAULT_NOTITLE']) {
			$globalHTML->error($this->_T('no_title'), $dest);
		}
	}

	// Adds a link to the top navigation bar
	public function autoHookToplink(&$linkbar, $isReply) {
		$linkbar .= '[<a href="'.$this->getModulePageURL().'">'.$this->_T('link').'</a>]'."\n";
	}

	// Generates thread list and adds it to the front of the page
	public function autoHookThreadFront(&$txt, $isReply) {
		$PIO = PIOPDO::getInstance();
		$PTE = PTELibrary::getInstance();

		if($this->SHOW_IN_MAIN && !$isReply) {
			$dat = ''; // HTML Buffer
			$plist = $PIO->getThreadListFromBoard($this->board, 0, $this->THREADLIST_NUMBER_IN_MAIN); 
			self::$PMS->useModuleMethods('ThreadOrder', array($isReply, 0, 0, &$plist)); // "ThreadOrder" Hook Point

			// Start building the HTML for the thread list
			$dat .= '<div class="menu outerbox" id="topiclist"><div class="innerbox">';

			foreach ($plist as $i => $post) {
				$post = $PIO->getThreadOpPostsFromList($post); // Fetch post data
				$CommentTitle = (mb_strlen(strip_tags($post[0]['com'])) <= 10) 
					? strip_tags($post[0]['com']) 
					: mb_substr(strip_tags($post[0]['com']), 0, 10, 'UTF-8') . "...";

					$dat.= sprintf('<span><!--%d--> <a href="%s">%s (%d)</a></span>',
					$post[0]['no'],
					$this->config['PHP_SELF'].'?res='.$post[0]['no'], 
					$post[0]['sub'] ? $post[0]['sub'] : $CommentTitle,
					$PIO->getpostCountFromThread($post[0]['thread_uid']) - 1
				);
			}

			$dat .= '</div></div>';
			$dat .= $PTE->ParseBlock('REALSEPARATE', array());
			$txt .= $dat;
		}
	}

	// Helper function to get post counts
	private function _getPostCounts($posts) {
		$PIO = PIOPDO::getInstance();
		$pc = array();

		foreach($posts as $post) {
			$pc[$post] = $PIO->getPostCountFromThread($post);
		}
		return $pc;
	}

	// Sorts an array based on values or keys
	private function _kasort(&$a, $revkey = false, $revval = false) {
		$t = $u = array();

		// Flip the array
		foreach ($a as $k => &$v) {
			if (!isset($t[$v])) $t[$v] = array($k);
			else $t[$v][] = $k;
		}

		// Sort by key or value in reverse order if specified
		if ($revkey) krsort($t);
		else ksort($t);

		foreach ($t as $k => &$vv) {
			if ($revval) rsort($vv);
			else sort($vv);
		}

		// Reconstruct the array
		foreach ($t as $k => &$vv) {
			foreach ($vv as &$v) {
				$u[$v] = $k;
			}
		}

		$a = $u;
	}

	// Handles the module page display and pagination
	public function ModulePage() {
		$PIO = PIOPDO::getInstance();
		$thisPage = $this->getModulePageURL(); // Base position
		$dat = ''; // HTML Buffer
		$listMax = $PIO->threadCountFromBoard($this->board); // Total number of threads
		$pageMax = ceil($listMax / $this->THREADLIST_NUMBER) - 1; // Maximum page number
		$page = isset($_GET['page']) ? intval($_GET['page']) : 0; // Current page number
		$sort = isset($_GET['sort']) ? $_GET['sort'] : 'no'; // Sorting option

		$globalHTML = new globalHTML($this->board);

		// Check if the page number is out of range
		if ($page < 0 || $page > $pageMax) exit('Page out of range.');

		// Sort and fetch threads based on sorting options
		if (strpos($sort, 'post') !== false) {
			$plist = $PIO->fetchThreadListFromBoard($this->board);
			$pc = $this->_getPostCounts($plist);
			$this->_kasort($pc, $sort == 'postdesc', true);

			// Slice the list based on page
			$plist = array_slice(array_keys($pc), $this->THREADLIST_NUMBER * $page, $this->THREADLIST_NUMBER);
		} else {
			$plist = $PIO->fetchThreadListFromBoard($this->board, $this->THREADLIST_NUMBER * $page, $this->THREADLIST_NUMBER, $sort == 'date' ? false : true);
			self::$PMS->useModuleMethods('ThreadOrder', array(0, $page, 0, &$plist)); // "ThreadOrder" Hook Point
			$pc = $this->_getPostCounts($plist);
		}
		$post = $PIO->getThreadOpPostsFromList($plist); // Fetch posts data
		$post_count = count($post);

		// Re-arrange posts based on the sorting option
		if ($sort == 'date' || strpos($sort, 'post') !== false) {
			$mypost = array();
			foreach ($plist as $p) {
				foreach ($post as $k => $v) {
					if ($v['thread_uid'] == $p) {
						$mypost[] = $v;
						unset($post[$k]);
						break;
					}
				}
			}
			$post = $mypost;
		}

		// Start output HTML
		$globalHTML->head($dat);
		$dat .= '<script>
var selectall = "";
function checkall(){
	selectall = selectall ? "" : "checked";
	var inputs = document.getElementsByTagName("input");
	for (x = 0; x < inputs.length; x++) {
		if (inputs[x].type == "checkbox" && parseInt(inputs[x].name)) {
			inputs[x].checked = selectall;
		}
	}
}
</script>';

		$dat .= '<div id="contents">
			[<a href="'.$this->config['PHP_SELF2'].'?'.time().'">'._T('return').'</a>]
			<h2 class="theading">'.$this->_T('page_title').'</h2>'.
			($this->SHOW_FORM ? '<form action="'.$this->config['PHP_SELF'].'" method="post">' : '').'<table id="tableThreadlist" class="postlists"><thead><tr>
			'.($this->SHOW_FORM ? '<th class="colDel"><a href="javascript:checkall()">↓</a></th>' : '').'
			<th class="colNum"><a href="'.$thisPage.'&sort=no">No.'.($sort == 'no' ? ' ▼' : '').'</a></th>
			<th class="colSub">'._T('form_topic').'</th>
			<th class="colName">'._T('form_name').'</th>
			<th class="colReply"><a href="'.$thisPage.'&sort='.($sort == 'postdesc' ? 'postasc' : 'postdesc').'">'._T('reply_btn').($sort == 'postdesc' ? ' ▼' : ($sort == 'postasc' ? ' ▲' : '')).'</a></th>
			<th class="colDate"><a href="'.$thisPage.'&sort=date">'.$this->_T('date').($sort == 'date' ? ' ▼' : '').'</a></th></tr>
		</thead><tbody>
		';

		// Loop through and display each post
		for ($i = 0; $i < $post_count; $i++) {
			$no = $post[$i]['no'];
			$sub = $post[$i]['sub'];
			$name = $post[$i]['name'];
			$now = $post[$i]['now'];
			$thread_uid = $post[$i]['thread_uid'];

			$rescount = $pc[$thread_uid] - 1;
			if ($this->HIGHLIGHT_COUNT > 0 && $rescount > $this->HIGHLIGHT_COUNT) {
				$rescount = '<span class="warning">'.$rescount.'</span>';
			}

			$dat .= '<tr>'.
				($this->SHOW_FORM ? '<td class="colDel"><input type="checkbox" name="'.$no.'" value="delete"></td>' : '').
				'<td class="colNum"><a href="'.$this->config['PHP_SELF'].'?res='.$no.'">'.$no.'</a></td>
				<td class="colSub"><span class="title">'.( $sub ? $sub : 'No Title' ).'</span></td>
				<td class="colName"><span class="name">'.$name.'</span></td>
				<td class="colReply">'.$rescount.'</td>
				<td class="colDate">'.$now.'</td>
			</tr>';
		}

		$dat .= '</tbody></table>
<hr>
<table id="pager"><tr>
';

		// Pagination
		if ($page) {
			$dat .= '<td><a href="'.$thisPage.'&page='.($page - 1).'&sort='.$sort.'">'._T('prev_page').'</a></td>';
		} else {
			$dat .= '<td>'._T('first_page').'</td>';
		}

		$dat .= '<td>';

		for ($i = 0; $i <= $pageMax; $i++) {
			if ($i == $page) {
				$dat .= '[<b>'.$i.'</b>] ';
			} else {
				$dat .= '[<a href="'.$thisPage.'&page='.$i.'&sort='.$sort.'">'.$i.'</a>] ';
			}
		}

		$dat .= '</td>';

		if ($page < $pageMax) {
			$dat .= '<td><a href="'.$thisPage.'&page='.($page + 1).'&sort='.$sort.'">'._T('next_page').'</a></td>';
		} else {
			$dat .= '<td>'._T('last_page').'</td>';
		}

		$dat .= '</tr></table>';

		// Add delete form if necessary
		if ($this->SHOW_FORM) {
			$pte_vals = array(
				'{$DEL_HEAD_TEXT}' => '<input type="hidden" name="mode" value="usrdel">'._T('del_head'),
				'{$DEL_IMG_ONLY_FIELD}' => '<input type="checkbox" name="onlyimgdel" id="onlyimgdel" value="on">',
				'{$DEL_IMG_ONLY_TEXT}' => _T('del_img_only'),
				'{$DEL_PASS_TEXT}' => _T('del_pass'),
				'{$DEL_PASS_FIELD}' => '<input type="password" class="inputtext" name="pwd" id="pwd2" value="">',
				'{$DEL_SUBMIT_BTN}' => '<input type="submit" value="'._T('del_btn').'">'
			);
			$dat .= PTELibrary::getInstance()->ParseBlock('DELFORM', $pte_vals).'</form>';
		}

		$dat .= '</div>';
		$globalHTML->foot($dat);
		echo $dat;
	}
}
