<?php

namespace Kokonotsuba\Modules\threadList;

use BoardException;
use Kokonotsuba\ModuleClasses\abstractModuleMain;

class moduleMain extends abstractModuleMain {
	// Configuration variables
	private $THREADLIST_NUMBER, $FORCE_SUBJECT, $SHOW_IN_MAIN, $THREADLIST_NUMBER_IN_MAIN, $SHOW_FORM, $HIGHLIGHT_COUNT = -1;

	// Get the module name
	public function getName(): string {
		return 'Thread list';
	}

	// Get the module version information
	public function getVersion(): string  {
		return 'Koko BBS Release 1';
	}

	public function initialize(): void {
		$this->THREADLIST_NUMBER = $this->getConfig('ModuleSettings.THREADLIST_NUMBER');
		$this->FORCE_SUBJECT = $this->getConfig('ModuleSettings.FORCE_SUBJECT');
		$this->THREADLIST_NUMBER_IN_MAIN = $this->getConfig('ModuleSettings.THREADLIST_NUMBER_IN_MAIN');
		$this->SHOW_FORM = $this->getConfig('ModuleSettings.SHOW_FORM');
		$this->HIGHLIGHT_COUNT = $this->getConfig('ModuleSettings.HIGHLIGHT_COUNT');
		$this->SHOW_IN_MAIN = $this->getConfig('ModuleSettings.SHOW_IN_MAIN');

		$this->moduleContext->moduleEngine->addListener('RegistBeforeCommit', function ($name, &$email, &$sub, &$com, &$category, &$age, $file, $isReply, &$status, $thread) {
			$this->onBeforeCommit($sub, $isReply);
		});

		$this->moduleContext->moduleEngine->addListener('AboveThreadArea', function(string &$aboveThreadsHtml, bool $isThreadView) {
			$this->onRenderAboveThreadArea($aboveThreadsHtml, $isThreadView);
		});

		$this->moduleContext->moduleEngine->addListener('TopLinks', function(string &$topLinkHookHtml, bool $isReply) {
			$this->onRenderTopLinks($topLinkHookHtml);
		});
	}

	// Automatically checks subject for posts before commit
	public function onBeforeCommit(string &$sub, bool $isReply): void {
		if ($this->FORCE_SUBJECT && !$isReply && $sub == $this->getConfig('DEFAULT_NOTITLE')) {
			throw new BoardException("A subject/title is required for a new thread.");
		}
	}

	// Adds a link to the top navigation bar
	public function onRenderTopLinks(&$topLinkHookHtml) {
		$topLinkHookHtml .= '[<a href="' . $this->getModulePageURL() . '">Thread list</a>]'."\n";
	}

	public function onRenderAboveThreadArea(string &$txt, bool $isThreadView) {
		if ($this->SHOW_IN_MAIN && $isThreadView) {
				$dat = '';

				$threadUIDs = $this->moduleContext->threadService->getThreadListFromBoard($this->moduleContext->board, 0, $this->THREADLIST_NUMBER_IN_MAIN);
				$this->moduleContext->moduleEngine->dispatch('ThreadOrder', array($isThreadView, 0, 0, &$threadUIDs));

				$opPosts = $this->moduleContext->threadRepository->getFirstPostsFromThreads($threadUIDs);
				$postCounts = $this->moduleContext->threadRepository->getPostCountsForThreads($threadUIDs);

				$dat .= '<div class="menu outerbox" id="topiclist"><div class="innerbox">';

				foreach ($threadUIDs as $threadUID) {
						if (!isset($opPosts[$threadUID])) continue;
						$post = $opPosts[$threadUID];

						$cleanComment = strip_tags($post['com']);
						$title = $post['sub'] ?: (mb_strlen($cleanComment) <= 100
								? $cleanComment
								: mb_substr($cleanComment, 0, 100, 'UTF-8') . '...');

						$replyCount = isset($postCounts[$threadUID])
								? $postCounts[$threadUID] - 1
								: 0;

						$dat .= sprintf(
								'<span><!--%d--> <a href="%s">%s (%d)</a></span>',
								$post['no'],
								$this->getConfig('LIVE_INDEX_FILE') . '?res=' . $post['no'],
								$title,
								$replyCount
						);
				}

				$dat .= '</div></div>';
				$dat .= $this->moduleContext->templateEngine->ParseBlock('REALSEPARATE', []);
				$txt .= $dat;
		}
	}


