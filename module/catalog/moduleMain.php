<?php

namespace Kokonotsuba\Modules\catalog;

require_once __DIR__ . '/catalogEntry.php';
require_once __DIR__ . '/catalogRepository.php';
require_once __DIR__ . '/catalogService.php';

use Kokonotsuba\database\databaseConnection;
use Kokonotsuba\error\BoardException;
use Kokonotsuba\module_classes\abstractModuleMain;
use Kokonotsuba\module_classes\traits\listeners\IncludeScriptTrait;
use Kokonotsuba\module_classes\traits\listeners\TopLinksListenerTrait;
use Kokonotsuba\module_classes\traits\listeners\ModuleHeaderListenerTrait;

use function Kokonotsuba\libraries\html\drawPager;
use function Kokonotsuba\libraries\_T;
use function Puchiko\json\renderCachedJsonPage;
use function Puchiko\request\redirect;

class moduleMain extends abstractModuleMain {
	use TopLinksListenerTrait;
	use ModuleHeaderListenerTrait;
	use IncludeScriptTrait;

	private readonly string $staticUrl;
	private readonly string $staticIndexFile;
	private readonly string $modulePageUrl;
	private readonly string $repliesIconUrl;
	private catalogService $catalogService;

	/** Maximum threads per catalog page */
	private int $perPage = 200;

	public function getName(): string {
		return 'K! Catalog';
	}

	public function getVersion(): string {
		return 'Koko BBS Release 2';
	}

	public function initialize(): void {
		$this->staticUrl = $this->getConfig('STATIC_URL');
		$this->staticIndexFile = $this->getConfig('LIVE_INDEX_FILE');
		$this->repliesIconUrl = $this->staticUrl . 'image/replies.png';
		$this->modulePageUrl = $this->getModulePageURL();

		// Initialize the catalog service with its repository
		$this->initCatalogService();

		// Add a "Catalog" link in the top navigation bar
		$this->addTopLink($this->getModulePageURL([], false), _T('head_catalog'));

		// Inject the catalog JSON endpoint URL as a meta tag in the page header
		$this->listenModuleHeader('onGenerateModuleHeader');

		// Include the catalog JS via the module script helper
		$this->registerScript('catalog.js');
	}

	/** Inject the catalog JSON endpoint URL and thread cell template into the page head. */
	private function onGenerateModuleHeader(string &$moduleHeader): void {
		$jsonUrl = $this->getModulePageURL(['output' => 'json']);
		$moduleHeader .= '<meta name="catalogJsonUrl" content="' . $jsonUrl . '">';

		// Parse the CATALOG_THREAD template with empty values to produce
		// a structural clone that JS can populate via DOM after cloning.
		$threadTplContent = $this->moduleContext->templateEngine->ParseBlock('CATALOG_THREAD', [
			'{$THREAD_URL}' => '',
			'{$THUMB_HTML}' => '',
			'{$SUBJECT}' => '',
			'{$POST_INFO_EXTRA}' => '',
			'{$REPLY_COUNT}' => '',
			'{$REPLIES_ICON}' => '',
			'{$COMMENT}' => '',
			'{$IS_STICKY}' => false,
		]);

		$moduleHeader .= $this->generateTemplate('catalogThreadTpl', $threadTplContent);
	}

	/**
	 * Wire up the catalog repository and service.
	 */
	private function initCatalogService(): void {
		$dbSettings = getDatabaseSettings();

		$catalogRepository = new catalogRepository(
			databaseConnection::getInstance(),
			$dbSettings['THREAD_TABLE'],
			$dbSettings['POST_TABLE'],
			$dbSettings['FILE_TABLE'],
			$dbSettings['DELETED_POSTS_TABLE'],
		);

		$this->catalogService = new catalogService($catalogRepository);
	}

