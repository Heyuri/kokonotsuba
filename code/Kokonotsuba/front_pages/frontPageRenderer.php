<?php

namespace Kokonotsuba\front_pages;

use Kokonotsuba\module_classes\frontModuleEngine;
use Kokonotsuba\module_classes\moduleStylesheets;
use Kokonotsuba\news\newsEntry;
use Kokonotsuba\news\newsPage;
use Kokonotsuba\template\templateEngine;

use Kokonotsuba\policy\frontPagePolicy;
use Kokonotsuba\post\helper\postDateFormatter;

use function Kokonotsuba\libraries\_T;
use function Kokonotsuba\libraries\html\generateEarlySettingsScript;
use function Kokonotsuba\libraries\html\getBoardStylesheetsFromConfig;
use function Kokonotsuba\libraries\getCsrfMetaTag;
use function Kokonotsuba\libraries\html\getDefaultFooterLinks;
use function Puchiko\strings\sanitizeStr;

/**
 * Draws the front page: the shell (head, logo, nav, footer) around a news list, one news entry
 * or one page. News and page bodies are trusted HTML written by staff and are not escaped.
 */
final class frontPageRenderer {
	/** Stands in for a random subtitle in cached HTML; swapped for a fresh pick on every serve. */
	public const SUBTITLE_MARKER = '<!--koko:front-subtitle-->';

	/** Stands in for the post counts in cached HTML; swapped for current ones on every serve. */
	public const POST_STATS_MARKER = '<!--koko:front-post-stats-->';

	/** Stands in for the board table in cached HTML; drawn fresh on every serve. */
	public const BOARD_LIST_MARKER = '<!--koko:front-board-list-->';

	/**
	 * @param array $site The SITE.* config group.
	 */
	public function __construct(
		private readonly templateEngine $templateEngine,
		private readonly array $siteConfig,
		private readonly array $site,
		private readonly frontPageLinks $links,
		private readonly frontPageService $frontPageService,
		private readonly frontModuleEngine $moduleEngine,
		private readonly postDateFormatter $dateFormatter,
		private readonly frontPagePolicy $frontPagePolicy,
		private readonly ?frontPostStats $postStats = null,
		private readonly ?frontBoardList $boardList = null,
	) {}

	public function renderNewsPage(newsPage $newsPage, bool $isStaff): string {
		$rows = array_map(fn(newsEntry $entry): array => $this->newsRow($entry), $newsPage->entries);

		$content = $this->templateEngine->ParseBlock('NEWS_LIST', [
			'{$BOARD_LIST}' => $this->boardList !== null ? self::BOARD_LIST_MARKER : '',
			'{$ENTRIES}'    => $rows,
			'{$EMPTY}'      => $rows === [],
			'{$EMPTY_TEXT}' => _T('front_news_empty'),
			'{$PAGER}'      => $this->pager($newsPage),
		]);

		$title = $newsPage->page > 1 ? _T('front_news_page_title', $newsPage->page) : '';

		return $this->renderShell($title, $content, $isStaff);
	}

	public function renderNewsEntry(newsEntry $entry, bool $isStaff): string {
		$content = $this->templateEngine->ParseBlock('NEWS_LIST', [
			'{$BOARD_LIST}' => '',
			'{$ENTRIES}' => [$this->newsRow($entry)],
			'{$EMPTY}'   => false,
			'{$PAGER}'   => '',
		]);

		return $this->renderShell($entry->subject, $content, $isStaff);
	}

	public function renderPage(frontPage $page, bool $isStaff): string {
		$content = $this->templateEngine->ParseBlock('PAGE', [
			'{$SLUG}'  => sanitizeStr($page->slug),
			'{$BODY}'  => $page->body,
			'{$EDIT}'  => $this->frontPagePolicy->canEditPages() ? $this->editLink($this->links->editPage($page->id)) : '',
			'{$DRAFT}' => $page->isPublished ? '' : '<span class="newsDraft">' . _T('front_news_draft') . '</span>',
		]);

		return $this->renderShell($page->title, $content, $isStaff);
	}

