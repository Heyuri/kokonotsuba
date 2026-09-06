<?php

namespace Kokonotsuba\Modules\deletedPosts;

use Kokonotsuba\error\BoardException;
use Kokonotsuba\post\deletion\deletedPostsService;
use Kokonotsuba\request\request;
use Kokonotsuba\userRole;

use function Kokonotsuba\libraries\getBoardsByUIDs;
use function Kokonotsuba\libraries\rebuildBoardsByArray;
use function Kokonotsuba\libraries\searchBoardArrayForBoard;
use function Puchiko\request\redirect;

class deletedPostActionHandler {
	public function __construct(
		private userRole $requiredRoleActionForModAll,
		private deletedPostsService $deletedPostsService,
		private deletedPostUtility $deletedPostUtility,
		private string $restoredIndexUrl,
		private readonly request $request
	) {}

	/**
	 * @return array{action: string, message: string} Result of the action performed.
	 */
	public function handleModPageRequests(int $accountId, userRole $roleLevel): array {
		// Support both the browser-lowercased form (data-param-deletedPostId → deletedpostid in DOM)
		// and the original camelCase name used by server-rendered forms.
		$deletedPostId = $this->request->getParameter('deletedpostid', 'POST')
			?? $this->request->getParameter('deletedPostId', 'POST');
		$action = $this->request->getParameter('action', 'POST');

		// a selection from the [Moderate] window, addressed by post UID
		$postUids = $this->request->getParameter('post_uids', 'POST');

		if(is_array($postUids)) {
			return $this->handleMassAction($postUids, $accountId, $roleLevel, $action);
		}

		// a single attachment, addressed by file ID since a file deleted with its whole post has no
		// deletion record of its own (same lowercased/camelCase pair as above)
		$fileId = $this->request->getParameter('fileid', 'POST')
			?? $this->request->getParameter('fileId', 'POST');

		if(isset($fileId) && $action === 'purgeFile') {
			return $this->handleFilePurge((int)$fileId, $roleLevel);
		}

		// handle an action for single deleted post
		if(isset($deletedPostId)) {
			// make sure the user is a high enough role level if the post wasn't deleted by them
			// if not, throw excepton
			$this->deletedPostUtility->authenticateDeletedPost($deletedPostId, $roleLevel, $accountId);

			return $this->handleAction($deletedPostId, $accountId, $roleLevel, $action);
		}

		// invalid action from request - it didn't fit any of the above criteria
		else {
			throw new BoardException("Invalid action");
		}
	}

	/**
	 * Restore or purge a whole selection of posts.
	 *
	 * The window hands over post UIDs, so the deletion records are resolved, authorised and acted
	 * on as a set: two lookups and one transaction for the selection, then one rebuild per board it
	 * reached into (purging touches nothing that is still rendered, so it rebuilds nothing).
	 *
	 * @param array $postUids Post UIDs from the selection.
	 * @return array{action: string, message: string, results: array}
	 */
	private function handleMassAction(array $postUids, int $accountId, userRole $roleLevel, string $action): array {
		if($action !== 'restore' && $action !== 'purge') {
			throw new BoardException("Invalid action");
		}

		$postUids = array_values(array_unique(array_filter(
			array_map(fn($postUid) => (int)$postUid, $postUids),
			fn(int $postUid) => $postUid > 0
		)));

		$deletedPostIds = $this->deletedPostsService->getDeletedPostIdsByPostUids($postUids);

		if(!$deletedPostIds) {
			throw new BoardException("None of the selected posts are deleted");
		}

		$authenticated = $this->deletedPostUtility->authenticateDeletedPosts(
			array_values($deletedPostIds),
			$roleLevel,
			$accountId
		);

		if($action === 'purge') {
			if(!$roleLevel->isAtLeast($this->requiredRoleActionForModAll)) {
				throw new BoardException("Invalid action");
			}

			$this->deletedPostsService->purgePosts($authenticated);

			return ['action' => 'purge', 'message' => 'Posts purged', 'results' => []];
		}

		$this->deletedPostsService->restorePosts($authenticated, $accountId);

		$this->rebuildBoardsByDeletedPostIds($authenticated);

		return ['action' => 'restore', 'message' => 'Posts restored', 'results' => []];
	}

	/**
	 * Purge one attachment on its own, leaving its post's deletion untouched. The file is already
	 * hidden from the board, so nothing needs rebuilding.
	 *
	 * @return array{action: string, message: string}
	 */
	private function handleFilePurge(int $fileId, userRole $roleLevel): array {
		// same role as purging a whole post
		if(!$roleLevel->isAtLeast($this->requiredRoleActionForModAll)) {
			throw new BoardException("Invalid action");
		}

		if($fileId <= 0) {
			throw new BoardException("Invalid action");
		}

		$this->deletedPostsService->purgeAttachmentByFileId($fileId);

		return ['action' => 'purgeFile', 'message' => 'File purged'];
	}

	private function rebuildBoardsByDeletedPostIds(array $deletedPostIds): void {
		$boardUids = $this->deletedPostsService->getBoardUidsByDeletedPostIds($deletedPostIds);

		rebuildBoardsByArray(getBoardsByUIDs($boardUids));
	}

    /**
	 * @return array{action: string, message: string}
	 */
	private function handleAction(int $deletedPostId, int $accountId, userRole $roleLevel, string $action): array {
		// If its a restore action, handle the restoring of the post
		if($action === 'restore') {
			$this->deletedPostsService->restorePost($deletedPostId, $accountId);

			// rebuild board
			$this->rebuildBoardByDeletedPostId($deletedPostId);

			return ['action' => 'restore', 'message' => 'Post restored'];
		}

		// if it's a purge action, handle the purging and associated actions 
		else if ($action === 'purge' && $roleLevel->isAtLeast($this->requiredRoleActionForModAll)) {
			$this->deletedPostsService->purgePost($deletedPostId);

			return ['action' => 'purge', 'message' => 'Post purged'];
		}

		// if it's an attachment purge then delete the file only
		// then mark it as 'restored' by the mod since theres no more action to do on it
		else if ($action === 'purgeAttachment' && $roleLevel->isAtLeast($this->requiredRoleActionForModAll)) {
			$this->deletedPostsService->purgeAttachmentOnly($deletedPostId);

			return ['action' => 'purgeAttachment', 'message' => 'Attachment purged'];
		}

		// if its a restore attachment action then restore the specifc attachment
		else if ($action === 'restoreAttachment') {
			$this->deletedPostsService->restoreAttachment($deletedPostId, $accountId);

			// rebuild board
			$this->rebuildBoardByDeletedPostId($deletedPostId);

			return ['action' => 'restoreAttachment', 'message' => 'Attachment restored'];
		}

		// if it's a delete record action - then delete the record directly from the database
		// this is only intended for restore records
		else if ($action === 'deleteRecord') {
			// delete the row, the post remains intact
			$this->deletedPostsService->removeEntry($deletedPostId);

			return ['action' => 'deleteRecord', 'message' => 'Record deleted', 'redirect' => $this->restoredIndexUrl];
		}

		throw new BoardException("Invalid action");
	}

	private function rebuildBoardByDeletedPostId(int $deletedPostId): void {
		// get the board uid by deleted post id
		$boardUid = $this->deletedPostsService->getBoardUidByDeletedPostId($deletedPostId);

		// if its null then dont bother
		if(is_null($boardUid)) {
			return;
		}

		// get board from board uid
		$board = searchBoardArrayForBoard($boardUid);

		// rebuild the board html
		$board->rebuildBoard();
	}
}