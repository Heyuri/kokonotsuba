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

	public function ModulePage() {
		$postSearchService = postSearchService::getInstance();

		$globalHTML = new globalHTML($this->board);
	
		$adminMode = isActiveStaffSession();
	
		$searchKeyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
		$dat = '';
		$globalHTML->head($dat);
		$dat .= $this->renderReturnLink();
		$dat .= $this->renderSearchHeader();
		$dat .= $this->renderSearchForm();
	
		if ($searchKeyword) {
			$dat .= $this->handleSearchResults($postSearchService, $globalHTML, $adminMode, $searchKeyword);
		}

		$globalHTML->foot($dat);
	
		echo $dat;
	}
	
	private function renderReturnLink() {
		return '[<a href="' . $this->config['PHP_SELF2'] . '?' . time() . '">' . _T('return') . '</a>]';
	}
	
	private function renderSearchHeader() {
		return '
			<h2 class="theading2">' . _T('search_top') . '</h2>
			<div class="modulePageContent">
		';
	}
	
	private function renderSearchForm() {
		$keyword = $_GET['keyword'] ?? '';
		$field = $_GET['field'] ?? 'com';
		$method = $_GET['method'] ?? 'AND';
	
		return '
			<form action="' . $this->config['PHP_SELF'] . '" method="get">
				<input type="hidden" name="mode" value="module">
				<input type="hidden" name="load" value="mod_search">
				<div id="search">
					<ul>' . _T('search_notice') . '</ul>
					<table>
						<tbody>
							<tr>
								<td class="postblock"><label for="searchKeyword">' . _T('search_keyword') . '</label></td>
								<td><input type="text" class="inputtext" id="searchKeyword" name="keyword" value="' . htmlspecialchars($keyword, ENT_QUOTES) . '"></td>
							</tr>
							<tr>
								<td class="postblock"><label for="searchTarget">' . _T('search_target') . '</label></td>
								<td>
									<select id="searchTarget" name="field">
										<option value="com"' . ($field === 'com' ? ' selected="selected"' : '') . '>' . _T('search_target_comment') . '</option>
										<option value="name"' . ($field === 'name' ? ' selected="selected"' : '') . '>' . _T('search_target_name') . '</option>
										<option value="sub"' . ($field === 'sub' ? ' selected="selected"' : '') . '>' . _T('search_target_topic') . '</option>
									</select>
								</td>
							</tr>
							<tr>
								<td class="postblock"><label for="searchMethod">' . _T('search_method') . '</label></td>
								<td>
									<select id="searchMethod" name="method">
										<option value="AND"' . ($method === 'AND' ? ' selected="selected"' : '') . '>' . _T('search_method_and') . '</option>
										<option value="OR"' . ($method === 'OR' ? ' selected="selected"' : '') . '>' . _T('search_method_or') . '</option>
									</select>
								</td>
							</tr>
							<tr>
								<td class="postblock"><label for="searchWholeWord">' . _T('search_match_word') . '</label></td>
								<td>
									<input type="checkbox" name="matchWholeWord" '. (isset($_GET['matchWholeWord']) ? 'checked' : '').'>
								</td>
							</tr>
						</tbody>
					</table>
					<div class="buttonSection">
						<input type="submit" value="' . _T('search_submit_btn') . '">
					</div>
				</div>
			</form>
			<hr>
		';
	}
	
	private function handleSearchResults(postSearchService $postSearchService, globalHTML $globalHTML, bool $adminMode, string $searchKeyword): string {
		$searchPage = $_GET['page'] ?? 0;
		$searchPostsPerPage = $this->config['ModuleSettings']['SEARCH_POSTS_PER_PAGE'];
		$searchPostOffset = $searchPostsPerPage * $searchPage;
		$quoteLinksFromBoard = getQuoteLinksFromBoard($this->board);
	
		$searchField = $_GET['field'];
		$searchMethod = $_GET['method'];
		$searchKeywordArray = preg_split('/(　| )+/', strtolower(trim($searchKeyword)));
		if ($searchMethod == 'REG') $searchMethod = 'AND';

		$matchWholeWord = isset($_GET['matchWholeWord']);
	
		$hitPosts = $postSearchService->searchPosts($this->board, $searchKeywordArray, $matchWholeWord, $searchField, $searchMethod, $searchPostsPerPage, $searchPostOffset) ?? [];
		
		$totalPostHits = $hitPosts['total_posts'] ?? 0;
		$resultList = '';

		$templateValues = ['{$BOARD_THREAD_NAME}' => ''];
	
		$postRenderer = new postRenderer($this->board, $this->config, $globalHTML, $this->moduleEngine, $this->templateEngine, $quoteLinksFromBoard);
		$hitPostResultData = $hitPosts['results_data'];
	
		foreach ($hitPostResultData as $hitPost) {
			$hitPostThread = $hitPost['thread'];
			$hitPostData = $hitPost['post'];
			$hitThreadResno = $hitPostThread['post_op_number'];
	
			$resultList .= $postRenderer->render($hitPostData,
				$templateValues,
				$hitThreadResno,
				false,
				[$hitPostData],
				$adminMode,
				'',
				'',
				'',
				'',
				'',
				0,
				true);
			$resultList .= $this->templateEngine->ParseBlock('THREADSEPARATE', []);
		}
	
		if ($totalPostHits > 0) {
			$out = '<div id="searchresult">' . $resultList . '</div>';
	
			$filters = [
				'keyword' => implode('+', $searchKeywordArray),
				'field'   => $searchField,
				'method'  => $searchMethod
			];
	
			$baseUrl = generateFilteredUrl($this->mypage, $filters);
			$out .= $globalHTML->drawPager($searchPostsPerPage, $totalPostHits, $baseUrl);
			return $out;
		} else {
			return $this->renderNoResultsMessage();
		}
	}
	
	private function renderNoResultsMessage(): string {
		return '<div class="error">' . _T('search_notfound') . '</div>';
	}
	
	
}