	/** A staff page (editor, dashboard) in the front page shell; never cached. */
	public function renderStaffPage(string $title, string $contentHtml): string {
		return $this->renderShell($title, $contentHtml, true);
	}

	public function templateEngine(): templateEngine {
		return $this->templateEngine;
	}

	/** A module's page (?module=name) in the shell. */
	public function renderModulePage(string $title, string $contentHtml, bool $isStaff): string {
		return $this->renderShell($title, $contentHtml, $isStaff);
	}

	/**
	 * Head, logo and the module hooks, up to where the content starts.
	 * The error page's header goes without the hooks, so building it costs no queries.
	 */
	public function renderHead(string $title, bool $isStaff, bool $withInfo = true): string {
		$siteName = (string)($this->site['NAME'] ?? '');
		$pageTitle = $title !== '' ? $title . ' - ' . $siteName : $siteName;
		$moduleHeader = $topNav = $sideNav = $info = '';
		if ($withInfo) {
			$this->moduleEngine->dispatch('FrontHeader', [&$moduleHeader]);
			$this->moduleEngine->dispatch('FrontTopNav', [&$topNav]);
			$this->moduleEngine->dispatch('FrontSideNav', [&$sideNav]);
			$this->moduleEngine->dispatch('FrontInfo', [&$info]);
		}

		return $this->templateEngine->ParseBlock('HEADER', [
			'{$PAGE_TITLE}'              => sanitizeStr($pageTitle),
			'{$HEAD_META}'               => $this->headMeta(),
			'{$META_DESCRIPTION}'        => sanitizeStr((string)($this->site['DESCRIPTION'] ?? '')),
			'{$MODULE_STYLESHEETS_HTML}' => moduleStylesheets::html(),
			'{$MODULE_HEADER_HTML}'      => ($isStaff ? getCsrfMetaTag() : '') . $moduleHeader,
			'{$BOARD_STYLESHEETS}'       => getBoardStylesheetsFromConfig($this->siteConfig),
			'{$JS_DEFAULT_SETTINGS}'     => '<script>window.KOKO_DEFAULT_SETTINGS=' . json_encode((object)($this->siteConfig['JS_DEFAULT_SETTINGS'] ?? []), JSON_HEX_TAG | JSON_HEX_AMP) . ';</script>',
			'{$EARLY_JS_SETTINGS}'       => generateEarlySettingsScript(),
			'{$HOME}'                    => '[<a href="' . sanitizeStr($this->links->home()) . '">' . _T('head_home') . '</a>]',
			'{$OVERBOARD}'               => '',
			'{$HOOKLINKS}'               => '',
			'{$ADMIN}'                   => '[<a href="' . sanitizeStr($this->links->admin()) . '">' . _T('head_admin') . '</a>]',
			'{$TOP_NAV_SECTION_HOOK}'    => $topNav,
			'{$SIDE_NAV_HOOK}'           => $sideNav,
			'{$LOGO}'                    => $this->logo(),
			'{$SHOW_TITLE}'              => !empty($this->site['SHOW_TITLE']),
			'{$TITLE}'                   => sanitizeStr($siteName),
			'{$TITLESUB}'                => $this->subtitleSlot(),
			'{$POST_STATS}'              => $this->postStats !== null ? self::POST_STATS_MARKER : '',
			'{$FRONT_INFO}'              => $info !== '' ? '<div class="frontInfo">' . $info . '</div>' : '',
			'{$TOP_BANNER_AD}'           => '',
		]);
	}

	public function renderFooter(): string {
		return $this->templateEngine->ParseBlock('FOOTER', [
			'{$FOOTER}'      => getDefaultFooterLinks(),
			'{$PAGE_BOTTOM}' => '',
			'{$IS_THREAD}'   => false,
		]);
	}

