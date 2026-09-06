<?php

namespace Kokonotsuba;

use Kokonotsuba\board\board;
use Kokonotsuba\board\boardService;
use Kokonotsuba\containers\appContainer;
use Kokonotsuba\error\softErrorHandler;
use Kokonotsuba\renderers\boardRendererFactory;
use Kokonotsuba\renderers\threadRenderer;
use Kokonotsuba\module_classes\moduleEngine;
use Kokonotsuba\policy\postRenderingPolicy;
use Kokonotsuba\post\postRepository;
use Kokonotsuba\quote_link\quoteLinkService;
use Kokonotsuba\request\request;
use Kokonotsuba\template\templateEngine;
use Kokonotsuba\thread\threadRepository;
use Kokonotsuba\thread\threadService;
use Kokonotsuba\thread\ThreadData;

use function Kokonotsuba\libraries\html\drawPager;
use function Kokonotsuba\libraries\html\getThreadTitle;
use function Kokonotsuba\libraries\_T;
use function Kokonotsuba\libraries\getOrCreateCsrfToken;
use function Kokonotsuba\libraries\getPostUidsFromThreadArrays;
use function Kokonotsuba\libraries\html\generateMassModerateHtml;
use function Kokonotsuba\libraries\html\getBoardStylesheetsFromConfig;
use function Kokonotsuba\libraries\html\generateEarlySettingsScript;
use function Kokonotsuba\libraries\isActiveStaffSession;
use function Puchiko\strings\sanitizeStr;
use function Kokonotsuba\libraries\getCsrfMetaTag;

class overboard {
	private bool $adminMode, $canViewDeleted;

	// renderers are reused across every thread belonging to the same board
	private ?boardRendererFactory $rendererFactory = null;

	public function __construct(
		private board $board,
		private readonly array $config, 
		private readonly softErrorHandler $softErrorHandler,
		private readonly threadRepository $threadRepository,
		private readonly boardService $boardService,
		private readonly postRepository $postRepository,
		private readonly quoteLinkService $quoteLinkService,
		private readonly threadService $threadService,
		private moduleEngine $moduleEngine, 
		private templateEngine $templateEngine,
		private postRenderingPolicy $postRenderingPolicy,
		private readonly appContainer $container,
		private readonly request $request,
	) {
		// whether staff is logged in or not
		$this->adminMode = isActiveStaffSession();
		
		// can view deleted posts
		$this->canViewDeleted = $postRenderingPolicy->viewDeleted();
	}
	