	/**
	 * Main catalog page handler.
	 *
	 * Renders the full catalog view with pagination, sorting, and search controls.
	 * Also handles the JSON endpoint for client-side re-sorting.
	 */
	public function ModulePage(): void {
		$board = $this->moduleContext->board;
		$request = $this->moduleContext->request;

		// Check if this is a JSON request for client-side sorting
		if ($request->getParameter('output', 'GET') === 'json') {
			$this->handleJsonRequest();
			return;
		}

		// Resolve the current sort order from POST, GET, or cookie
		$sort = $this->resolveSort();

		// Persist sort and display preferences to cookies when submitted via POST,
		// then redirect (PRG) so refreshing doesn't resubmit the form.
		if ($request->hasParameter('sort_by', 'POST') || $request->hasParameter('cat_cols', 'POST')) {
			$this->persistSortCookie($sort);
			$this->persistDisplayCookies();
			redirect($this->getModulePageURL([], false));
			return;
		}

		// Validate and resolve the current page
		$totalThreads = $this->catalogService->countEntries($board);
		$page = $this->resolveCurrentPage($totalThreads);

		// Fetch catalog entries from the service
		$entries = $this->catalogService->getCatalogEntries($board, $page, $this->perPage, $sort);

		// Build template rows from the entries
		$threadRows = $this->buildTemplateRows($entries);

		// Read display settings from cookies
		$tableClasses = $this->resolveTableClasses();
		$catCols = $this->resolveColumnCount();

		// Render the page
		$this->renderCatalogPage($sort, $threadRows, $tableClasses, $catCols, $totalThreads);
	}

	/**
	 * Handle JSON request for client-side re-sorting.
	 *
	 * Returns a lightweight JSON array of catalog entries so the client
	 * can re-sort without doing a full page reload.
	 */
	private function handleJsonRequest(): void {
		$sort = $this->moduleContext->request->getParameter('sort_by', 'GET', 'bump');

		if (!in_array($sort, ['bump', 'time'], true)) {
			$sort = 'bump';
		}

		$entries = $this->catalogService->getCatalogEntriesAsJson(
			$this->moduleContext->board,
			$this->perPage,
			$sort
		);

		renderCachedJsonPage($entries, 60);
	}

	/**
	 * Determine the current sort order from POST, GET, or cookie fallback.
	 *
	 * @return string 'bump' or 'time'.
	 */
	private function resolveSort(): string {
		$request = $this->moduleContext->request;

		$sort = $request->getParameter('sort_by', 'POST')
			?? $request->getParameter('sort_by', 'GET')
			?? $this->moduleContext->cookieService->get('cat_sort_by', '');

		if (!in_array($sort, ['bump', 'time'], true)) {
			$sort = 'bump';
		}

		return $sort;
	}

	/**
	 * Save sort preference to a cookie when the user submits from the form.
	 *
	 * @param string $sort Current sort value.
	 */
	private function persistSortCookie(string $sort): void {
		$this->moduleContext->cookieService->set('cat_sort_by', $sort, time() + 365 * 86400, '/');
	}

	/**
	 * Save display preferences (full-width, columns) to cookies when submitted via POST.
	 */
	private function persistDisplayCookies(): void {
		$request = $this->moduleContext->request;
		$expiry = time() + 365 * 86400;
		$cookieService = $this->moduleContext->cookieService;

		$fw = $request->getParameter('cat_fw', 'POST') === '1' ? 'true' : 'false';
		$cookieService->set('cat_fw', $fw, $expiry, '/');

		$cols = max(0, intval($request->getParameter('cat_cols', 'POST', 0)));
		$cookieService->set('cat_cols', (string) $cols, $expiry, '/');
	}

	/**
	 * Validate and clamp the current page to valid bounds.
	 *
	 * @param int $totalThreads Total thread count for pagination.
	 * @return int Zero-based page index.
	 * @throws BoardException If the page is out of range.
	 */
	private function resolveCurrentPage(int $totalThreads): int {
		$request = $this->moduleContext->request;

		$page = filter_var($request->getParameter('page', 'GET', 0), FILTER_VALIDATE_INT);
		$page = ($page === false) ? 0 : $page;

		$pageMax = max(0, (int) ceil($totalThreads / $this->perPage) - 1);

		if ($page < 0 || $page > $pageMax) {
			throw new BoardException("Page out of range!");
		}

		return $page;
	}