	/** A random subtitle (trusted HTML), for SUBTITLE_MARKER. */
	public function pickSubtitle(): string {
		$subtitles = array_values((array)($this->site['SUBTITLES'] ?? []));

		return $subtitles === [] ? '' : (string)$subtitles[array_rand($subtitles)];
	}

	/** The post counts block, for POST_STATS_MARKER; '' when the stats are off. */
	public function renderPostStats(): string {
		if ($this->postStats === null) {
			return '';
		}

		$counts = $this->postStats->counts();

		return $this->templateEngine->ParseBlock('POST_STATS', [
			'{$POSTS_HOUR}' => _T('front_posts_this_hour', $counts['hour']),
			'{$POSTS_DAY}'  => _T('front_posts_today', $counts['day']),
		]);
	}

	/** The board table, for BOARD_LIST_MARKER; '' when the list is off or no board is listed. */
	public function renderBoardList(): string {
		if ($this->boardList === null) {
			return '';
		}

		$stats = $this->boardList->hasStats();
		$rows = [];
		foreach ($this->boardList->rows() as $row) {
			$board = $row['board'];
			$rows[] = [
				'{$URL}'         => sanitizeStr($board->getBoardURL()),
				// title and subtitle are stored escaped, as the board header prints them
				'{$NAME}'        => '/' . sanitizeStr($board->getBoardIdentifier()) . '/ - ' . $board->getBoardTitle(),
				'{$DESCRIPTION}' => $board->getBoardSubTitle(),
				'{$STATS}'       => $stats,
				'{$PPH}'         => $row['hour'],
				'{$PPD}'         => $row['day'],
				'{$USERS}'       => $row['users'],
				'{$POSTS}'       => $row['posts'],
			];
		}

		if ($rows === []) {
			return '';
		}

		return $this->templateEngine->ParseBlock('BOARD_LIST', [
			'{$ROWS}'          => $rows,
			'{$STATS}'         => $stats,
			'{$L_BOARD}'       => _T('front_board_list_board'),
			'{$L_DESCRIPTION}' => _T('front_board_list_description'),
			'{$L_PPH}'         => '<abbr title="' . _T('front_board_list_pph_title') . '">' . _T('front_board_list_pph') . '</abbr>',
			'{$L_PPD}'         => '<abbr title="' . _T('front_board_list_ppd_title') . '">' . _T('front_board_list_ppd') . '</abbr>',
			'{$L_USERS}'       => '<abbr title="' . _T('front_board_list_users_title') . '">' . _T('front_board_list_users') . '</abbr>',
			'{$L_POSTS}'       => _T('front_board_list_posts'),
		]);
	}

	private function renderShell(string $title, string $contentHtml, bool $isStaff): string {
		return $this->renderHead($title, $isStaff)
			. $this->templateEngine->ParseBlock('CONTENT', [
				'{$NAV}'     => $this->nav(),
				'{$CONTENT}' => $contentHtml,
			])
			. $this->renderFooter();
	}

	private function editLink(string $url): string {
		return ' <span class="frontEdit">[<a href="' . sanitizeStr($url) . '">' . _T('front_admin_edit') . '</a>]</span>';
	}

	/** A random subtitle quoted in italics */
	private function subtitleSlot(): string {
		if (!empty($this->site['RANDOM_SUBTITLES']) && !empty($this->site['SUBTITLES'])) {
			return '<h3 class="subtitle fronttext">"<i>' . self::SUBTITLE_MARKER . '</i>"</h3>';
		}

		return '<div class="subtitle fronttext">' . sanitizeStr((string)($this->site['DESCRIPTION'] ?? '')) . '</div>';
	}

	private function logo(): string {
		$logo = (string)($this->site['LOGO'] ?? '');
		if ($logo === '') {
			return '';
		}

		return '<a href="' . sanitizeStr($this->links->home()) . '"><img class="frontLogo" src="' . sanitizeStr($logo) . '" alt="' . sanitizeStr((string)($this->site['NAME'] ?? '')) . '"></a>';
	}

