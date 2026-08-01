<?php

namespace Kokonotsuba\Modules\search;

use Kokonotsuba\database\databaseConnection;
use Kokonotsuba\module_classes\abstractModuleMain;
use Kokonotsuba\module_classes\traits\listeners\TopLinksListenerTrait;
use Kokonotsuba\module_classes\moduleEngine;
use Kokonotsuba\containers\moduleEngineContext;
use Kokonotsuba\renderers\postRenderer;
use Kokonotsuba\post\helper\postDateFormatter;
use Kokonotsuba\post\Post;
use Kokonotsuba\post\postSearchService;
use Kokonotsuba\board\board;
use Kokonotsuba\template\templateEngine;

use function Kokonotsuba\libraries\_T;
use function Puchiko\strings\buildSmartQuery;
use function Kokonotsuba\libraries\getFiltersFromRequest;
use function Kokonotsuba\libraries\createAssocArrayFromBoardArray;
use function Kokonotsuba\libraries\html\generateBoardListCheckBoxHTML;
use function Kokonotsuba\libraries\stripExtension;
use function Kokonotsuba\libraries\searchBoardArrayForBoard;
use function Kokonotsuba\libraries\html\getThreadTitle;
use function Kokonotsuba\libraries\html\drawPager;
use function Kokonotsuba\libraries\getBoardsByUIDs;
use function Kokonotsuba\libraries\isActiveStaffSession;

class moduleMain extends abstractModuleMain {
	use TopLinksListenerTrait;

	private readonly string $modulePageUrl;

	// used for rendering posts
	private templateEngine $moduleTemplateEngine;

	public function getName(): string {
		return 'Kokonotsuba Search';
	}

	public function getVersion(): string  {
		return 'Koko BBS Release 1';
	}

	public function initialize(): void {
		$this->modulePageUrl = $this->getModulePageURL([], false);

		// init the module template engine
		$this->moduleTemplateEngine = $this->initModuleTemplateEngine('modules.search.SEARCH_TEMPLATE', 'kokoimg');

		$this->addTopLink($this->modulePageUrl, _T('head_search'));
	}

