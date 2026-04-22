<?php

namespace Kokonotsuba\Modules\deletedPosts;

use Kokonotsuba\error\BoardException;
use Kokonotsuba\post\deletion\deletedPostsService;
use Kokonotsuba\request\request;
use Kokonotsuba\userRole;

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
		$deletedPostId = $this->request->getParameter('deletedPostId', 'POST');
		$action = $this->request->getParameter('action', 'POST');

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