	public function drawOverboardHead(&$dat, $resno = 0) {
		$html = '';
		
		$pte_vals = array('{$RESTO}'=>$resno?$resno:'', '{$IS_THREAD}'=>boolval($resno), '{$IS_STAFF}' => $this->adminMode);

		$pte_vals['{$PAGE_TITLE}'] = strip_tags($this->config['OVERBOARD_TITLE']);

		$pte_vals['{$MODULE_HEADER_HTML}'] = '';

		// dispatch module header hook point for (staff) live frontend
		if($this->adminMode) {
			$pte_vals['{$MODULE_HEADER_HTML}'] .= getCsrfMetaTag();

			$this->moduleEngine->dispatch('ModuleAdminHeader', array(&$pte_vals['{$MODULE_HEADER_HTML}']));

			$pte_vals['{$MODULE_HEADER_HTML}'] .= generateMassModerateHtml($this->templateEngine, $this->moduleEngine, $this->config);
		}
		// dispatch module header hook point for static html
		$this->moduleEngine->dispatch('ModuleHeader', array(&$pte_vals['{$MODULE_HEADER_HTML}']));

		// Generate stylesheet <link> tags from config styles.
		$pte_vals['{$BOARD_STYLESHEETS}'] = getBoardStylesheetsFromConfig($this->config);

		// Inject default JS user settings as JSON for koko.js
		$jsDefaults = $this->config['JS_DEFAULT_SETTINGS'] ?? [];
		$pte_vals['{$JS_DEFAULT_SETTINGS}'] = '<script>window.KOKO_DEFAULT_SETTINGS=' . json_encode((object)$jsDefaults, JSON_HEX_TAG | JSON_HEX_AMP) . ';</script>';

		// Applied at the top of the body so the settings land before the first paint
		$pte_vals['{$EARLY_JS_SETTINGS}'] = generateEarlySettingsScript();

		$html .= $this->templateEngine->ParseBlock('HEADER',$pte_vals);
		$this->moduleEngine->dispatch('Head', array(&$html, $resno)); // "Head" Hook Point

		$pte_vals += array('{$HOME}' => '[<a href="'.$this->config['HOME'].'" target="_top">'._T('head_home').'</a>]',
			'{$STATUS}' => '[<a href="'.$this->config['LIVE_INDEX_FILE'].'?mode=status">'._T('head_info').'</a>]',
			'{$ADMIN}' => '[<a href="'.$this->config['LIVE_INDEX_FILE'].'?mode=admin">'._T('head_admin').'</a>]',
			'{$REFRESH}' => '[<a href="'.$this->config['STATIC_INDEX_FILE'].'?">'._T('head_refresh').'</a>]',
			'{$HOOKLINKS}' => '', '{$TITLE}' => $this->config['OVERBOARD_TITLE'], '{$TITLESUB}' => $this->config['OVERBOARD_SUBTITLE'],
			 '{$LIVE_INDEX_FILE}' => $this->config['LIVE_INDEX_FILE'], '{$BANNER}' => '',
			);
		
		$this->moduleEngine->dispatch('PlaceHolderIntercept', [&$pte_vals]);
		$this->moduleEngine->dispatch('TopLinks', array(&$pte_vals['{$HOOKLINKS}'],$resno)); // "Toplink" Hook Point
		$this->moduleEngine->dispatch('PageTop', array(&$pte_vals['{$BANNER}'])); //"AboveTitle" Hook Point

		// Hook: TopNavSection — as on a board page.
		$pte_vals['{$TOP_NAV_SECTION_HOOK}'] = '';
		$this->moduleEngine->dispatch('TopNavSection', array(&$pte_vals['{$TOP_NAV_SECTION_HOOK}']));

		$html .= $this->templateEngine->ParseBlock('BODYHEAD',$pte_vals);
		
		$pte_vals['{$MODULE_INFO_HOOK}'] = $this->templateEngine->ParseBlock('MODULE_INFO_HOOK', $pte_vals);
		
		$html .= $this->templateEngine->ParseBlock('POST_AREA', $pte_vals);

		$html .= $this->config['OVERBOARD_SUB_HEADER_HTML'];

		$dat .= $html;
		return $html;
	}

	public function drawOverboardThreads(array $filters) {
		$page = $this->request->getParameter('page', null, 1);
		if (!filter_var($page, FILTER_VALIDATE_INT) && $page != 1) $this->softErrorHandler->errorAndExit("Page number was not a valid int.");
		$page = ($page >= 1) ? $page : 1;
		
		$threadsHTML = '';
		$limit = $this->config['OVERBOARD_THREADS_PER_PAGE'];
		$offset = ($page - 1) * $limit;
		
		$templateValues = $this->buildOverboardTemplateValues();

		// add CSRF token to delform for logged-in staff on live pages
		if($this->adminMode) {
			$templateValues['{$DELFORM_CSRF}'] = '<input type="hidden" name="csrf_token" value="' . sanitizeStr(getOrCreateCsrfToken()) . '">';
		}

		$this->moduleEngine->dispatch('AboveThreadsGlobal', array(&$templateValues['{$THREADFRONT}']));
		$this->moduleEngine->dispatch('BelowThreadsGlobal', array(&$templateValues['{$THREADREAR}']));
		
		// If no boards are selected, return prematurely
		if (!$filters['board']) {
			return '<div class="bbls"> <b class="error"> - No threads - </b> </div>';
		}

		$previewCount = $this->config['RE_DEF'];

		$threads = $this->threadService->getFilteredThreads($previewCount, $limit, $offset, $filters, $this->canViewDeleted);
		
		$numberThreadsFiltered = $this->threadRepository->getFilteredThreadCount($filters, $this->canViewDeleted);
		
		$postUidsInPage =  getPostUidsFromThreadArrays($threads);

		// one set of quote links for the whole page, shared by every board's renderer
		$this->getRendererFactory()->setQuoteLinks(
			$this->quoteLinkService->getQuoteLinksByPostUids($postUidsInPage, $this->canViewDeleted)
		);
		
		$boardMap = $this->loadBoardsForThreads($threads);

		$postsByBoard = [];
		foreach ($threads as $thread) {
			$postsByBoard[$thread->getThread()->getBoardUID()][] = $thread->getPosts();
		}
		foreach ($postsByBoard as $boardUid => $postLists) {
			if (isset($boardMap[$boardUid])) {
				$boardPosts = array_merge(...$postLists);
				$boardMap[$boardUid]->getModuleEngine()->dispatch('PostsPrefetch', [&$boardPosts]);
			}
		}

		foreach ($threads as $iterator => $thread) {
			$threadHTML = $this->renderOverboardThread(
				$thread,
				$iterator,
				$boardMap,
				$threads
			);
		
			if (!empty($threadHTML)) {
				$templateValues['{$THREADS}'] .= $threadHTML;
			}
		}
		
		$templateValues['{$BOTTOM_PAGENAV}'] = drawPager($limit, $numberThreadsFiltered, $this->board->getBoardURL(true) . '?mode=overboard', $this->request);
		$threadsHTML .= $this->templateEngine->ParseBlock('MAIN', $templateValues);
		return $threadsHTML;
	}

