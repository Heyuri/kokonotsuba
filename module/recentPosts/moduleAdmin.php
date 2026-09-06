<?php

namespace Kokonotsuba\Modules\recentPosts;

use Kokonotsuba\module_classes\abstractModuleAdmin;
use Kokonotsuba\module_classes\traits\listeners\PostControlHooksTrait;
use Kokonotsuba\post\Post;
use Kokonotsuba\post\postListFilters;
use Kokonotsuba\renderers\boardRendererFactory;
use Kokonotsuba\template\templateEngine;
use Kokonotsuba\userRole;

use function Kokonotsuba\libraries\_T;
use function Kokonotsuba\libraries\createAssocArrayFromBoardArray;
use function Kokonotsuba\libraries\getRoleLevelFromSession;
use function Kokonotsuba\libraries\html\drawManagePostsFilterForm;
use function Kokonotsuba\libraries\html\drawPager;
use function Kokonotsuba\libraries\html\getThreadTitle;

/**
 * The newest posts across every board, drawn the way their own boards draw them.
 *
 * The same filters and the same query as the manage-posts table (see postListFilters), so a
 * moderator can narrow to an address, a name or a board and read the results as posts - with the
 * attachments, capcodes, IDs and post controls the table has no room for - rather than as rows.
 */
class moduleAdmin extends abstractModuleAdmin {
	use PostControlHooksTrait;

	private string $modulePageUrl;

	/** Renders the posts themselves; the admin page renderer only draws the page around them. */
	private templateEngine $moduleTemplateEngine;

	private ?boardRendererFactory $rendererFactory = null;

	public function getRequiredRole(): userRole {
		return $this->getConfig('AuthLevels.CAN_MANAGE_POSTS', userRole::LEV_JANITOR);
	}

	public function getName(): string {
		return 'Recent posts mod page';
	}

	public function getVersion(): string {
		return 'Koko BBS Release 1';
	}

	public function initialize(): void {
		$this->modulePageUrl = $this->getModulePageURL([], false, true);

		$this->moduleTemplateEngine = $this->initModuleTemplateEngine('modules.recentPosts.RECENT_POSTS_TEMPLATE', 'kokoimg');

		// top level of the sticky nav, next to the manage-posts table it mirrors
		$this->registerLinksAboveBarHook(
			_T('admin_nav_recent_posts_title'),
			$this->modulePageUrl,
			_T('admin_nav_recent_posts')
		);
	}

	public function ModulePage(): void {
		$filters = new postListFilters(
			$this->moduleContext->board,
			$this->moduleContext->postRepository,
			$this->moduleContext->request,
			getRoleLevelFromSession()
		);

		$context = $filters->resolve($this->modulePageUrl);

		$postsPerPage = max(1, (int)$this->getModuleConfig('RECENT_POSTS_PER_PAGE', 30));

		$posts = $this->moduleContext->postRepository->getFilteredPosts(
			$postsPerPage,
			($context['page'] - 1) * $postsPerPage,
			$context['queryFilters'],
			$this->moduleContext->postRenderingPolicy->viewDeleted()
		) ?: [];

		$html = $this->renderFilterForm($context);
		$html .= $this->renderPosts($posts);

		$pager = drawPager(
			$postsPerPage,
			$this->moduleContext->postRepository->postCount($context['queryFilters']),
			$context['cleanUrl'],
			$this->moduleContext->request
		);

		// withStaffHead: the posts carry their deletion checkboxes, so the page needs the
		// [Moderate] window and the CSRF meta tag a live staff board gets.
		echo $this->moduleContext->adminPageRenderer->ParsePage(
			'GLOBAL_ADMIN_PAGE_CONTENT',
			['{$PAGE_CONTENT}' => $html, '{$PAGER}' => $pager],
			true,
			true
		);
	}

	private function renderFilterForm(array $context): string {
		$html = '';

		drawManagePostsFilterForm(
			$html,
			$this->moduleContext->request->getCurrentUrlNoQuery(),
			['mode' => 'module', 'load' => $this->moduleName, 'moduleMode' => 'admin'],
			$context['formFilters'],
			$context['canViewIp'],
			createAssocArrayFromBoardArray($this->moduleContext->boardList),
			_T('recent_posts_filter')
		);

		// The same panel the manage-posts table offers, for whoever the filtered address means
		// something to. The resolved address is passed, not the form's, so a postsFrom lookup
		// lands on the host it resolved to.
		$filteredIp = (string)($context['queryFilters']['ip_address'] ?? '');
		$filteredTokenHash = (string)($context['queryFilters']['visitor_token_hash'] ?? '');
		$this->moduleContext->moduleEngine->dispatch(
			'ManagePostsHostPanel',
			[&$html, &$filteredIp, $context['canViewIp'], &$filteredTokenHash]
		);

		return $html;
	}

	/**
	 * @param Post[] $posts Newest first, from any number of boards.
	 */
	private function renderPosts(array $posts): string {
		if (!$posts) {
			return '<div id="recentPosts"><b class="error" id="no-posts-found"> - ' . _T('recent_posts_none') . ' - </b></div>';
		}

		$rendererFactory = $this->getRendererFactory();

		$rendererFactory->setQuoteLinks(
			$this->moduleContext->quoteLinkService->getQuoteLinksByPostUids(
				array_map(fn(Post $post) => $post->getUid(), $posts)
			)
		);

		$html = '';

		foreach ($posts as $post) {
			$board = $rendererFactory->boardForPost($post);

			// which board a post came from is the one thing its own markup never says
			$templateValues = ['{$BOARD_THREAD_NAME}' => getThreadTitle($board->getBoardURL(), $board->getBoardTitle())];

			// replies are drawn through the OP block so each post stands on its own
			$html .= $rendererFactory->renderPost($post, true, true, false, $templateValues);
			$html .= $this->moduleTemplateEngine->ParseBlock('THREADSEPARATE', []);
		}

		return '<div id="recentPosts">' . $html . '</div>';
	}

	private function getRendererFactory(): boardRendererFactory {
		return $this->rendererFactory ??= new boardRendererFactory(
			$this->moduleTemplateEngine,
			$this->moduleContext->moduleEngine,
			$this->moduleContext->request,
			$this->moduleContext->board,
			$this->moduleContext->getContainer()
		);
	}
}