	private function headMeta(): string {
		$meta = '';

		$keywords = (string)($this->site['KEYWORDS'] ?? '');
		if ($keywords !== '') {
			$meta .= '<meta name="keywords" content="' . sanitizeStr($keywords) . '">';
		}

		$themeColor = (string)($this->site['THEME_COLOR'] ?? '');
		if ($themeColor !== '') {
			$meta .= '<meta name="theme-color" content="' . sanitizeStr($themeColor) . '">';
		}

		$favicon = (string)($this->site['FAVICON'] ?? '');
		if ($favicon !== '') {
			$meta .= '<link rel="icon" href="' . sanitizeStr($favicon) . '">';
		}

		return $meta;
	}

	/** News, then every page shown in the nav, then SITE.NAV_LINKS. */
	private function nav(): string {
		$links = ['<a href="' . sanitizeStr($this->links->home()) . '">' . _T('front_nav_news') . '</a>'];

		foreach ($this->frontPageService->getNavPages() as $page) {
			$links[] = '<a href="' . sanitizeStr($this->links->page($page->slug)) . '">' . sanitizeStr($page->title) . '</a>';
		}

		foreach ((array)($this->site['NAV_LINKS'] ?? []) as $label => $url) {
			$links[] = '<a href="' . sanitizeStr((string)$url) . '">' . sanitizeStr((string)$label) . '</a>';
		}

		return '<ul class="frontNav"><li>' . implode('</li><li>', $links) . '</li></ul>';
	}

	private function newsRow(newsEntry $entry): array {
		$author = sanitizeStr($entry->displayAuthor());
		if ($author !== '' && $entry->email !== '') {
			$author = '<a href="mailto:' . sanitizeStr($entry->email) . '">' . $author . '</a>';
		}

		return [
			'{$ID}'         => $entry->id,
			'{$LINK}'       => sanitizeStr($this->links->news($entry->id)),
			'{$LINK_TITLE}' => _T('front_news_permalink'),
			'{$SUBJECT}'    => sanitizeStr($entry->subject),
			'{$BYLINE}'     => $author !== '' ? _T('front_news_by', $author) . ' - ' : '',
			'{$DATE}'       => $this->formatDate($entry->createdAt),
			'{$DRAFT}'      => ($entry->isPublished ? '' : ' <span class="newsDraft">' . _T('front_news_draft') . '</span>')
				. ($this->frontPagePolicy->canEditNews() ? $this->editLink($this->links->editNews($entry->id)) : ''),
			'{$BODY}'       => $entry->body,
		];
	}

	/** On the site's clock (the global TIME_ZONE), marked up like a post's date. */
	private function formatDate(string $stored): string {
		try {
			return $this->dateFormatter->formatFromDateString($stored);
		} catch (\InvalidArgumentException) {
			return sanitizeStr($stored);
		}
	}

	private function pager(newsPage $newsPage): string {
		if ($newsPage->pageCount <= 1) {
			return '';
		}

		$parts = [];
		if ($newsPage->page > 1) {
			$parts[] = '[<a href="' . sanitizeStr($this->links->newsPage($newsPage->page - 1)) . '">' . _T('front_news_newer') . '</a>]';
		}
		for ($page = 1; $page <= $newsPage->pageCount; $page++) {
			$parts[] = $page === $newsPage->page
				? '[<b>' . $page . '</b>]'
				: '[<a href="' . sanitizeStr($this->links->newsPage($page)) . '">' . $page . '</a>]';
		}
		if ($newsPage->page < $newsPage->pageCount) {
			$parts[] = '[<a href="' . sanitizeStr($this->links->newsPage($newsPage->page + 1)) . '">' . _T('front_news_older') . '</a>]';
		}

		return '<div class="frontPager">' . implode(' ', $parts) . '</div>';
	}
}
