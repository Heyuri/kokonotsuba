<?php

function validateAndClampPagination(int $entriesPerPage, int $totalEntries, int $currentPage): array {
	if ((filter_var($totalEntries, FILTER_VALIDATE_INT) === false || $totalEntries < 0) ||
		(filter_var($entriesPerPage, FILTER_VALIDATE_INT) === false || $entriesPerPage < 0)) {
		throw new BoardException("Total entries must be a valid non-negative integer.");
	}

	$totalPages = (int) ceil($totalEntries / $entriesPerPage);

	if (filter_var($currentPage, FILTER_VALIDATE_INT) === false) {
		throw new BoardException("Invalid page number");
	}

	$currentPage = max(0, min($totalPages - 1, $currentPage));

	return [$totalPages, $currentPage];
}

function getBoardPageLink(int $page, bool $isStaticAll, string $liveFrontEnd, bool $isLiveFrontend, int $staticPagesToRebuild): string {
	if ($isLiveFrontend) {
		return $liveFrontEnd . '?page=' . $page;
	}

	if ($isStaticAll || $page <= $staticPagesToRebuild - 1) {
		return ($page === 0) ? 'index.html' : $page . '.html';
	}

	return $liveFrontEnd . '?page=' . $page;
}

function renderPager(int $currentPage, int $totalPages, callable $getLink, ?callable $getForm = null): string {
	$pageHTML = '<table id="pager"><tbody><tr>';

	if ($currentPage <= 0) {
		$pageHTML .= '<td id="pagerPreviousCell">First</td>';
	} else {
		$pageHTML .= '<td id="pagerPreviousCell">' . ($getForm ? $getForm($currentPage - 1, 'Previous') : '<form action="' . $getLink($currentPage - 1) . '" method="get"><button type="submit">Previous</button></form>') . '</td>';
	}

	$pageHTML .= '<td id="pagerPagesCell"><div id="pagerPagesContainer">';
	for ($i = 0; $i < $totalPages; $i++) {
		if ($i == $currentPage) {
			$pageHTML .= "<span class=\"pagerPageLink\" id=\"pagerSelectedPage\">[$i]</span> ";
		} else {
			$pageHTML .= '<span class="pagerPageLink">[<a href="' . $getLink($i) . '">' . $i . '</a>]</span> ';
		}
	}
	$pageHTML .= '</div></td>';

	if ($currentPage >= $totalPages - 1) {
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
		if ($page === 0) {
			return $url . $staticIndexFile;
		}
		if (!$isStaticAll && $page >= $staticUntil) {
			return $url . $liveIndexFile . '?page=' . $page;
		}
		return $page . '.html';
	};

	$getForm = function($page, $label) use ($url, $staticUntil, $isStaticAll, $liveIndexFile, $staticIndexFile) {
		if ($page === 0) {
			return '<form action="' . htmlspecialchars($url . $staticIndexFile) . '" method="get"><button type="submit">' . htmlspecialchars($label) . '</button></form>';
		}
		if (!$isStaticAll && $page >= $staticUntil) {
			return '<form action="' . htmlspecialchars($url . $liveIndexFile) . '" method="get">
				<input type="hidden" name="page" value="' . intval($page) . '">
				<button type="submit">' . htmlspecialchars($label) . '</button>
			</form>';
		}

		return '<form action="' . htmlspecialchars($page . '.html') . '" method="get"><button type="submit">' . htmlspecialchars($label) . '</button></form>';
	};

	return renderPager($currentPage, $totalPages, $getLink, $getForm);
}

function drawLiveBoardPager(int $entriesPerPage, int $totalEntries, string $url, int $staticPagesToRebuild, string $liveIndexFile): string {
	$currentPage = (int)$_REQUEST['page'] ?? 0;

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

function drawPager(int $entriesPerPage, int $totalEntries, string $url): string {
	$currentPage = $_REQUEST['page'] ?? 0;

	[$totalPages, $currentPage] = validateAndClampPagination($entriesPerPage, $totalEntries, $currentPage);

	$getLink = fn($page) => $url . '&page=' . $page;

	$getForm = function($page, $label) use ($url) {
		$params = $_GET;
		unset($params['page']);
		$params['page'] = $page;

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
