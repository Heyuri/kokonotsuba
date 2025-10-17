<?php

class usrdelRoute {
	public function __construct(
		private readonly array $config,
		private board $board,
		private moduleEngine $moduleEngine,
		private readonly actionLoggerService $actionLoggerService,
		private readonly postRepository $postRepository,
		private readonly postService $postService,
		private readonly deletedPostsService $deletedPostsService,
		private readonly softErrorHandler $softErrorHandler,
		private readonly array $regularBoards,
		private mixed $FileIO,
		private postPolicy $postPolicy
	) {}

	/* User post deletion */
	public function userPostDeletion(): void {
		// the password entered by the user for deleting the posts
		$password = $_POST['pwd'] ?? '';

		// the post password from the cookie value
		$passwordFromCookie = $_COOKIE['pwdc'] ?? '';

		// whether to only delete the attachment of posts
		$onlyImgDel = $_POST['onlyimgdel'] ?? '';

		// IDs of posts to be deleted
		$postUidsForDeletion = $this->collectPostUidsForDeletion();

		// make sure all post uids slated for deletion are integers
		$postUidsForDeletion = array_map('intval', $postUidsForDeletion);

		// the account ID, for post deletion-related meta data for staff deletions
		// users will just have a null ID
		$accountId = getIdFromSession();

		// if the password supplied is empty - then use the password stored in the cookie
		if (empty($password) && $passwordFromCookie !== '') {
			$password = $passwordFromCookie;
		}
		
		// at least 1 post was successfully authenticated for deletion
		$postWasDeleted = false;
		
		// IDs of posts that the user is authorized to delete
		$authenticatedDeletedPostUids = [];

		// post data from posts that were marked for deletion
		$authenticatedPostData = [];

		// no post uids were found (no post checkboxes were selected) - throw an error
		if (!count($postUidsForDeletion)) {
			throw new BoardException(_T('del_notchecked'));
		}

		// get post data for posts that were checked
		$posts = $this->postService->getPostsByUids($postUidsForDeletion);

		// Call the method to authenticate the posts and log actions
		$this->authenticateAndLogPostDeletions(
			$posts, 
			$password, 
			$onlyImgDel, 
			$postWasDeleted, 
			$authenticatedDeletedPostUids, 
			$authenticatedPostData
		);

		// handle logic directly related to deletion
		$this->handlePostDeletion(
			$postWasDeleted, 
			$onlyImgDel, 
			$authenticatedPostData, 
			$authenticatedDeletedPostUids, 
			$accountId
		);

		// Rebuild the static HTML pages for the board
		$this->board->rebuildBoard();

		// handle page redirecting
		$this->handleDeleteRedirect();
	}

	private function collectPostUidsForDeletion(): array {
		// Initialize an array to store the UIDs marked for deletion
		$postUidsForDeletion = [];

		// Reset the internal pointer of the $_POST array to ensure the loop works from the beginning
		reset($_POST);

		// Loop through each element in the $_POST array
		foreach ($_POST as $key => $val) {
			// Check if the current value is equal to 'delete'
			if ($val === 'delete') {
				// If the value is 'delete', add the key (UID) to the $postUidsForDeletion array
				$postUidsForDeletion[] = $key;
			}
		}

		// Return the array of UIDs marked for deletion
		return $postUidsForDeletion;
	}

	private function authenticateAndLogPostDeletions(
		array $posts, 
		string $password, 
		bool $onlyImgDel, 
		bool &$postWasDeleted, 
		array &$authenticatedDeletedPostUids, 
		array &$authenticatedPostData
	): void {

		// Loop through each post and authenticate whether it can be deleted by the user
		foreach ($posts as $post) {
			// Get the post password hash
			$postPasswordHash = $post['pwd'] ?? '';

			// Determine whether the user can delete the post
			$canUserDelete = $this->postPolicy->authenticatePostDeletion($postPasswordHash, $password);

			// If the post can be deleted, proceed
			if ($canUserDelete) {
				// Mark that at least one post was successfully authenticated for deletion
				$postWasDeleted = true;
			
				// Add the post data to the authenticated post data array (for potential attachment deletion)
				$authenticatedPostData[] = $post;

				// Add the post UID to the authenticated deletion UIDs array (to delete the post itself)
				$authenticatedDeletedPostUids[] = (int)$post['post_uid'];

				// Get the board of this post
				$board = searchBoardArrayForBoard($post['boardUID']);

				// Log the action (whether it's just the file or the entire post deletion)
				$this->actionLoggerService->logAction(
					"Deleted post No." . $post['no'] . ($onlyImgDel ? ' (file only)' : ''), 
					$board->getBoardUID()
				);
			}
		}
	}
	
	private function handlePostDeletion(
		bool $postWasDeleted, 
		bool $deleteAttachment, 
		array $postData, 
		array $postUids, 
		?int $accountId
	): void {
		// if any of the selected posts were authenticated, then delete the posts that were authenticated - unauthenticated posts will not get deleted and just be ignored
		if ($postWasDeleted) {
			// only delete the attachments for posts that have an attachment
			if ($deleteAttachment) {
				// only mark attachments as deleted
				$this->deletedPostsService->deleteFilesFromPosts($postData, $accountId);
			} 
			
			// delete the posts
			else {
				// run post deletion service method
				$this->postService->removePosts($postUids, $accountId);
			}

		} 

		// No posts were authenticated - throw error
		else {
			$this->softErrorHandler->errorAndExit(_T('del_wrongpwornotfound'));
		}
	}

	private function handleDeleteRedirect(): void {
		// Check if the 'func' key exists in the POST data and if its value is 'delete'
		if (isset($_POST['func']) && $_POST['func'] === 'delete') {
			// Check if the 'HTTP_REFERER' header is present, which indicates the page the request came from
			if (isset($_SERVER['HTTP_REFERER'])) {
				// Send a 302 HTTP response, indicating a temporary redirect
				header('HTTP/1.1 302 Moved Temporarily');
			
				// Redirect the user to the page they came from (using the 'HTTP_REFERER' value)
				header('Location: ' . $_SERVER['HTTP_REFERER']);
				exit; // It's a good idea to call exit after header redirects
			}
		} else {
			// If the 'func' key is not 'delete', redirect to the default live index page
			redirect($this->config['LIVE_INDEX_FILE']);
		}
	}
}

