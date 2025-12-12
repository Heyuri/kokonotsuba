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

		$this->moduleContext->moduleEngine->addListener('RegistBeforeCommit', function ($name, &$email, &$emailForInsertion, &$sub, &$com, &$category, &$age, $file, $isReply, &$status, $thread, &$poster_hash) {
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

				// fetch thread previews - only get OP post
				$threads = $this->moduleContext->threadService->getThreadPreviewsFromBoard($this->moduleContext->board, 0, $this->THREADLIST_NUMBER_IN_MAIN);

				$dat .= '<div class="menu outerbox" id="topiclist"><div class="innerbox">';

				foreach ($threads as $t) {
						if (!isset($t['posts'][0])) continue;
						$post = $t['posts'][0];

						$cleanComment = strip_tags($post['com']);
						$truncatedComment = truncateText($cleanComment, 100);
						$truncatedSubject = truncateText($post['sub'], 100);

						$title = $truncatedSubject ?: $truncatedComment;

						$replyCount = isset($t['number_of_posts'])
								? $t['number_of_posts'] - 1
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

	// Handles the module page display and pagination
	public function ModulePage() {
		$thisPage = $this->getModulePageURL(); // Base position
		$dat = ''; // HTML Buffer
		$listMax = $this->moduleContext->threadRepository->threadCountFromBoard($this->moduleContext->board); // Total number of threads
		$pageMax = ceil($listMax / $this->THREADLIST_NUMBER) - 1; // Maximum page number
		$page = isset($_GET['page']) ? intval($_GET['page']) : 0; // Current page number

		// Check if the page number is out of range
		if ($page < 0 || $page > $pageMax) throw new BoardException('Page out of range.');

		// init sorting descending variable
		// this is so we can change the direction (ASC vs DESC) if we want to for certain sorting options
		// default is true (DESC)
		//
		// (ASC = where sortDescending = false)
		// (DESC = where sortDescending = true)
		$sortDescending = true;

		// init sorting column variable
		$sortingColumn = '';

		// get sorting value from request
		$sortingMethod = $_GET['sort'] ?? 'no';

		// decide which column to use based on the request
		if($sortingMethod === 'no') {
			// set to post op number so it sorts by thread number
			$sortingColumn = 'post_op_number';
		}
		else if($sortingMethod === 'replyMost') {
			// set to be number-of-posts column (generated from JOIN query)
			$sortingColumn = 'number_of_posts';

			// set direction to be DESC so the threads with highest amount of posts get sorted first.
			// it's value will be true anyway but it's good to be explicit
			$sortDescending = true;
		}
		else if($sortingMethod === 'replyLeast') {
			// set to be number-of-posts column (generated from JOIN query)
			$sortingColumn = 'number_of_posts';

			// set direction to be ASC so the threads with the least posts/replies get sorted first
			$sortDescending = false;
		}
		else if($sortingMethod === 'creationDate') {
			// set to thread_creation_date so it
			$sortingColumn = 'thread_created_time';
		}

		// now fetch the threads
		// set preview count to 0 so it just gets OPs.
		// pass column/direction parameters (sanitized by threadService)
		$threads = $this->moduleContext->threadService->getThreadPreviewsFromBoard(
			$this->moduleContext->board, 
			0, 
			$this->THREADLIST_NUMBER, 
			$this->THREADLIST_NUMBER * $page,
			false,
			$sortingColumn,
			$sortDescending
		);

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
			<th class="colNum"><a href="'.$thisPage.'&sort=no">No.'.($sortingMethod == 'no' ? ' ▼' : '').'</a></th>
			<th class="colSub">'._T('form_topic').'</th>
			<th class="colName">'._T('form_name').'</th>
			<th class="colReply"><a href="'.$thisPage.'&sort='.($sortingMethod === 'replyMost' ? 'replyLeast' : 'replyMost').'">'._T('reply_btn').($sortingMethod == 'replyMost' ? ' ▼' : ($sortingMethod == 'replyLeast' ? ' ▲' : '')).'</a></th>
			<th class="colDate"><a href="'.$thisPage.'&sort=creationDate">Date' . ($sortingMethod == 'creationDate' ? ' ▼' : '').'</a></th></tr>
		</thead><tbody>
		';

		// Loop through and display each thread data
		foreach($threads as $t) {
			// get opening post from data
			$opPost = $t['posts'][0] ?? false;

			// not found or data invalid (falsey value) - continue
			if(!$opPost) {
				continue;
			}

			$no = $opPost['no'];
			$sub = $opPost['sub'];
			$name = $opPost['name'];
			$email = $opPost['email'];
			$tripcode = $opPost['tripcode'];
			$secure_tripcode = $opPost['secure_tripcode'];
			$capcode = $opPost['capcode'];
			$now = $opPost['now'];
			$thread_uid = $opPost['thread_uid'];

			$nameHtml = generatePostNameHtml(
				$this->moduleContext->moduleEngine, 
				$name, 
				$tripcode, 
				$secure_tripcode, 
				$capcode,
				$email,
				$this->getConfig('NOTICE_SAGE', false)
			);

			$rescount = $t['number_of_posts'] - 1;
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

		$dat .= drawPager($this->THREADLIST_NUMBER, $listMax, $thisPage . '&sort=' . $sortingMethod); 		

		// Add delete form if necessary
		if ($this->SHOW_FORM) {
			$pte_vals = array(
				'{$DEL_HEAD_TEXT}' => '<input type="hidden" name="mode" value="usrdel">'._T('del_head'),
				'{$DEL_IMG_ONLY_FIELD}' => '<input type="checkbox" name="onlyimgdel" id="onlyimgdel" value="on">',
				'{$DEL_IMG_ONLY_TEXT}' => _T('del_img_only'),
				'{$DEL_PASS_TEXT}' => _T('del_pass'),
				'{$DEL_SUBMIT_BTN}' => '<input type="submit" value="'._T('del_btn').'">'
			);
			$dat .= $this->moduleContext->templateEngine->ParseBlock('DELFORM', $pte_vals).'</form>';
		}

		$dat .= '</div>';
		$dat .= $this->moduleContext->board->getBoardFooter();
		echo $dat;
	}
}
