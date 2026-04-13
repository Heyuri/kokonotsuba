<?php

namespace Kokonotsuba\thread;

use Kokonotsuba\post\Post;

/**
 * Represents a thread with its associated posts and preview metadata.
 * Combines thread metadata (Thread) with post data for rendering.
 */
class ThreadData {
	private array $postUids;

	public function __construct(
		private Thread $thread,
		private array $posts,
		private ?int $hiddenReplyCount,
		private int $numberOfPosts,
	) {
		$this->postUids = array_map(fn($p) => $p->getUid(), $posts);
	}

	/** Get the thread metadata object. */
	public function getThread(): Thread {
		return $this->thread;
	}

	/** Get the posts associated with this thread. */
	public function getPosts(): array {
		return $this->posts;
	}

	/** Get the opening post (OP) of the thread. Returns the post with is_op=1, or the first post as fallback. */
	public function getOpeningPost(): ?Post {
		foreach ($this->posts as $post) {
			if ($post->isOp()) {
				return $post;
			}
		}
		return $this->posts[0] ?? null;
	}

	/** Get the UIDs of all posts in this thread data. */
	public function getPostUids(): array {
		return $this->postUids;
	}

	/** Get the number of hidden/omitted replies (null if no preview limit). */
	public function getHiddenReplyCount(): ?int {
		return $this->hiddenReplyCount;
	}

	/** Get the total number of posts in the thread. */
	public function getNumberOfPosts(): int {
		return $this->numberOfPosts;
	}

	/** Get the thread UID. */
	public function getThreadUid(): string {
		return $this->thread->getUid();
	}
}
