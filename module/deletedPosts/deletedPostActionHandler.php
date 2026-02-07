<?php

namespace Kokonotsuba\Modules\deletedPosts;

use BoardException;
use deletedPostsService;
use Kokonotsuba\Root\Constants\userRole;

class deletedPostActionHandler {
	public function __construct(
		private userRole $requiredRoleActionForModAll,
		private deletedPostsService $deletedPostsService,
		private deletedPostUtility $deletedPostUtility,
		private string $restoredIndexUrl
	) {}

	public function handleModPageRequests(int $accountId, userRole $roleLevel): void {
		$deletedPostId = $_POST['deletedPostId'] ?? null;
		$action = $_POST['action'] ?? null;

		// handle an action for single deleted post
		if(isset($deletedPostId)) {
			// make sure the user is a high enough role level if the post wasn't deleted by them
			// if not, throw excepton
			$this->deletedPostUtility->authenticateDeletedPost($deletedPostId, $roleLevel, $accountId);

			$this->handleAction($deletedPostId, $accountId, $roleLevel, $action);

			return;
		}

		// invalid action from request - it didn't fit any of the above criteria
		else {
			throw new BoardException("Invalid action");
		}
	}

    private function handleAction(int $deletedPostId, int $accountId, userRole $roleLevel, string $action): void {
		// If its a restore action, handle the restoring of the post
		if($action === 'restore') {
			$this->deletedPostsService->restorePost($deletedPostId, $accountId);
		}

		// if it's a purge action, handle the purging and associated actions 
		else if ($action === 'purge' && $roleLevel->isAtLeast($this->requiredRoleActionForModAll)) {
			$this->deletedPostsService->purgePost($deletedPostId);
		}

		// if it's an attachment purge then delete the file only
		// then mark it as 'restored' by the mod since theres no more action to do on it
		else if ($action === 'purgeAttachment' && $roleLevel->isAtLeast($this->requiredRoleActionForModAll)) {
			$this->deletedPostsService->purgeAttachmentOnly($deletedPostId);
		}

		// if its a restore attachment action then restore the specifc attachment
		else if ($action === 'restoreAttachment') {
			$this->deletedPostsService->restoreAttachment($deletedPostId, $accountId);
		}

		// if it's a saveNote action. handle saving a new note tied to that post
		else if ($action === 'saveNote') {
			// note from request
			$note = $_POST['note'] ?? '';

			// update the note
			$this->deletedPostsService->updateNote($deletedPostId, $note);

			// url of the deleted post
			$url = $this->deletedPostUtility->generateDeletedPostViewUrl($deletedPostId);

			// redirect early to the url - theres no need to rebuild
			redirect($url);
		}

		// if it's a delete record action - then delete the record directly from the database
		// this is only intended for restore records
		else if ($action === 'deleteRecord') {
			// delete the row, the post remains intact
			$this->deletedPostsService->removeEntry($deletedPostId);

			// then redirect to the restored index
			redirect($this->restoredIndexUrl);
		}

		// rebuild board
		$this->rebuildBoardByDeletedPostId($deletedPostId);
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