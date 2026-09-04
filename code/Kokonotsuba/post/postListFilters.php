<?php

namespace Kokonotsuba\post;

use Kokonotsuba\interfaces\IBoard;
use Kokonotsuba\request\request;
use Kokonotsuba\userRole;

use function Kokonotsuba\libraries\getFiltersFromRequest;
use function Puchiko\strings\buildSmartQuery;

/**
 * The filter state a staff page listing posts from every board works from.
 *
 * The manage-posts table and the recent-posts feed take the same filters off the query string,
 * drop the ones the viewer's role may not use, and page postRepository with them - so reading them
 * lives here rather than once per page.
 */
final class postListFilters {
	public function __construct(
		private readonly IBoard $board,
		private readonly postRepository $postRepository,
		private readonly request $request,
		private readonly userRole $roleLevel,
	) {}

	/** What a page starts from: this board only, every other filter blank. */
	public function getDefaultFilters(): array {
		return [
			'ip_address' => '',
			'visitor_token_hash' => '',
			'post_name' => '',
			'tripcode' => '',
			'capcode' => '',
			'subject' => '',
			'comment' => '',
			'board' => [$this->board->getBoardUID()],
			'date_before' => '',
			'date_after' => '',
			'postsFrom' => ''
		];
	}

	public function canViewIp(): bool {
		return $this->roleLevel->isAtLeast(
			$this->board->getConfigValue('AuthLevels.CAN_VIEW_IP_ADDRESSES', userRole::LEV_JANITOR)
		);
	}

	public function canViewHashedIp(): bool {
		return $this->roleLevel->isAtLeast(
			$this->board->getConfigValue('AuthLevels.CAN_ONLY_VIEW_POSTS_FROM_USER', userRole::LEV_JANITOR)
		);
	}

	/**
	 * Read this request's filters.
	 *
	 * 'formFilters' is what the form redraws; 'queryFilters' is what the query runs, and differs
	 * in one place: a postsFrom lookup resolves to the address that post was made from, which the
	 * form never shows and the URL never carries.
	 *
	 * @param string $baseUrl The page's own URL, which the filters are appended to.
	 * @return array{formFilters: array, queryFilters: array, cleanUrl: string, page: int,
	 *               canViewIp: bool, canViewHashedIp: bool}
	 */
	public function resolve(string $baseUrl): array {
		$isSubmission = $this->request->hasParameter('filterSubmissionFlag', 'GET');
		$defaultFilters = $this->getDefaultFilters();

		$formFilters = getFiltersFromRequest($baseUrl, $isSubmission, $defaultFilters, $this->request);

		$canViewIp = $this->canViewIp();

		// The address and the browser label are only ever shown to raw-IP staff, so only they may
		// filter on either.
		if (!$canViewIp) {
			$formFilters['ip_address'] = '';
			$formFilters['visitor_token_hash'] = '';
		}

		$queryFilters = $formFilters;

		// "All posts from this poster" is a post, not an address - resolved here so the URL and
		// the form stay clear of the address it lands on.
		$postsFrom = $this->request->getParameter('postsFrom', 'GET');
		if ($postsFrom && is_numeric($postsFrom)) {
			$queryFilters['ip_address'] = $this->postRepository->resolveHostFromPostUid((int)$postsFrom);
		}

		$cleanUrl = buildSmartQuery($baseUrl, $defaultFilters, $formFilters, true);

		if ($postsFrom) {
			$cleanUrl .= '&postsFrom=' . urlencode($postsFrom);
		}

		return [
			'formFilters' => $formFilters,
			'queryFilters' => $queryFilters,
			'cleanUrl' => $cleanUrl,
			'page' => max(1, (int) $this->request->getParameter('page', default: 1)),
			'canViewIp' => $canViewIp,
			'canViewHashedIp' => $this->canViewHashedIp(),
		];
	}
}
