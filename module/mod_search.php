<?php
class mod_search extends moduleHelper {
	private $mypage;

	public function __construct(moduleEngine $moduleEngine, boardIO $boardIO, pageRenderer $pageRenderer, pageRenderer $adminPageRenderer) {
		parent::__construct($moduleEngine, $boardIO, $pageRenderer, $adminPageRenderer);		
		$this->mypage = $this->getModulePageURL();
	}

	public function getModuleName(){
		return __CLASS__.' : K! Search';
	}

	public function getModuleVersionInfo() {
		return 'Koko BBS Release 1';
	}

	public function autoHookToplink(&$linkbar, $isreply){
		$linkbar .= ' [<a href="'.$this->mypage.'">'._T('head_search').'</a>] ';
	}

	public function ModulePage(){
		$PIO = PIOPDO::getInstance();
		$globalHTML = new globalHTML($this->board);

		$adminMode = isActiveStaffSession();

		$searchKeyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : ''; // The text you want to search
		$dat = '';
		$globalHTML->head($dat);
		$links = '[<a href="'.$this->config['PHP_SELF2'].'?'.time().'">'._T('return').'</a>]';
		$dat .= $links;
		
		$dat .= '
			<h2 class="theading2">'._T('search_top').'</h2>
				<div class="modulePageContent">
			';

			$keyword = $_GET['keyword'] ?? '';
			$field = $_GET['field'] ?? 'com';
			$method = $_GET['method'] ?? 'AND';
			
			$dat .= '
				<form action="'.$this->config['PHP_SELF'].'" method="get">
					<input type="hidden" name="mode" value="module">
					<input type="hidden" name="load" value="mod_search">
			
					<div id="search">
						<ul>'._T('search_notice').'</ul>
							
						<table>
							<tbody>
								<tr>
									<td class="postblock"><label for="searchKeyword">'._T('search_keyword').'</label></td>
									<td><input type="text" class="inputtext" id="searchKeyword" name="keyword" value="'.htmlspecialchars($keyword, ENT_QUOTES).'"></td>
								</tr>
								<tr>
									<td class="postblock"><label for="searchTarget">'._T('search_target').'</label></td>
									<td>
										<select id="searchTarget" name="field">
											<option value="com"'.($field === 'com' ? ' selected="selected"' : '').'>'._T('search_target_comment').'</option>
											<option value="name"'.($field === 'name' ? ' selected="selected"' : '').'>'._T('search_target_name').'</option>
											<option value="sub"'.($field === 'sub' ? ' selected="selected"' : '').'>'._T('search_target_topic').'</option>
											<option value="no"'.($field === 'no' ? ' selected="selected"' : '').'>'._T('search_target_number').'</option>
										</select>
									</td>
								</tr>
								<tr>
									<td class="postblock"><label for="searchMethod">'._T('search_method').'</label></td>
									<td>
										<select id="searchMethod" name="method">
											<option value="AND"'.($method === 'AND' ? ' selected="selected"' : '').'>'._T('search_method_and').'</option>
											<option value="OR"'.($method === 'OR' ? ' selected="selected"' : '').'>'._T('search_method_or').'</option>
										</select>
									</td>
								</tr>
							</tbody>
						</table>
			
						<div class="buttonSection">
							<input type="submit" value="'._T('search_submit_btn').'">
						</div>
					</div>
				</form>
				<hr>
			';
			

		if($searchKeyword){
			$searchPage = $_GET['page'] ?? 0;
			$searchPostsPerPage = $this->config['SEARCH_POSTS_PER_PAGE'];
			$searchPostOffset = $searchPostsPerPage * $searchPage;
			$quoteLinksFromBoard = getQuoteLinksFromBoard($this->board);

			$searchField = $_GET['field']; // Search target (no:number, name:name, sub:title, com:text)
			$searchMethod = $_GET['method']; // Search method
			$searchKeyword = preg_split('/(　| )+/', strtolower(trim($searchKeyword))); // Search text is cut with spaces
			if ($searchMethod=='REG') $searchMethod = 'AND';
			$hitPosts = $PIO->searchPosts($this->board, $searchKeyword, $searchField, $searchMethod, $searchPostsPerPage, $searchPostOffset) ?? []; // Directly return the matching article content array

			$resultList = '';

			$templateValues = ['{$BOARD_THREAD_NAME}' => ''];

			$postRenderer = new postRenderer($this->board, $this->config, $globalHTML, $this->moduleEngine, $this->templateEngine, $quoteLinksFromBoard);
			
			foreach($hitPosts as $hitPost) {
				$hitPostThread = $hitPost['thread'];
				$hitPostData = $hitPost['post'];

				$hitThreadResno = $hitPostThread['post_op_number'];
				//$hitThreadPosts = $hitPostThread['posts'];

				$postFormExtra = '';
				$warnBeKill = '';
				$warnOld = '';
				$warnHidePost = '';
				$warnEndReply = '';

				// render the post
				$resultList .= $postRenderer->render($hitPostData,
				 $templateValues,
				 $hitThreadResno,
				 false,
				 [$hitPostData],
				 $adminMode,
				 $postFormExtra,
				 $warnBeKill,
				 $warnOld,
				 $warnHidePost,
				 $warnEndReply,
				 '',
				 0,
				 true);
				$resultList .= $this->templateEngine->ParseBlock('THREADSEPARATE', []); 
			}

			$dat .= '<div id="searchresult">';
			$dat .= $resultList;
			$dat .= '</div>';
		}

		echo $dat;
	}
}