	private function buildOverboardTemplateValues() {
		return array(
			'{$THREADFRONT}' => '',
			'{$THREADREAR}' => '',
			'{$DELFORM_CSRF}' => '',
			'{$DEL_HEAD_TEXT}' => '<input type="hidden" name="mode" value="usrdel">'._T('del_head'),
			'{$DEL_PASS_TEXT}' => _T('del_pass'),
			'{$DEL_IMG_ONLY_FIELD}' => '<input type="checkbox" name="onlyimgdel" id="onlyimgdel" value="on">',
			'{$DEL_IMG_ONLY_TEXT}' => _T('del_img_only'),
			'{$FORMDAT}' => '',
			'{$DEL_SUBMIT_BTN}' => '<input type="submit" value="'._T('del_btn').'">',
			'{$THREADS}' => '',
			'{$TITLE}' => 'Overboard',
			'{$TITLESUB}' => 'Posts from all kokonotsuba boards',
			'{$BOARD_URL}' => '',
			'{$IS_THREAD}' => false,
			'{$LIVE_INDEX_FILE}' => $this->config['LIVE_INDEX_FILE']
		);
	}

	private function loadBoardsForThreads(array $threads): array {
		// Extract thread.boardUID safely
		$boardUIDs = array_map(fn($t) => $t->getThread()->getBoardUID(), $threads);

		// Remove nulls and duplicates
		$boardUIDs = array_unique(array_filter($boardUIDs));

		// Fetch boards
		$boards = $this->boardService->getBoardsFromUIDs($boardUIDs);
	
		// Map boards by UID
		$boardMap = [];
		foreach ($boards as $board) {
			$boardMap[$board->getBoardUID()] = $board;
		}
	
		return $boardMap;
	}
	
	private function renderOverboardThread(
		ThreadData $thread, 
		int $iterator, 
		array $boardMap, 
		array $threads
	): string {
		$boardUID = $thread->getThread()->getBoardUID();
	
		if (!isset($boardMap[$boardUID]) || $thread->getPosts() === []) {
			return '';
		}
	
		$board = $boardMap[$boardUID];
		$posts = $thread->getPosts();
		$threadToRender = $thread->getThread();

		$threadRenderer = $this->getThreadRenderer($board);

		[$overboardThreadTitle, $crossLink] = $this->buildThreadTitleAndLink($board);
	
		$adminMode = isActiveStaffSession();
		$templateValues = $this->buildTemplateValues($board);
	
		$killSensor = false;
	
		$hiddenReply = $thread->getHiddenReplyCount();
	
		return $threadRenderer->render($threads,
			false,
			$threadToRender,
			$posts,
			$hiddenReply,
			$killSensor,
			$adminMode,
			$iterator,
			$overboardThreadTitle,
			$crossLink,
			$templateValues
		);
	}
	
	/*
	* Get the renderer for a board, building it on first use.
	*
	* Every thread from a board shares one module engine, the same way a board renders
	* its own index. Building one per thread re-instantiated every module - along with
	* an admin template engine and page renderer - for each thread on the page.
	*/
	private function getThreadRenderer(board $board): threadRenderer {
		return $this->getRendererFactory()->threadRendererFor($board);
	}

	private function getRendererFactory(): boardRendererFactory {
		return $this->rendererFactory ??= new boardRendererFactory(
			$this->templateEngine,
			$this->moduleEngine,
			$this->request,
			$this->board,
			$this->container
		);
	}

	private function buildThreadTitleAndLink(board $board): array {
		$boardTitle = $board->getBoardTitle();
		$boardURL = $board->getBoardURL();
		$titleHTML = getThreadTitle($boardURL, $boardTitle);
		return [$titleHTML, $boardURL];
	}
	
	
	private function buildTemplateValues(board $board): array {
		return [
			'{$BOARD_UID}' => $board->getBoardUID(),
		];
	}
	
	
}
