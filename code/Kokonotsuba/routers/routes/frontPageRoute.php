<?php

namespace Kokonotsuba\routers\routes;

use Kokonotsuba\account\staffAccountFromSession;
use Kokonotsuba\error\BoardException;
use Kokonotsuba\front_pages\frontPageCache;
use Kokonotsuba\front_pages\frontPageRenderer;
use Kokonotsuba\front_pages\frontPageService;
use Kokonotsuba\module_classes\frontModuleEngine;
use Kokonotsuba\news\newsService;
use Kokonotsuba\policy\frontPagePolicy;
use Kokonotsuba\request\request;
use Kokonotsuba\routers\route;
use Kokonotsuba\userRole;

use function Kokonotsuba\libraries\_T;

/**
 * The front page's default view: news (?page=N), one news entry (?news=id), a module's page
 * (?module=name) or a page (?p=slug, and the old ?view=slug and ?d=/slug.html). Visitors are served from the file cache.
 */
final class frontPageRoute implements route {
	public function __construct(
		private readonly frontPageRenderer $renderer,
		private readonly frontPageCache $cache,
		private readonly newsService $newsService,
		private readonly frontPageService $frontPageService,
		private readonly frontPagePolicy $frontPagePolicy,
		private readonly array $siteConfig,
		private readonly staffAccountFromSession $staffAccountFromSession,
		private readonly request $request,
		private readonly frontModuleEngine $moduleEngine,
	) {}

	public function handle(): void {
		[$key, $render] = $this->resolveView();

		// only anonymous viewers share the cache: any signed-in role can draw listenStaff() output
		if (!$this->isAnonymous()) {
			$html = $render();
		} else {
			$dependencies = [];
			$this->moduleEngine->dispatch('FrontCacheDependencies', [&$dependencies]);
			$html = $this->cache->get($key, $dependencies);
			if ($html === null) {
				$html = $render();
				$this->cache->put($key, $html);
			}
		}

		$html = str_replace(frontPageRenderer::SUBTITLE_MARKER, $this->renderer->pickSubtitle(), $html);
		if (str_contains($html, frontPageRenderer::POST_STATS_MARKER)) {
			$html = str_replace(frontPageRenderer::POST_STATS_MARKER, $this->renderer->renderPostStats(), $html);
		}
		if (str_contains($html, frontPageRenderer::BOARD_LIST_MARKER)) {
			$html = str_replace(frontPageRenderer::BOARD_LIST_MARKER, $this->renderer->renderBoardList(), $html);
		}

		echo $html;
	}

	/** @return array{0: string, 1: callable(): string} The cache key and the view's renderer. */
	private function resolveView(): array {
		$newsId = $this->intParameter('news');
		if ($newsId !== null) {
			return ['news:' . $newsId, function () use ($newsId): string {
				$entry = $this->newsService->getEntry($newsId, $this->frontPagePolicy->canEditNews())
					?? throw new BoardException(_T('news_not_found'), 404);

				return $this->renderer->renderNewsEntry($entry, $this->isStaff());
			}];
		}

		$moduleName = $this->request->getParameter('module', 'GET');
		if (is_string($moduleName) && $moduleName !== '') {
			return ['module:' . $moduleName, function () use ($moduleName): string {
				$page = $this->moduleEngine->renderModulePage($moduleName)
					?? throw new BoardException(_T('front_page_not_found'), 404);

				return $this->renderer->renderModulePage($page['title'], $page['html'], $this->isStaff());
			}];
		}

		$slug = $this->requestedSlug();
		if ($slug !== null && $slug !== 'news') {
			return ['page:' . $slug, function () use ($slug): string {
				$page = $this->frontPageService->getPage($slug, $this->frontPagePolicy->canEditPages())
					?? throw new BoardException(_T('front_page_not_found'), 404);

				return $this->renderer->renderPage($page, $this->isStaff());
			}];
		}

		$pageNumber = max(1, $this->intParameter('page') ?? 1);

		return ['list:' . $pageNumber, function () use ($pageNumber): string {
			$newsPage = $this->newsService->listPage($pageNumber, (int)($this->siteConfig['SITE']['NEWS_PER_PAGE'] ?? 5), $this->frontPagePolicy->canEditNews());
			if ($pageNumber > $newsPage->pageCount) {
				throw new BoardException(_T('news_not_found'), 404);
			}

			return $this->renderer->renderNewsPage($newsPage, $this->isStaff());
		}];
	}

	/** ?p=, else the old script's ?view= and ?d= (a path like /faq.html). */
	private function requestedSlug(): ?string {
		foreach (['p', 'view'] as $name) {
			$value = $this->request->getParameter($name, 'GET');
			if (is_string($value) && $value !== '') {
				return strtolower(trim($value));
			}
		}

		$path = $this->request->getParameter('d', 'GET');
		if (is_string($path) && preg_match('#/([a-z]+)#i', $path, $match)) {
			return strtolower($match[1]);
		}

		return null;
	}

	private function isAnonymous(): bool {
		return $this->staffAccountFromSession->getRoleLevel()->isLessThan(userRole::LEV_USER);
	}

	private function isStaff(): bool {
		return $this->staffAccountFromSession->getRoleLevel()->isStaff();
	}

	private function intParameter(string $name): ?int {
		$value = $this->request->getParameter($name, 'GET');

		return is_string($value) && ctype_digit($value) ? (int)$value : null;
	}
}
