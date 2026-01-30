<?php

namespace Kokonotsuba\Modules\search;

use DatabaseConnection;
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
		$this->myPage = $this->getModulePageURL([], false);

		// init the module template engine
		$this->moduleTemplateEngine = $this->initModuleTemplateEngine('ModuleSettings.SEARCH_TEMPLATE', 'kokoimg.tpl');

		$this->moduleContext->moduleEngine->addListener('TopLinks', function(string &$topLinkHookHtml, bool $isReply) {
			$this->onRenderTopLinks($topLinkHookHtml);
		});
	}

	public function onRenderTopLinks(&$topLinkHookHtml){
		$topLinkHookHtml .= ' [<a href="' . htmlspecialchars($this->myPage) . '">' . _T('head_search') . '</a>] ';
	}

	public function ModulePage() {
		$adminMode = isActiveStaffSession();

		// fetch boards
		// for staff it shows all boards
		// for non-staff it only shows listed boards
		$boards = $adminMode ? GLOBAL_BOARD_ARRAY : $this->moduleContext->boardService->getAllListedBoards();

		// build board checkbox HTML
		$isSubmission = isset($_GET['filterSubmissionFlag']);

		$defaultFilters = [
			'searchGeneral' => '',
			'searchComment' => '',
			'searchName' => '',
			'searchEmail' => '',
			'searchSubject' => '',
			'searchFileName' => '',
			'searchPostNumber' => '',
			'searchMatchWord' => $isSubmission ? '' : 'on',
			'searchOpeningPost' => $isSubmission ? '' : 'off',
			'board' => $this->getUidsFromBoards($boards),
		];

		// get filters from request
		$filtersFromRequest = getFiltersFromRequest($this->myPage, $isSubmission, $defaultFilters);

		// build clean url
		$cleanUrl = buildSmartQuery($this->myPage, $defaultFilters, $filtersFromRequest, true);

		$dat = '';

		$dat .= $this->moduleContext->board->getBoardHead("Search");
		
		$dat .= $this->renderReturnLink();
		$dat .= $this->renderSearchHeader();
		$dat .= $this->renderSearchForm($filtersFromRequest, $cleanUrl, $boards);

		$searchFields = [
			'general' => $_GET['searchGeneral'] ?? '',
			'com' => $_GET['searchComment'] ?? '',
			'name' => $_GET['searchName'] ?? '',
			'email' => $_GET['searchEmail'] ?? '',
			'sub' => $_GET['searchSubject'] ?? '',
			'file_name' => $_GET['searchFileName'] ?? '',
			'no' => $_GET['searchPostNumber'] ?? '',
		];

		// get selected boards from request
		$boardUids = $_GET['board'] ?? $this->getUidsFromBoards($boards);

		// convert to array of integers
		if (!is_array($boardUids) && !empty($boardUids)) {
			// convert to array of integers
			$boardUids = explode(' ', $boardUids);
		} 

		if (!empty(array_filter($searchFields))) {
			// fetch database stop words.
			// This is to prevent the engine from trying to search for words it can't index
			// note: this is statically cached per request
			$stopWords = DatabaseConnection::getInstance()->fetchFulltextStopWords();

			// handle search result fetching and displaying
			$dat .= $this->handleSearchResults($this->moduleContext->postSearchService, $stopWords, $searchFields, $boardUids, $cleanUrl, $adminMode);
		}

		// close tag
		$dat .= "</div>";

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
	
	private function renderSearchForm(array $filtersFromRequest, string $cleanUrl, array $boards): string {
		// retrieve previous search values
		$searchGeneral = $filtersFromRequest['searchGeneral'] ?? '';
		$searchComment = $filtersFromRequest['searchComment'] ?? '';
		$searchName = $filtersFromRequest['searchName'] ?? '';
		$searchEmail = $filtersFromRequest['searchEmail'] ?? '';
		$searchSubject = $filtersFromRequest['searchSubject'] ?? '';
		$searchFileName = $filtersFromRequest['searchFileName'] ?? '';
		$searchPostNumber = $filtersFromRequest['searchPostNumber'] ?? '';
		$searchMatchWord = $filtersFromRequest['searchMatchWord'] ?? '';
		$searchOpeningPost = $filtersFromRequest['searchOpeningPost'] ?? '';

		// get all boards as associative arrays
		$allBoards = createAssocArrayFromBoardArray($boards);

		// generate board list checkboxes
		$boardCheckboxHTML = generateBoardListCheckBoxHTML($filtersFromRequest['board'], $allBoards, false);

		// render the form
		return '
			<form action="' . htmlspecialchars($cleanUrl) . '" method="GET">
				<input type="hidden" name="mode" value="module">
				<input type="hidden" name="load" value="search">
				<input type="hidden" name="filterSubmissionFlag" value="1">
				<div id="search">
					<ul>' . _T('search_notice') . '</ul>
					<table>
						<tbody>
							<tr>
								<td class="postblock"><label for="searchGeneral">' . _T('search_target_general') . '</label></td>
								<td>
									<input id="searchGeneral" name="searchGeneral" value="' . htmlspecialchars($searchGeneral) . '">
								</td>
							</tr>
							<tr>
								<td class="postblock"><label for="searchComment">' . _T('search_target_comment') . '</label></td>
								<td>
									<input id="searchComment" name="searchComment" value="' . htmlspecialchars($searchComment) . '">
								</td>
							</tr>
							<tr>
								<td class="postblock"><label for="searchName">' . _T('search_target_name') . '</label></td>
								<td>
									<input id="searchName" name="searchName" value="' . htmlspecialchars($searchName) . '">
								</td>
							</tr>
							<tr>
								<td class="postblock"><label for="searchEmail">' . _T('search_target_email') . '</label></td>
								<td>
									<input id="searchEmail" name="searchEmail" value="' . htmlspecialchars($searchEmail) . '">
								</td>
							</tr>
							<tr>
								<td class="postblock"><label for="searchSubject">' . _T('search_target_subject') . '</label></td>
								<td>
									<input id="searchSubject" name="searchSubject" value="' . htmlspecialchars($searchSubject) . '">
								</td>
							</tr>
							<tr>
								<td class="postblock"><label for="searchFileName">' . _T('search_target_file_name') . '</label></td>
								<td>
									<input id="searchFileName" name="searchFileName" value="' . htmlspecialchars($searchFileName) . '">
								</td>
							</tr>
							<tr>
								<td class="postblock"><label for="searchPostNumber">' . _T('search_target_number') . '</label></td>
								<td>
									<input type="number" min="1" id="searchPostNumber" name="searchPostNumber" value="' . htmlspecialchars($searchPostNumber) . '">
								</td>
							</tr>
							<tr>
								<td class="postblock"><label for="searchMatchWord">' . _T('search_target_matchword') . '</label></td>
								<td>
									<input type="hidden" name="searchMatchWord" value="off">
									<input type="checkbox" id="searchMatchWord" name="searchMatchWord" value="on"' . ($searchMatchWord === 'on' ? 'checked' : '') . '>
								</td>
							</tr>
							<tr>
								<td class="postblock"><label for="searchOpeningPost">' . _T('search_target_opening_post') . '</label></td>
								<td>
									<input type="hidden" name="searchOpeningPost" value="off">
									<input type="checkbox" id="searchOpeningPost" name="searchOpeningPost" value="on"' . ($searchOpeningPost === 'on' ? 'checked' : '') . '>
								</td>
							</tr>
							<tr id="boardrow">
								<td class="postblock"><label for="filterboard">Boards</label><div class="selectlinktextjs" id="boardselectall">[<a>Select all</a>]</div></td>
								<td>
									<ul class="boardFilterList" id="boardFilterList">
										' . $boardCheckboxHTML . '
									</ul>
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
	
	private function handleSearchResults(
		postSearchService $postSearchService, 
		array $stopWords,
		array $fields, 
		array $boardUids, 
		string $searchUrl,
		bool $adminMode
	): string {
		$searchPage = $_GET['page'] ?? 0;
		$searchPostsPerPage = $this->getConfig('ModuleSettings.SEARCH_POSTS_PER_PAGE');

		// determine search method
		$matchWholeWords = isset($_GET['searchMatchWord']) && $_GET['searchMatchWord'] === 'on';

		// only search opening posts
		$openingPostsOnly = isset($_GET['searchOpeningPost']) && $_GET['searchOpeningPost'] === 'on';

		// chop the extension off of the file_name field
		$fields['file_name'] = stripExtension($fields['file_name']);

		// search database
		$hitPosts = $postSearchService->searchPosts($stopWords, $fields, $boardUids, $matchWholeWords, $openingPostsOnly, $searchPage, $searchPostsPerPage) ?? [];
		
		$totalPostHits = $hitPosts['total_posts'] ?? 0;
		$resultList = '';

		$templateValues = [];

		$postRenderer = new postRenderer(
			$this->moduleContext->board, 
			$this->moduleContext->board->loadBoardConfig(), 
			$this->moduleContext->moduleEngine, 
			$this->moduleTemplateEngine, 
			[]);
	
		if ($totalPostHits > 0) {
			$hitPostResultData = $hitPosts['results_data'];
		
			// fetch hit post uids
			$postUids = array_keys($hitPostResultData);

			// get quote links
			$quoteLinks = $this->moduleContext->quoteLinkService->getQuoteLinksByPostUids($postUids);

			// set quote links
			$postRenderer->setQuoteLinks($quoteLinks);

			// config option for displaying all posts as OPs
			$displayThreadedFormat = $this->getConfig('ModuleSettings.DISPLAY_THREADED_FORMAT', false);

			// whether to render all posts with the OP html since searching isn't a threaded format
			$renderAsOp = !$displayThreadedFormat;

			foreach ($hitPostResultData as $hitPost) {
				// extract post data
				$hitPostData = $hitPost['post'];
				
				// get the thread resno for linking
				$hitThreadResno = $hitPostData['post_op_number'];

				// get board object
				$board = searchBoardArrayForBoard($hitPostData['boardUID']);

				// set board/thread name for template
				$templateValues['{$BOARD_THREAD_NAME}'] = getThreadTitle(
					$board->getBoardURL(),
					$board->getBoardTitle()
				);

				// set board/thread name for template
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
					$board->getBoardURL(),
					$renderAsOp);
				$resultList .= $this->moduleTemplateEngine->ParseBlock('THREADSEPARATE', []);
			}

			$out = '<div id="searchresult">' . $resultList . '</div>';
	
			$out .= drawPager($searchPostsPerPage, $totalPostHits, $searchUrl);
			return $out;
		} else {
			return $this->renderNoResultsMessage();
		}
	}
	
	private function renderNoResultsMessage(): string {
		return '<div class="error">' . _T('search_notfound') . '</div>';
	}

	private function getUidsFromBoards(array $boards): array {
		$boardUids = [];
		foreach ($boards as $board) {
			$boardUids[] = $board->getBoardUID();
		}
		return $boardUids;
	}
	
}