	// Helper function to get post counts
	private function _getPostCounts($posts) {
		$pc = array();

		foreach($posts as $post) {
			$pc[$post] = $this->moduleContext->threadRepository->getPostCountFromThread($post);
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
		$thisPage = $this->getModulePageURL(); // Base position
		$dat = ''; // HTML Buffer
		$listMax = $this->moduleContext->threadRepository->threadCountFromBoard($this->moduleContext->board); // Total number of threads
		$pageMax = ceil($listMax / $this->THREADLIST_NUMBER) - 1; // Maximum page number
		$page = isset($_GET['page']) ? intval($_GET['page']) : 0; // Current page number
		$sort = isset($_GET['sort']) ? $_GET['sort'] : 'no'; // Sorting option

		// Check if the page number is out of range
		if ($page < 0 || $page > $pageMax) throw new BoardException('Page out of range.');

		// Sort and fetch threads based on sorting options
		if (strpos($sort, 'post') !== false) {
			$plist = $this->moduleContext->threadService->getThreadListFromBoard($this->moduleContext->board);
			$pc = $this->_getPostCounts($plist);
			$this->_kasort($pc, $sort == 'postdesc', true);

			// Slice the list based on page
			$plist = array_slice(array_keys($pc), $this->THREADLIST_NUMBER * $page, $this->THREADLIST_NUMBER);
		} else {
			$plist = $this->moduleContext->threadService->getThreadListFromBoard($this->moduleContext->board, $this->THREADLIST_NUMBER * $page, $this->THREADLIST_NUMBER, true, 'post_op_number');
			$this->moduleContext->moduleEngine->dispatch('ThreadOrder', array(0, $page, 0, &$plist)); // "ThreadOrder" Hook Point
			$pc = $this->_getPostCounts($plist);
		}
		
		$unorderedOPs = $this->moduleContext->threadRepository->getFirstPostsFromThreads($plist);
		$threadOPs = [];
		
		foreach ($plist as $threadUID) {
			if (isset($unorderedOPs[$threadUID])) {
				$threadOPs[] = $unorderedOPs[$threadUID];
			}
		}
		
		// Re-arrange posts based on the sorting option
		if ($sort == 'date' || strpos($sort, 'post') !== false) {
			$mypost = array();
			foreach ($plist as $p) {
				foreach ($threadOPs as $k => $v) {
					if ($v['thread_uid'] == $p) {
						$mypost[] = $v;
						unset($threadOPs[$k]);
						break;
					}
				}
			}
			$threadOPs = $mypost;
		}

		// Start output HTML
		$dat .= $this->moduleContext->board->getBoardHead("Thread list");
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
			[<a href="'.$this->getConfig('STATIC_INDEX_FILE').'?'.time().'">'._T('return').'</a>]
			<h2 class="theading2">Thread list</h2>'.
			($this->SHOW_FORM ? '<form action="'.$this->getConfig('LIVE_INDEX_FILE').'" method="post">' : '').'<div id="tableThreadlistContainer"><table id="tableThreadlist" class="postlists"><thead><tr>
			'.($this->SHOW_FORM ? '<th class="colDel"><a href="javascript:checkall()">↓</a></th>' : '').'
			<th class="colNum"><a href="'.$thisPage.'&sort=no">No.'.($sort == 'no' ? ' ▼' : '').'</a></th>
			<th class="colSub">'._T('form_topic').'</th>
			<th class="colName">'._T('form_name').'</th>
			<th class="colReply"><a href="'.$thisPage.'&sort='.($sort == 'postdesc' ? 'postasc' : 'postdesc').'">'._T('reply_btn').($sort == 'postdesc' ? ' ▼' : ($sort == 'postasc' ? ' ▲' : '')).'</a></th>
			<th class="colDate"><a href="'.$thisPage.'&sort=date">Date' . ($sort == 'date' ? ' ▼' : '').'</a></th></tr>
		</thead><tbody>
		';

		// Loop through and display each post
		foreach($threadOPs as $opPost) {
			$no = $opPost['no'];
			$sub = $opPost['sub'];
			$name = $opPost['name'];
			$tripcode = $opPost['tripcode'];
			$secure_tripcode = $opPost['secure_tripcode'];
			$capcode = $opPost['capcode'];
			$now = $opPost['now'];
			$thread_uid = $opPost['thread_uid'];

			$nameHtml = generatePostNameHtml($this->getConfig('staffCapcodes'), $this->getConfig('CAPCODES'), $name, $tripcode, $secure_tripcode, $capcode);

			$rescount = $pc[$thread_uid] - 1;
			if ($this->HIGHLIGHT_COUNT > 0 && $rescount > $this->HIGHLIGHT_COUNT) {
				$rescount = '<span class="warning">'.$rescount.'</span>';
			}

			$dat .= '<tr>'.
				($this->SHOW_FORM ? '<td class="colDel"><input type="checkbox" name="'.$no.'" value="delete"></td>' : '').
				'<td class="colNum"><a href="'.$this->getConfig('LIVE_INDEX_FILE') . '?res='.$no.'">'.$no.'</a></td>
				<td class="colSub"><span class="title">'.( $sub ? $sub : 'No subject' ).'</span></td>
				<td class="colName"><span class="name">'.$nameHtml.'</span></td>
				<td class="colReply">'.$rescount.'</td>
				<td class="colDate">'.$now.'</td>
			</tr>';
		}

		$dat .= '</tbody></table></div>
<hr>
';

		$dat .= drawPager($this->THREADLIST_NUMBER, $listMax, $thisPage.'&sort='.$sort, function() {}); 		

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
			$dat .= $this->moduleContext->templateEngine->ParseBlock('DELFORM', $pte_vals).'</form>';
		}

		$dat .= '</div>';
		$dat .= $this->moduleContext->board->getBoardFooter();
		echo $dat;
	}
}
