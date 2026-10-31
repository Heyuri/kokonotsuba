<?php

namespace Kokonotsuba\front_pages;

use Kokonotsuba\action_log\actionLoggerService;
use Kokonotsuba\action_log\actionLogReferences;
use Kokonotsuba\error\BoardException;

use function Kokonotsuba\libraries\_T;

use const Kokonotsuba\GLOBAL_BOARD_UID;

/**
 * Front page pages: slug rules, validated writes, and the action log. Bodies are staff-written
 * HTML and stored as given; who may write them is the caller's check (frontPagePolicy).
 */
final class frontPageService {
	/** Slugs the front page uses for itself. */
	public const RESERVED_SLUGS = ['news'];

	private const MAX_TITLE = 255;
	private const MAX_BODY = 1048576;

	/** @var list<callable(): void> */
	private array $changeListeners = [];

	public function __construct(
		private readonly frontPageRepository $frontPageRepository,
		private readonly actionLoggerService $actionLoggerService,
	) {}

	/** Called after every write, e.g. to forget cached front page HTML. */
	public function onChange(callable $listener): void {
		$this->changeListeners[] = $listener;
	}

	/** @return frontPage[] */
	public function getNavPages(): array {
		return $this->frontPageRepository->listNavPages();
	}

	/** @return frontPage[] */
	public function getAllPages(): array {
		return $this->frontPageRepository->listAllPages();
	}

	/** A page by slug; a draft only when drafts may be seen. Case and padding are ignored. */
	public function getPage(string $slug, bool $includeDrafts): ?frontPage {
		$page = $this->frontPageRepository->findBySlug(self::normalizeSlug($slug));

		return $page !== null && ($page->isPublished || $includeDrafts) ? $page : null;
	}

	public function getPageById(int $id): ?frontPage {
		return $this->frontPageRepository->findById($id);
	}

	/** @return int the new page's id */
	public function createPage(string $slug, string $title, string $body, bool $isPublished, bool $showInNav, int $navOrder, ?int $authorId): int {
		[$slug, $title] = $this->validate($slug, $title, $body, null);

		$id = $this->frontPageRepository->insertPage($slug, $title, $body, $isPublished, $showInNav, $navOrder, $authorId);

		$this->log('Created page ' . self::reference($slug, $title) . ($isPublished ? '' : ' (draft)'), frontPageActionType::CREATE);
		$this->changed();

		return $id;
	}

	/**
	 * A page from an import. Writes no action log line and fires no change event: the importer
	 * reports once for the whole run.
	 * @return ?int the new page's id, null when the slug is taken
	 */
	public function importPage(string $slug, string $title, string $body, bool $showInNav, int $navOrder): ?int {
		$slug = self::normalizeSlug($slug);
		if ($this->frontPageRepository->slugTaken($slug)) {
			return null;
		}

		[$slug, $title] = $this->validate($slug, $title, $body, null);

		return $this->frontPageRepository->insertPage($slug, $title, $body, true, $showInNav, $navOrder, null);
	}

	public function updatePage(int $id, string $slug, string $title, string $body, bool $isPublished, bool $showInNav, int $navOrder): void {
		$this->requirePage($id);
		[$slug, $title] = $this->validate($slug, $title, $body, $id);

		$this->frontPageRepository->updatePage($id, $slug, $title, $body, $isPublished, $showInNav, $navOrder);

		$this->log('Edited page ' . self::reference($slug, $title), frontPageActionType::EDIT);
		$this->changed();
	}

	public function deletePage(int $id): void {
		$page = $this->requirePage($id);

		$this->frontPageRepository->deletePage($id);

		$this->log("Deleted page '{$page->slug}' ({$page->title})", frontPageActionType::DELETE);
		$this->changed();
	}

	public static function normalizeSlug(string $slug): string {
		return strtolower(trim($slug));
	}

	public static function isValidSlug(string $slug): bool {
		return preg_match('/^[a-z0-9_-]{1,64}$/', $slug) === 1 && !in_array($slug, self::RESERVED_SLUGS, true);
	}

	private function requirePage(int $id): frontPage {
		return $this->frontPageRepository->findById($id) ?? throw new BoardException(_T('front_page_not_found'), 404);
	}

	/** @return array{string, string} normalized slug, trimmed title */
	private function validate(string $slug, string $title, string $body, ?int $exceptId): array {
		$slug = self::normalizeSlug($slug);
		$title = trim($title);

		if (!self::isValidSlug($slug)) {
			throw new BoardException(_T('front_page_slug_invalid', implode(', ', self::RESERVED_SLUGS)));
		}
		if ($this->frontPageRepository->slugTaken($slug, $exceptId)) {
			throw new BoardException(_T('front_page_slug_taken', $slug));
		}
		if ($title === '' || mb_strlen($title) > self::MAX_TITLE) {
			throw new BoardException(_T('front_page_title_invalid', self::MAX_TITLE));
		}
		if (strlen($body) > self::MAX_BODY) {
			throw new BoardException(_T('front_page_body_too_long'));
		}

		return [$slug, $title];
	}

	private static function reference(string $slug, string $title): string {
		return actionLogReferences::reference('page', $slug, "'{$slug}' ({$title})");
	}

	private function log(string $action, frontPageActionType $type): void {
		$this->actionLoggerService->logAction($action, GLOBAL_BOARD_UID, $type);
	}

	private function changed(): void {
		foreach ($this->changeListeners as $listener) {
			$listener();
		}
	}
}