	/**
	 * Convert catalog entries into template-ready row arrays.
	 *
	 * @param catalogEntry[] $entries Catalog entry DTOs.
	 * @return array[] Array of template row associative arrays.
	 */
	private function buildTemplateRows(array $entries): array {
		$rows = [];
		foreach ($entries as $entry) {
			$rows[] = $entry->toTemplateRow($this->repliesIconUrl);
		}
		return $rows;
	}

	/**
	 * Determine table CSS classes from POST or cookie display preferences.
	 *
	 * @return string Space-separated CSS class string.
	 */
	private function resolveTableClasses(): string {
		$catCols = $this->resolveColumnCount();
		$catFw = $this->resolveFullWidth();

		$classes = [];

		if ($catFw) {
			$classes[] = 'full-width';
		}

		$classes[] = ($catCols > 0) ? 'fixed-cols' : 'auto-cols';

		return implode(' ', $classes);
	}

	/**
	 * Get the column count from POST or cookie.
	 *
	 * @return int Column count (0 = auto).
	 */
	private function resolveColumnCount(): int {
		$request = $this->moduleContext->request;

		$cols = $request->getParameter('cat_cols', 'POST')
			?? $this->moduleContext->cookieService->get('cat_cols', 0);

		return max(0, intval($cols));
	}

	/**
	 * Get the full-width preference from POST or cookie.
	 *
	 * @return bool Whether full-width is enabled.
	 */
	private function resolveFullWidth(): bool {
		$request = $this->moduleContext->request;

		if ($request->hasParameter('sort_by', 'POST')) {
			return $request->getParameter('cat_fw', 'POST') === '1';
		}

		return $this->moduleContext->cookieService->get('cat_fw', 'false') === 'true';
	}

	/**
	 * Render the final catalog HTML page.
	 *
	 * @param string $sort        Current sort key ('bump' or 'time').
	 * @param array  $threadRows  Template row arrays for the FOREACH directive.
	 * @param string $tableClasses CSS classes for the catalog table.
	 * @param int    $catCols     Column count for CSS grid (0 = auto).
	 * @param int    $totalThreads Total thread count for the pager.
	 */
	private function renderCatalogPage(string $sort, array $threadRows, string $tableClasses, int $catCols, int $totalThreads): void {
		$board = $this->moduleContext->board;
		$request = $this->moduleContext->request;

		$catFw = $this->resolveFullWidth();

		// Assemble template values
		$templateValues = [
			'{$STATIC_INDEX_FILE}' => htmlspecialchars($this->staticIndexFile),
			'{$CACHE_BUST}' => time(),
			'{$MODULE_PAGE_URL}' => $this->modulePageUrl,
			'{$SORT_BUMP_SELECTED}' => ($sort === 'bump'),
			'{$SORT_TIME_SELECTED}' => ($sort === 'time'),
			'{$FW_CHECKED}' => $catFw,
			'{$CAT_COLS_VALUE}' => $catCols ?: '',
			'{$TABLE_CLASSES}' => htmlspecialchars($tableClasses),
			'{$CAT_COLS}' => max(1, $catCols),
			'{$THREADS}' => $threadRows,
		];

		// Render page header
		$dat = $board->getBoardHead('Catalog');

		// Open the catalog container
		$dat .= '<div id="catalog">';

		// Render the catalog body from the template
		$dat .= $this->moduleContext->adminPageRenderer->ParseBlock('CATALOG_PAGE', $templateValues);

		// Close container and add separator
		$dat .= '</div><hr>';

		// Add pagination
		$dat .= drawPager($this->perPage, $totalThreads, $this->getModulePageURL([], false), $request);

		// Add page footer
		$dat .= $board->getBoardFooter();

		echo $dat;
	}
}