	public function ModulePage() {
		$adminMode = isActiveStaffSession();

		// fetch boards
		// for staff it shows all boards
		// for non-staff it only shows listed boards
		$boards = $adminMode ? GLOBAL_BOARD_ARRAY : $this->moduleContext->boardService->getAllListedBoards();

		// build board checkbox HTML
		$isSubmission = $this->moduleContext->request->hasParameter('filterSubmissionFlag', 'GET');

		$defaultFilters = [
			'searchGeneral' => '',
			'searchComment' => '',
			'searchName' => '',
			'searchEmail' => '',
			'searchSubject' => '',
			'searchFileName' => '',
			'searchPostNumber' => '',
			'searchTag' => '',
			'searchDateAfter' => '',
			'searchDateBefore' => '',
			'searchMatchWord' => $isSubmission ? '' : 'on',
			'searchOpeningPost' => $isSubmission ? '' : 'off',
			'searchMatchMode' => 'and',
			'board' => $this->getUidsFromBoards($boards),
		];

		// get filters from request
		$filtersFromRequest = getFiltersFromRequest($this->modulePageUrl, $isSubmission, $defaultFilters, $this->moduleContext->request);

		// build clean url
		$cleanUrl = buildSmartQuery($this->modulePageUrl, $defaultFilters, $filtersFromRequest, true);

		$dat = '';

		$dat .= $this->moduleContext->board->getBoardHead("Search");
		
		$dat .= $this->renderReturnLink();
		$dat .= $this->renderSearchHeader();
		$dat .= $this->renderSearchForm($filtersFromRequest, $cleanUrl, $boards);

		$searchFields = [
			'general' => $this->moduleContext->request->getParameter('searchGeneral', 'GET', ''),
			'com' => $this->moduleContext->request->getParameter('searchComment', 'GET', ''),
			'name' => $this->moduleContext->request->getParameter('searchName', 'GET', ''),
			'email' => $this->moduleContext->request->getParameter('searchEmail', 'GET', ''),
			'sub' => $this->moduleContext->request->getParameter('searchSubject', 'GET', ''),
			'file_name' => $this->moduleContext->request->getParameter('searchFileName', 'GET', ''),
			'no' => $this->moduleContext->request->getParameter('searchPostNumber', 'GET', ''),
			'tag' => $this->moduleContext->request->getParameter('searchTag', 'GET', ''),
		];

		// get selected boards from request
		$boardUids = $this->moduleContext->request->getParameter('board', 'GET') ?? $this->getUidsFromBoards($boards);

		// convert to array of integers
		if (!is_array($boardUids) && !empty($boardUids)) {
			// convert to array of integers
			$boardUids = explode(' ', $boardUids);
		}

		// the From/To days are picked in this board's local time; convert them to the
		// UTC bounds the posts table stores
		[$dateAfter, $dateBefore] = $this->buildDateRangeBounds(
			$this->getDayParameter('searchDateAfter'),
			$this->getDayParameter('searchDateBefore')
		);

		// a date range on its own is a valid search - it browses the selected boards
		// over that window - so it counts alongside the keyword fields
		$hasDateRange = $dateAfter !== null || $dateBefore !== null;

		if (!empty(array_filter($searchFields)) || $hasDateRange) {
			// fetch database stop words.
			// This is to prevent the engine from trying to search for words it can't index
			// note: this is statically cached per request
			$stopWords = databaseConnection::getInstance()->fetchFulltextStopWords();

			// handle search result fetching and displaying
			$dat .= $this->handleSearchResults($this->moduleContext->postSearchService, $stopWords, $searchFields, $boardUids, $cleanUrl, $adminMode, $dateAfter, $dateBefore);
		}

		// close tag
		$dat .= "</div>";

		$dat .= $this->moduleContext->board->getBoardFooter();
	
		echo $dat;
	}
	
	/**
	 * Read a date-picker value from the query string as a plain string.
	 *
	 * Array-shaped input (e.g. "?searchDateAfter[]=x") is discarded rather than
	 * coerced, so a hand-built URL cannot provoke a conversion warning.
	 *
	 * @param string $name Query string parameter name.
	 * @return string The submitted day, or '' if absent or not a string.
	 */
	private function getDayParameter(string $name): string {
		$value = $this->moduleContext->request->getParameter($name, 'GET', '');

		return is_string($value) ? $value : '';
	}

	/**
	 * Turn the From/To calendar days from the form into UTC datetime bounds.
	 *
	 * Post timestamps are stored in UTC, but the days the user picks read as days on
	 * this board's clock (its TIME_ZONE offset), so each bound is shifted by that
	 * offset. Results from a board on a different offset are therefore bounded by the
	 * searching board's day, not their own.
	 *
	 * The range is half-open: the "To" day becomes midnight of the day after it, so
	 * that whole day is included.
	 *
	 * @param string $dateAfter  The "From" day as 'Y-m-d', or '' if unset.
	 * @param string $dateBefore The "To" day as 'Y-m-d', or '' if unset.
	 * @return array{0: ?string, 1: ?string} UTC 'Y-m-d H:i:s' bounds; either may be null.
	 */
	private function buildDateRangeBounds(string $dateAfter, string $dateBefore): array {
		// TIME_ZONE is an offset in hours from UTC, and may be fractional (e.g. 5.5)
		$timeZoneOffsetSeconds = (int)round(floatval($this->getConfig('TIME_ZONE', 0)) * 3600);

		return [
			$this->localDayToUtc($dateAfter, 0, $timeZoneOffsetSeconds),
			$this->localDayToUtc($dateBefore, 86400, $timeZoneOffsetSeconds),
		];
	}

