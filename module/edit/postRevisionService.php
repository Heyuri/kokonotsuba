<?php

namespace Kokonotsuba\Modules\edit;

require_once __DIR__ . '/postRevisionRepository.php';

use Kokonotsuba\post\Post;

/**
 * The post edit history, and putting a piece of it back.
 *
 * A revision is what the post said the moment before an edit, so the newest one is the state the
 * last edit replaced, not the state the post is in now. Restoring a revision is itself an edit
 * and records one of its own, which is what makes a restore undoable rather than a one-way door.
 *
 * Only the text a post carries is versioned. Attachments are not: dropping one already goes
 * through the deleted-posts machinery, which owns purgatory and its own restore.
 */
final class postRevisionService {
	/** The post columns a revision holds. */
	public const VERSIONED_FIELDS = ['name', 'email', 'sub', 'com', 'tag'];

	public function __construct(private readonly postRevisionRepository $repository) {}

	/**
	 * Snapshot a post before an edit changes it.
	 *
	 * @param int|null $editedBy Staff account making the edit, null when the poster made it.
	 * @return int The new revision's id.
	 */
	public function record(Post $post, ?int $editedBy): int {
		return $this->repository->insertRevision(
			$post->getUid(),
			$post->getBoardUID(),
			$editedBy,
			$this->snapshot($post)
		);
	}

	/**
	 * The values a post would be reverted to, as stored.
	 *
	 * @return array<string, mixed>
	 */
	public function snapshot(Post $post): array {
		return [
			'name' => $post->getName(),
			'email' => $post->getEmail(),
			'sub' => $post->getSubject(),
			'com' => $post->getComment(),
			'tag' => $post->getTag(),
		];
	}

	/**
	 * The values held by a revision, as an update for the post table.
	 *
	 * @return array<string, mixed>
	 */
	public function valuesOf(array $revision): array {
		$values = [];

		foreach (self::VERSIONED_FIELDS as $field) {
			$values[$field] = $revision[$field] ?? '';
		}

		return $values;
	}

	/** @return array[] Every revision of a post, newest first. */
	public function getRevisionsForPost(int $postUid): array {
		return $this->repository->getRevisionsForPost($postUid);
	}

	public function getRevisionById(int $revisionId): array|false {
		return $this->repository->getRevisionById($revisionId);
	}

	public function countRevisionsForPost(int $postUid): int {
		return $this->repository->countRevisionsForPost($postUid);
	}
}
