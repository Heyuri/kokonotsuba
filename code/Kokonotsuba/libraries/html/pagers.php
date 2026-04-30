<?php

namespace Kokonotsuba\libraries\html;

use Kokonotsuba\error\BoardException;
use Kokonotsuba\request\request;

/**
 * Read a 1-based page number from the request, defaulting to 1.
 */
function getPageFromRequest(request $request, string $pageParam = 'page'): int {
	return ($request->hasParameter($pageParam) && is_numeric($request->getParameter($pageParam)))
		? max(1, (int)$request->getParameter($pageParam))
		: 1;
}

/**
 * Convert a 1-based page number to a 0-based database offset.
 */
function pageToOffset(int $page, int $entriesPerPage): int {
	return max(0, $page - 1) * $entriesPerPage;
}

function validateAndClampPagination(int $entriesPerPage, int $totalEntries, int $currentPage): array {
	if ((filter_var($totalEntries, FILTER_VALIDATE_INT) === false || $totalEntries < 0) ||
		(filter_var($entriesPerPage, FILTER_VALIDATE_INT) === false || $entriesPerPage < 0)) {
		throw new BoardException("Total entries must be a valid non-negative integer.");
	}

	$totalPages = (int) ceil($totalEntries / $entriesPerPage);

	if (filter_var($currentPage, FILTER_VALIDATE_INT) === false) {
		throw new BoardException("Invalid page number");
	}

	$currentPage = max(1, min($totalPages, $currentPage));

	return [$totalPages, $currentPage];
}

function getBoardPageLink(int $page, bool $isStaticAll, string $liveFrontEnd, bool $isLiveFrontend, int $staticPagesToRebuild): string {
	if ($isLiveFrontend) {
		return $liveFrontEnd . '?page=' . $page;
	}

	if ($isStaticAll || $page <= $staticPagesToRebuild) {
		return ($page === 1) ? 'index.html' : $page . '.html';
	}

	return $liveFrontEnd . '?page=' . $page;
}

function renderPager(int $currentPage, int $totalPages, callable $getLink, ?callable $getForm = null): string {
	$pageHTML = '<table id="pager"><tbody><tr>';

	if ($currentPage <= 1) {
		$pageHTML .= '<td id="pagerPreviousCell">First</td>';
	} else {
		$pageHTML .= '<td id="pagerPreviousCell">' . ($getForm ? $getForm($currentPage - 1, 'Previous') : '<form action="' . $getLink($currentPage - 1) . '" method="get"><button type="submit">Previous</button></form>') . '</td>';
	}

	$pageHTML .= '<td id="pagerPagesCell"><div id="pagerPagesContainer">';
	for ($i = 1; $i <= $totalPages; $i++) {
		if ($i == $currentPage) {
			$pageHTML .= "<span class=\"pagerPageLink\" id=\"pagerSelectedPage\">[$i]</span> ";
		} else {
			$pageHTML .= '<span class="pagerPageLink">[<a href="' . $getLink($i) . '">' . $i . '</a>]</span> ';
		}
	}
	$pageHTML .= '</div></td>';

	if ($currentPage >= $totalPages) {
		$pageHTML .= '<td id="pagerNextCell">Last</td>';
	} else {
		$pageHTML .= '<td id="pagerPreviousCell">' . ($getForm ? $getForm($currentPage + 1, 'Next') : '<form action="' . $getLink($currentPage + 1) . '" method="get"><button type="submit">Next</button></form>') . '</td>';
	}

	$pageHTML .= '</tr></tbody></table>';
	return $pageHTML;
}

function drawBoardPager(int $entriesPerPage, int $totalEntries, string $url, int $currentPage, int $staticPagesToRebuild, string $liveIndexFile, string $staticIndexFile): string {
	[$totalPages, $currentPage] = validateAndClampPagination($entriesPerPage, $totalEntries, $currentPage);

	$staticUntil = $staticPagesToRebuild;
	$isStaticAll = ($staticUntil === -1);

	$getLink = function($page) use ($url, $staticUntil, $isStaticAll, $liveIndexFile, $staticIndexFile) {
		if ($page === 1) {
			return $url . $staticIndexFile;
		}
		if (!$isStaticAll && $page > $staticUntil) {
			return $url . $liveIndexFile . '?page=' . $page;
		}
		return $page . '.html';
	};

	$getForm = function($page, $label) use ($url, $staticUntil, $isStaticAll, $liveIndexFile, $staticIndexFile) {
		if ($page === 1) {
			return '<form action="' . htmlspecialchars($url . $staticIndexFile) . '" method="get"><button type="submit">' . htmlspecialchars($label) . '</button></form>';
		}
		if (!$isStaticAll && $page > $staticUntil) {
			return '<form action="' . htmlspecialchars($url . $liveIndexFile) . '" method="get">
				<input type="hidden" name="page" value="' . intval($page) . '">
				<button type="submit">' . htmlspecialchars($label) . '</button>
			</form>';
		}

		return '<form action="' . htmlspecialchars($page . '.html') . '" method="get"><button type="submit">' . htmlspecialchars($label) . '</button></form>';
	};

	return renderPager($currentPage, $totalPages, $getLink, $getForm);
}

function drawLiveBoardPager(int $entriesPerPage, int $totalEntries, string $url, int $staticPagesToRebuild, string $liveIndexFile, request $request): string {
	$currentPage = getPageFromRequest($request);

	[$totalPages, $currentPage] = validateAndClampPagination($entriesPerPage, $totalEntries, $currentPage);

	$actionUrl = $url . $liveIndexFile;
	$isStaticAll = ($staticPagesToRebuild == -1);

	$getLink = fn($page) => getBoardPageLink($page, $isStaticAll, $actionUrl, true, $staticPagesToRebuild);

	$getForm = fn($page, $label) => '<form action="' . $actionUrl . '" method="get">
		<input type="hidden" name="page" value="' . $page . '">
		<button type="submit">' . $label . '</button>
	</form>';

	return renderPager($currentPage, $totalPages, $getLink, $getForm);
}

function drawPager(int $entriesPerPage, int $totalEntries, string $url, request $request, string $pageParam = 'page'): string {
	$currentPage = getPageFromRequest($request, $pageParam);

	[$totalPages, $currentPage] = validateAndClampPagination($entriesPerPage, $totalEntries, $currentPage);

	$getLink = fn($page) => $url . '&' . $pageParam . '=' . $page;

	$getForm = function($page, $label) use ($url, $request, $pageParam) {
		$params = $request->allGet();
		unset($params[$pageParam]);
		$params[$pageParam] = $page;

		$inputs = '';
		foreach ($params as $key => $val) {
			if (is_array($val)) {
				foreach ($val as $subVal) {
					$inputs .= '<input type="hidden" name="' . htmlspecialchars($key) . '[]" value="' . htmlspecialchars($subVal) . '">' . "\n";
				}
			} else {
				$inputs .= '<input type="hidden" name="' . htmlspecialchars($key) . '" value="' . htmlspecialchars($val) . '">' . "\n";
			}
		}

		return '<form action="' . htmlspecialchars($url) . '" method="get">' . $inputs . '
			<button type="submit">' . htmlspecialchars($label) . '</button>
		</form>';
	};

	return renderPager($currentPage, $totalPages, $getLink, $getForm);
}
