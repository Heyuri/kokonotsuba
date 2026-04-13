<?php

namespace Kokonotsuba\Modules\threadList;

use Kokonotsuba\error\BoardException;
use Kokonotsuba\module_classes\abstractModuleMain;
use Kokonotsuba\module_classes\traits\listeners\RegistBeforeCommitListenerTrait;
use Kokonotsuba\module_classes\traits\listeners\AboveThreadAreaListenerTrait;
use Kokonotsuba\module_classes\traits\listeners\TopLinksListenerTrait;
use Kokonotsuba\post\Post;

use function Kokonotsuba\libraries\_T;
use function Kokonotsuba\libraries\html\drawPager;
use function Kokonotsuba\libraries\html\generatePostNameHtml;
use function Puchiko\strings\truncateText;

class moduleMain extends abstractModuleMain {
	use RegistBeforeCommitListenerTrait;
	use AboveThreadAreaListenerTrait;
	use TopLinksListenerTrait;

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

		$this->listenRegistBeforeCommit('onBeforeCommit');
		$this->listenAboveThreadArea('onRenderAboveThreadArea');
		$this->addTopLink($this->getModulePageURL([], false), 'Thread list');
	}

	// Automatically checks subject for posts before commit
	public function onBeforeCommit($name, &$email, &$emailForInsertion, &$sub, &$com, &$category, &$age, $files, $isReply): void {
		if ($this->FORCE_SUBJECT && !$isReply && $sub == $this->getConfig('DEFAULT_NOTITLE')) {
			throw new BoardException("A subject/title is required for a new thread.");
		}
	}

	public function onRenderAboveThreadArea(string &$txt, bool $isThreadView) {
		if ($this->SHOW_IN_MAIN && $isThreadView) {
				$dat = '';

				// fetch thread previews - only get OP post
				$threads = $this->moduleContext->threadService->getThreadPreviewsFromBoard($this->moduleContext->board, 0, $this->THREADLIST_NUMBER_IN_MAIN);

				$dat .= '<div class="menu outerbox" id="topiclist"><div class="innerbox">';

				foreach ($threads as $t) {
						$post = $t->getOpeningPost();
						if (!$post) continue;

						$cleanComment = strip_tags($post->getComment());
						$truncatedComment = truncateText($cleanComment, 100);
						$truncatedSubject = truncateText($post->getSubject(), 100);

						$title = $truncatedSubject ?: $truncatedComment;

						$replyCount = $t->getNumberOfPosts() - 1;

						$dat .= sprintf(
								'<span><!--%d--> <a href="%s">%s (%d)</a></span>',
								$post->getNumber(),
								$this->getConfig('LIVE_INDEX_FILE') . '?res=' . $post->getNumber(),
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
		$page = $this->moduleContext->request->hasParameter('page', 'GET') ? intval($this->moduleContext->request->getParameter('page', 'GET')) : 0; // Current page number

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
		$sortingMethod = $this->moduleContext->request->getParameter('sort', 'GET', 'no');

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
			$opPost = $t->getOpeningPost();

			// not found or data invalid (falsey value) - continue
			if(!$opPost || !($opPost instanceof Post)) {
				continue;
			}

			$no = $opPost->getNumber();
			$sub = $opPost->getSubject();
			$name = $opPost->getName();
			$email = $opPost->getEmail();
			$tripcode = $opPost->getTripcode();
			$secure_tripcode = $opPost->getSecureTripcode();
			$capcode = $opPost->getCapcode();
			$now = $opPost->getTimestamp();

			$nameHtml = generatePostNameHtml(
				$this->moduleContext->moduleEngine, 
				$name, 
				$tripcode, 
				$secure_tripcode, 
				$capcode,
				$email,
				$this->getConfig('NOTICE_SAGE', false)
			);

			$rescount = $t->getNumberOfPosts() - 1;
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

		$dat .= drawPager($this->THREADLIST_NUMBER, $listMax, $thisPage . '&sort=' . $sortingMethod, $this->moduleContext->request); 		

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
