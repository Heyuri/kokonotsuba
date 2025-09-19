<?php

namespace Kokonotsuba\Modules\search;

use Kokonotsuba\ModuleClasses\abstractModuleMain;
use postRenderer;
use postSearchService;
use templateEngine;

class moduleMain extends abstractModuleMain {
	private readonly string $myPage;

	// used for rendering posts
	private templateEngine $moduleTemplateEngine;

	public function getName(): string {
		return 'Kokonotsuba Search';
	}

	public function getVersion(): string  {
		return 'Koko BBS Release 1';
	}

	public function initialize(): void {
		$this->myPage = $this->getModulePageURL();

		// init the module template engine
		$this->moduleTemplateEngine = $this->initModuleTemplateEngine('ModuleSettings.SEARCH_TEMPLATE', 'kokoimg.tpl');

		$this->moduleContext->moduleEngine->addListener('TopLinks', function(string &$topLinkHookHtml, bool $isReply) {
			$this->onRenderTopLinks($topLinkHookHtml);
		});
	}

	public function onRenderTopLinks(&$topLinkHookHtml){
		$topLinkHookHtml .= ' [<a href="' . $this->myPage . '">' . _T('head_search') . '</a>] ';
	}

	public function ModulePage() {
		$adminMode = isActiveStaffSession();
	
		$searchKeyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
		$dat = '';

		$dat .= $this->moduleContext->board->getBoardHead("Search");
		
		$dat .= $this->renderReturnLink();
		$dat .= $this->renderSearchHeader();
		$dat .= $this->renderSearchForm();
	
		if ($searchKeyword) {
			$dat .= $this->handleSearchResults($this->moduleContext->postSearchService, $adminMode, $searchKeyword);
		}

		$dat .= $this->moduleContext->board->getBoardFooter();
	
		echo $dat;
	}
	
	private function renderReturnLink() {
		return '[<a href="' . $this->getConfig('STATIC_INDEX_FILE') . '?' . time() . '">' . _T('return') . '</a>]';
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
			<form action="' . $this->getConfig('LIVE_INDEX_FILE') . '" method="get">
				<input type="hidden" name="mode" value="module">
				<input type="hidden" name="load" value="search">
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
								<td class="postblock"><label for="matchWholeWord">' . _T('search_match_word') . '</label></td>
								<td>
									<input type="checkbox" id="matchWholeWord" name="matchWholeWord" '. (isset($_GET['matchWholeWord']) ? 'checked' : '').'>
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
	
	private function handleSearchResults(postSearchService $postSearchService, bool $adminMode, string $searchKeyword): string {
		$searchPage = $_GET['page'] ?? 0;
		$searchPostsPerPage = $this->getConfig('ModuleSettings.SEARCH_POSTS_PER_PAGE');
		$searchPostOffset = $searchPostsPerPage * $searchPage;
		$quoteLinksFromBoard = $this->moduleContext->quoteLinkService->getQuoteLinksFromBoard($this->moduleContext->board->getBoardUID());
	
		$searchField = $_GET['field'];
		$searchMethod = $_GET['method'];
		$searchKeywordArray = preg_split('/(　| )+/', strtolower(trim($searchKeyword)));
		if ($searchMethod == 'REG') $searchMethod = 'AND';

		$matchWholeWord = isset($_GET['matchWholeWord']);
	
		$hitPosts = $postSearchService->searchPosts($this->moduleContext->board, $searchKeywordArray, $matchWholeWord, $searchField, $searchMethod, $searchPostsPerPage, $searchPostOffset) ?? [];
		
		$totalPostHits = $hitPosts['total_posts'] ?? 0;
		$resultList = '';

		$templateValues = ['{$BOARD_THREAD_NAME}' => ''];
	
		$postRenderer = new postRenderer(
			$this->moduleContext->board, 
			$this->moduleContext->board->loadBoardConfig(), 
			$this->moduleContext->moduleEngine, 
			$this->moduleTemplateEngine, 
			$quoteLinksFromBoard);

		$hitPostResultData = $hitPosts['results_data'];
	
		foreach ($hitPostResultData as $hitPost) {
			$hitPostThread = $hitPost['thread'];
			$hitPostData = $hitPost['post'];
			$hitThreadResno = $hitPostThread['post_op_number'];
	
			// Render all posts with the OP html since searching isn't a threaded format
			$renderAsOp = true;

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
				true,
				'',
				$renderAsOp);
			$resultList .= $this->moduleTemplateEngine->ParseBlock('THREADSEPARATE', []);
		}
	
		if ($totalPostHits > 0) {
			$out = '<div id="searchresult">' . $resultList . '</div>';
	
			$filters = [
				'keyword' => implode('+', $searchKeywordArray),
				'field'   => $searchField,
				'method'  => $searchMethod
			];
	
			$baseUrl = generateFilteredUrl($this->myPage, $filters);
			$out .= drawPager($searchPostsPerPage, $totalPostHits, $baseUrl);
			return $out;
		} else {
			return $this->renderNoResultsMessage();
		}
	}
	
	private function renderNoResultsMessage(): string {
		return '<div class="error">' . _T('search_notfound') . '</div>';
	}
	
	
}
