<?php

namespace Kokonotsuba\Modules\catalog;

use BoardException;
use Kokonotsuba\ModuleClasses\abstractModuleMain;

class moduleMain extends abstractModuleMain {
	private readonly string $staticUrl;
	private readonly string $staticIndexFile;
	private $myPage;
	private $PAGE_DEF = 200;
	private $RESICON = -1;

	public function getName(): string {
		return 'K! Catalog';
	}

	public function getVersion(): string {
		return 'Koko BBS Release 1';
	}

	public function initialize(): void {
		$this->staticUrl = $this->getConfig('STATIC_URL');
		$this->staticIndexFile = $this->getConfig('LIVE_INDEX_FILE');
		$this->RESICON = $this->staticUrl . 'image/replies.png';
		$this->myPage = $this->getModulePageURL();

		$this->moduleContext->moduleEngine->addListener('TopLinks', function(string &$topLinkHookHtml, bool $isReply) {
			$this->onRenderTopLink($topLinkHookHtml);
		});
	}

	private function onRenderTopLink(string &$linkbar): void {

		$linkbar .= ' [<a href="'.$this->myPage.'">' . _T('head_catalog') . '</a>] ';
	}

	private function drawSortOptions($sort = 'bump') {
		$timeSelected = $bumpSelected = '';
		if ($sort == 'bump') {
			$bumpSelected = ' selected';
		} else if ($sort == 'time') {
			$timeSelected = ' selected';
		}
		return '
			<form id="catalogSortForm" action="'. $this->myPage .'" method="post">
				<span>Sort by:</span>
				<select name="sort_by">
					<option value="bump"'.$bumpSelected.'>Bump order</option>
					<option value="time"'.$timeSelected.'>Creation date</option>
				</select>
				<input type="submit" value="Apply">
			</form>';
	}

	public function ModulePage(){
		$dat = '';

		$list_max = $this->moduleContext->threadRepository->threadCountFromBoard($this->moduleContext->board);
		$page = filter_var($_GET['page'] ?? 0, FILTER_VALIDATE_INT);
		$page = ($page === false) ? 0 : $page;
		$page_max = ceil($list_max / $this->PAGE_DEF) - 1;

		$sort = $_POST['sort_by'] ?? $_GET['sort_by'] ?? $_COOKIE['cat_sort_by'] ?? '';
		if (!in_array($sort, array('bump', 'time'))) {
			$sort = 'bump';
		}

		if($page < 0 || $page > $page_max) {
			throw new BoardException("Page out of range!");
		}

		if (isset($_POST['sort_by'])) {
			setcookie('cat_sort_by', $sort, time() + 365 * 86400);
		}

		$sortingColumn = 'last_bump_time';
 
		//sort threads. If sort is set to bump nothing will change because that is the default order returned by fetchThreadList
		switch($sort) {
			case 'time':
				$sortingColumn = 'thread_created_time';
			break;
			case 'bump':
			default:
				$sortingColumn = 'last_bump_time';
			break;
		}

		$cat_cols = $_COOKIE['cat_cols']??0;
		$cat_fw = ($_COOKIE['cat_fw']??'false')=='true';
		if (!$cat_cols=intval($cat_cols))
			$cat_cols = 'auto';

		$dat .= $this->moduleContext->board->getBoardHead('Catalog');

		$dat.= '
		<script src="' . $this->staticUrl . 'js/catalog.js"></script>
		<div id="catalog">
[<a href="' . $this->staticIndexFile . '?'.time().'">Return</a>]
<h2 class="theading2">Catalog</h2> '.$this->drawSortOptions($sort).'';
				
		$dat.= '<table id="catalogTable" class="' . ($cat_fw ? 'full-width' : '') . ' ' . ($cat_cols === 'auto' ? 'auto-cols' : 'fixed-cols') . '"><tbody><tr>';

		$threads = $this->moduleContext->postService->getThreadPreviewsFromBoard($this->moduleContext->board, 0, $this->PAGE_DEF, $page * $this->PAGE_DEF, $sortingColumn);
		
		foreach($threads as $i=>$thread){
			$threadPosts = $thread['posts'];
			
			$opPost = $threadPosts[0];

			$firstKey = array_key_first($opPost['attachments']);

			$opAttachment = $opPost['attachments'][$firstKey] ?? null;

			if(!$opPost) continue;

			// get OP subject
			$subject = $opPost['sub'] ?? '';

			$threadNumber = $thread['thread']['post_op_number'];
			if ( ($cat_cols!='auto') && !($i%intval($cat_cols)) )
				$dat.= '</tr><tr>';

			
			$arrLabels = array('{$IMG_BAR}'=>'', '{$POSTINFO_EXTRA}'=>'', '{$IMG_SRC}' => '');
			$this->moduleContext->moduleEngine->dispatch('ThreadPost', array(&$arrLabels, $opPost, $threadPosts, false)); // "ThreadPost" Hook Point

			$res = $thread['post_count'] - 1; // subtract by one so we dont count the OP
			$dat.= '<td class="thread">
	<!--<div class="filesize">'.$arrLabels['{$IMG_BAR}'].'</div>-->
	<a href="'.$this->moduleContext->board->getBoardThreadURL($threadNumber).'">'.
	(attachmentFileExists($opAttachment) ? '<img src="' . getAttachmentUrl($opAttachment, true) . '" width="'.min(150, $opAttachment['fileWidth']).'" class="thumb" alt="Thumbnail">' : '***').
	'</a>
	<div class="catPostInfo"><span class="title">' . $subject . '</span>'.
		$arrLabels['{$POSTINFO_EXTRA}'].'&nbsp;<span title="Replies"><img src="'.$this->RESICON.'" class="icon" alt="Replies"> '.$res.'</span></div>
	<div class="catComment">'.$opPost['com'].'</div>
</td>';
		}

		$dat .= '</tbody></table></div><hr>';
		$dat .= drawPager($this->PAGE_DEF,$list_max, $this->myPage);
		$dat .= $this->moduleContext->board->getBoardFooter();
		echo $dat;
	}


}
