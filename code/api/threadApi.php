<?php

class threadApi {
	public function __construct(
		private threadService $threadService
	) {}

	public function invoke(string $endpointPage): void {
		// thread API router
		// handle what data to show
		if($endpointPage === 'thread') {
			$this->handleThreadRequest();
		}
		
	}

	/**
	 * Handle requests forS json for a specific thread
	 * 
	 * @return void
	 */
	private function handleThreadRequest(): void {
		// wrap in try-catch
		try {
			// id of the requested thread
			$threadUid = $_GET['thread_uid'] ?? null;

			// if null or empty then output a json error page
			if(is_null($threadUid) || empty($threadUid)) {
				renderJsonErrorPage(_T('error_invalid_thread_id'), 400);
			}

			// fetch for a specific thread
			$thread = $this->fetchThread($threadUid);

			// throw a json error page if the thread wasn't found
			// i.e $thread is null or false
			if(!$thread) {
				renderJsonErrorPage(_T('thread_not_found'), 404);
			}

			// no errors so far - safe to ouput json
			renderCachedJsonPage($thread, 300);
		}
		// output json exception
		catch (\Throwable $e) {
		    renderJsonErrorPage('Error caught during thread json', 500);
		}
	}

	/**
	 * This method will fetch thread data from the database, process it, then return
	 *
	 * @param string $threadUid
	 * 
	 * @return string|false it may be false (i.e, not found)
	*/
	private function fetchThread(string $threadUid): array|false {
		// get thread data from database
		$thread = $this->threadService->getThreadLastReplies($threadUid, isActiveStaffSession(), 5, 500);

		// return false early if the thread wasn't found
		if(!$thread) {
			return false;
		}

		// whether to include the posts of the thread
		$includePosts = $_GET['includeThreadPosts'] ?? false; 

		// build the thread array
		// whitelists values we want in the json
		$processedThread = $this->buildThreadArray($thread, $includePosts);

		// return thread
		return $processedThread;
	}

	/**
	 * Manually craft a thread array used from thread data, for json encoding
	 * 
	 * Note: threads can have a lot of replies made to them, which can potientially eat up a lot of memory
	 * 
	 * @param array $thread the whole thread data
	 * @param bool $includeReplies whether to include the thread's replies
	 * 
	 * @return array to be json encoded
	*/
	private function buildThreadArray(array $thread, bool $includePosts = false): array {
		// thread meta data
		// contains thread_uid, board uid, post op number, etc.
		$threadMetaData = $thread['thread'];

		// manually specify keys and values to avoid potentially sensitive values from being included
		$threadData = [
			// ID of the thread
			'thread_uid' => $threadMetaData['thread_uid'],

			// board id that the thread is currently in
			'board_uid' => $threadMetaData['boardUID'],
			
			// flag for if the thread is stickied
			'is_sticky' => $threadMetaData['is_sticky'],

			// post number of the OP post
			'post_op_number' => $threadMetaData['post_op_number'],

			// post uid of the OP post
			'post_op_post_uid' => $threadMetaData['post_op_post_uid'],

			// amount of replies in the thread (not counting OP)
			// subtract count by 1 to exclude OP from count
			'amount_of_replies' => $threadMetaData['number_of_posts'] - 1
		];

		// append to array posts (includes OP) if specified
		if($includePosts) {
			$threadData['posts'] = $this->buildPostsArray($thread['posts']);
		}

		// return constructed thread data
		return $threadData;
	}

	/**
	 * This method excludes the `host` value
	 * It doesn't manually whitelist values as the post data structure has a ton of columns that change frequently 
	 * 
	 * Ideally i wouldn't use whitelisting at all since its a PITA to edit multiple files when updating columns
	 * but it feels wrong to just blindly put columns forth, this case being an exception
	 * 
	 * @param array $posts
	 * 
	 * @return array
	*/
	private function buildPostsArray(array $posts): array {
		// loop through posts and remove 'host', which contains the IP address. as well as 'pwd', which contains the post password hash
		foreach($posts as &$p) {
			// remove host
			unset($p['host']);

			// remove pwd
			unset($p['pwd']);
		}

		// then return posts
		return $posts;
	}
}