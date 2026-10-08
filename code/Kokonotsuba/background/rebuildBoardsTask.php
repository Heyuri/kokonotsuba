<?php

namespace Kokonotsuba\background;

use Kokonotsuba\cache\thread_fragment\threadFragments;

use Puchiko\background\BackgroundTaskInterface;

use function Kokonotsuba\libraries\rebuildBoardsByArray;

/**
 * Regenerate the static pages of the given boards in a detached process.
 *
 * Registered once at bootstrap under 'rebuild_boards' (see bootstrap/global.php), so anything
 * that changes rendered output - the rebuild module, a board config save, a global config save -
 * dispatches the same task rather than rebuilding inside the request.
 *
 * Runs with no HTTP session, so it rebuilds the boards from scratch off backgroundBoardContext.
 */
class rebuildBoardsTask implements BackgroundTaskInterface {
	public function handle(array $args): void {
		$boardUIDs = array_map('intval', $args['boardUIDs'] ?? []);
		if (empty($boardUIDs)) {
			return;
		}

		$boardService = backgroundBoardContext::boot()->get('boardService');

		// ── Rebuild ───────────────────────────────────────────────────────
		$boards = $boardService->getBoardsFromUIDs($boardUIDs);

		// a config save or manual rebuild may change how every thread renders
		if (!empty($args['dropFragments'])) {
			foreach ($boards as $board) {
				threadFragments::forgetBoard($board);
			}
		}

		rebuildBoardsByArray($boards, false);
	}
}