	/**
	 * Convert one board-local calendar day into a UTC datetime string.
	 *
	 * @param string $day                   The day as 'Y-m-d'; anything else yields null.
	 * @param int    $dayOffsetSeconds      Seconds past local midnight to land on (86400 for the next day).
	 * @param int    $timeZoneOffsetSeconds The board's offset from UTC, in seconds.
	 * @return string|null A UTC 'Y-m-d H:i:s' string, or null if the day is empty or malformed.
	 */
	private function localDayToUtc(string $day, int $dayOffsetSeconds, int $timeZoneOffsetSeconds): ?string {
		$day = trim($day);

		// the date input posts 'Y-m-d'; a hand-edited URL may not, so check before parsing
		if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) !== 1) {
			return null;
		}

		$localMidnight = strtotime($day . ' 00:00:00 UTC');

		// strtotime accepts the format but still rejects impossible days (e.g. 2026-02-31)
		if ($localMidnight === false || gmdate('Y-m-d', $localMidnight) !== $day) {
			return null;
		}

		return gmdate('Y-m-d H:i:s', $localMidnight + $dayOffsetSeconds - $timeZoneOffsetSeconds);
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
		$searchTag = $filtersFromRequest['searchTag'] ?? '';
		$searchDateAfter = $filtersFromRequest['searchDateAfter'] ?? '';
		$searchDateBefore = $filtersFromRequest['searchDateBefore'] ?? '';
		$searchMatchWord = $filtersFromRequest['searchMatchWord'] ?? '';
		$searchOpeningPost = $filtersFromRequest['searchOpeningPost'] ?? '';
		$searchMatchMode = ($filtersFromRequest['searchMatchMode'] ?? 'and') === 'or' ? 'or' : 'and';

		// get all boards as associative arrays
		$allBoards = createAssocArrayFromBoardArray($boards);

		// generate board list checkboxes
		$boardCheckboxHTML = generateBoardListCheckBoxHTML($filtersFromRequest['board'], $allBoards, false);

		// build tag select if tags are configured
		$tags = $this->getConfig('TAGS', []);
		$tagSelectHTML = '';
		if (!empty($tags)) {
			$tagSelectHTML = '<select name="searchTag" id="searchTag" class="inputtext"><option value="">' . _T('search_tag_all') . '</option>';
			foreach ($tags as $abbr => $label) {
				$safeAbbr = htmlspecialchars($abbr);
				$safeLabel = htmlspecialchars($label);
				$selected = ($searchTag === $abbr) ? ' selected' : '';
				$tagSelectHTML .= '<option value="' . $safeAbbr . '"' . $selected . '>' . $safeLabel . '</option>';
			}
			$tagSelectHTML .= '</select>';
		}

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
									<input id="searchGeneral" class="inputtext" name="searchGeneral" value="' . htmlspecialchars($searchGeneral) . '">
								</td>
							</tr>
							<tr>
								<td class="postblock"><label for="searchComment">' . _T('search_target_comment') . '</label></td>
								<td>
									<input id="searchComment" class="inputtext" name="searchComment" value="' . htmlspecialchars($searchComment) . '">
								</td>
							</tr>
							<tr>
								<td class="postblock"><label for="searchName">' . _T('search_target_name') . '</label></td>
								<td>
									<input id="searchName" class="inputtext" name="searchName" value="' . htmlspecialchars($searchName) . '">
								</td>
							</tr>
							<tr>
								<td class="postblock"><label for="searchEmail">' . _T('search_target_email') . '</label></td>
								<td>
									<input id="searchEmail" class="inputtext" name="searchEmail" value="' . htmlspecialchars($searchEmail) . '">
								</td>
							</tr>
							<tr>
								<td class="postblock"><label for="searchSubject">' . _T('search_target_subject') . '</label></td>
								<td>
									<input id="searchSubject" class="inputtext" name="searchSubject" value="' . htmlspecialchars($searchSubject) . '">
								</td>
							</tr>
							<tr>
								<td class="postblock"><label for="searchFileName">' . _T('search_target_file_name') . '</label></td>
								<td>
									<input id="searchFileName" class="inputtext" name="searchFileName" value="' . htmlspecialchars($searchFileName) . '">
								</td>
							</tr>
							<tr>
								<td class="postblock"><label for="searchPostNumber">' . _T('search_target_number') . '</label></td>
								<td>
									<input type="number" min="1" id="searchPostNumber" name="searchPostNumber" value="' . htmlspecialchars($searchPostNumber) . '">
								</td>
							</tr>
							<tr>
								<td class="postblock"><label for="searchDateAfter">' . _T('search_target_date_from') . '</label></td>
								<td>
									<input type="date" class="inputtext" id="searchDateAfter" name="searchDateAfter" value="' . htmlspecialchars($searchDateAfter) . '">
								</td>
							</tr>
							<tr>
								<td class="postblock"><label for="searchDateBefore">' . _T('search_target_date_to') . '</label></td>
								<td>
									<input type="date" class="inputtext" id="searchDateBefore" name="searchDateBefore" value="' . htmlspecialchars($searchDateBefore) . '">
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
							<tr>
								<td class="postblock"><label for="searchMatchMode">' . _T('search_target_matchmode') . '</label></td>
								<td>
									<select id="searchMatchMode" name="searchMatchMode" class="inputtext">
										<option value="and"' . ($searchMatchMode === 'or' ? '' : ' selected') . '>' . _T('search_matchmode_and') . '</option>
										<option value="or"' . ($searchMatchMode === 'or' ? ' selected' : '') . '>' . _T('search_matchmode_or') . '</option>
									</select>
								</td>
							</tr>
							' . ($tagSelectHTML !== '' ? '
							<tr>
								<td class="postblock"><label for="searchTag">' . _T('search_target_tag') . '</label></td>
								<td>' . $tagSelectHTML . '</td>
							</tr>' : '') . '
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

	/**
	 * Build a unique postRenderer (and moduleEngine) for each board.
	 *
	 * @param Board[] $boards
	 * @return postRenderer[] keyed by board UID
	 */
	private function buildPostRenderers(array $boards, array $quoteLinks): array {
		// init post renderers array
		$postRenderers = [];

		// loop through boards and create 
		foreach ($boards as $board) {
			$boardUID = $board->getBoardUID();

			// Skip if we already created it (safety)
			if (isset($postRenderers[$boardUID])) {
				continue;
			}

			// Build the module engine context
			$postDateFormatter = new postDateFormatter($board->loadBoardConfig()['TIME_ZONE']);

			$moduleEngineContext = new moduleEngineContext(
				$this->moduleContext->config,
				$board->getConfigValue('LIVE_INDEX_FILE'),
				$board->getConfigValue('ModuleList'),
				$this->moduleTemplateEngine,
				$board,
				$postDateFormatter,
				$this->moduleContext->getContainer()
			);

			// moduleEngine is unique per board
			$moduleEngine = new moduleEngine($moduleEngineContext);

			// postRenderer is unique per board
			$postRenderer = new postRenderer(
				$board,
				$this->moduleContext->config,
				$moduleEngine,
				$this->moduleTemplateEngine,
				$quoteLinks,
				$this->moduleContext->request
			);

			// Store it keyed by board UID
			$postRenderers[$boardUID] = $postRenderer;
		}

		return $postRenderers;
	}

	/**
	 * Extract unique board UIDs from hit post result data.
	 *
	 * @param array $hitPostResultData
	 * @return array<int|string> Unique board UIDs
	 */
	private function extractBoardUids(array $hitPostResultData): array {
		$boardUids = [];

		foreach ($hitPostResultData as $row) {
			if (!isset($row['post']) || !($row['post'] instanceof Post)) {
				continue;
			}

			$boardUID = $row['post']->getBoardUID();

			if (!is_string($boardUID) && !is_int($boardUID)) {
				continue;
			}

			// dedupe
			$boardUids[$boardUID] = true;
		}

		return array_keys($boardUids);
	}

	private function handleSearchResults(
		postSearchService $postSearchService, 
		array $stopWords,
		array $fields, 
		array $boardUids,
		string $searchUrl,
		bool $adminMode,
		?string $dateAfter = null,
		?string $dateBefore = null
	): string {
		$searchPage = max(1, intval($this->moduleContext->request->getParameter('page', 'GET', 1)));
		$searchPostsPerPage = $this->getModuleConfig('SEARCH_POSTS_PER_PAGE');

		// determine search method
		$matchWholeWords = $this->moduleContext->request->getParameter('searchMatchWord', 'GET') === 'on';

		// only search opening posts
		$openingPostsOnly = $this->moduleContext->request->getParameter('searchOpeningPost', 'GET') === 'on';

		// how to combine keywords: 'and' (all required, default) or 'or' (any)
		$searchMode = $this->moduleContext->request->getParameter('searchMatchMode', 'GET') === 'or' ? 'or' : 'and';

		// chop the extension off of the file_name field
		$fields['file_name'] = stripExtension($fields['file_name']);

		// search database
		$hitPosts = $postSearchService->searchPosts($stopWords, $fields, $boardUids, $matchWholeWords, $openingPostsOnly, $searchPage, $searchPostsPerPage, $searchMode, $dateAfter, $dateBefore) ?? [];
		
		$totalPostHits = $hitPosts['total_posts'] ?? 0;
		$resultList = '';

		if ($totalPostHits > 0) {
			// extract the plain posts
			$hitPostResultData = $hitPosts['results_data'];

			// fetch hit post uids
			$postUids = array_keys($hitPostResultData);

			// get quote links
			$quoteLinks = $this->moduleContext->quoteLinkService->getQuoteLinksByPostUids($postUids);

			// extract board uids
			$boardUids = $this->extractBoardUids($hitPostResultData);

			// fetch the boards for searched posts
			$boards = getBoardsByUIDs($boardUids);

			// build post renderers (keyed by board_uid)
			$postRenderersForResults = $this->buildPostRenderers($boards, $quoteLinks);

			// config option for displaying all posts as OPs
			$displayThreadedFormat = $this->getModuleConfig('DISPLAY_THREADED_FORMAT', false);

			// whether to render all posts with the OP html since searching isn't a threaded format
			$renderAsOp = !$displayThreadedFormat;

			foreach ($hitPostResultData as $hitPost) {
				// declare template values per post
				$templateValues = [];

				// extract post data
				$hitPostData = $hitPost['post'] ?? null;
				
				// no post data - don't render
				if(empty($hitPostData)) {
					continue;
				}

				// get the board uid
				$boardUid = $hitPostData->getBoardUID();

				// no board uid = invalid search result
				if(!$boardUid) {
					continue;
				}

				// dont render if the post renderer doesn't exist for this board
				if(isset($postRenderersForResults[$boardUid]) === false || !is_object($postRenderersForResults[$boardUid])) {
					continue;
				}

				// now select the post renderer for this board
				$postRenderer = $postRenderersForResults[$boardUid];

				// get the thread resno for linking
				$hitThreadResno = $hitPostData->getOpNumber();

				// get board object
				$board = searchBoardArrayForBoard($hitPostData->getBoardUID());

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
					0,
					true,
					$board->getBoardURL(),
					$renderAsOp);
				$resultList .= $this->moduleTemplateEngine->ParseBlock('THREADSEPARATE', []);
			}

			$out = '<div id="searchresult">' . $resultList . '</div>';
	
			$out .= drawPager($searchPostsPerPage, $totalPostHits, $searchUrl, $this->moduleContext->request);
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
