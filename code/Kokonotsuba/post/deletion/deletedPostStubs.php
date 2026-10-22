<?php

namespace Kokonotsuba\post\deletion;

use Kokonotsuba\interfaces\IBoard;
use Kokonotsuba\post\Post;

/**
 * Turns the deleted posts a reader may not see into stubs that keep only their place in the thread.
 *
 * The kind names the stub's label (`deleted_stub_{kind}`) and css class (`deletedStub-{kind}`).
 */
final class deletedPostStubs {
	public const BY_USER = 'user';
	public const BY_STAFF = 'staff';

	/** Whether the board draws stubs for readers who cannot see deleted posts. */
	public static function enabledFor(IBoard $board): bool {
		return (bool)$board->getConfigValue('DELETED_POST_STUBS', true);
	}

	/** @param Post[] $posts */
	public static function apply(array $posts): void {
		foreach ($posts as $post) {
			if ($post->isDeleted()) {
				$post->stubAs(self::deletedByStaff($post) ? self::BY_STAFF : self::BY_USER);
			}
		}
	}

	/**
	 * An anonymous deletion (no account) is the poster's, and so is one by an account below staff.
	 * An account id that names no account, such as anti-flood's 0, is the system's, counted as staff.
	 */
	private static function deletedByStaff(Post $post): bool {
		if ($post->getDeletedBy() === null) {
			return false;
		}

		return $post->getDeletedByRole()?->isStaff() ?? true;
	}
}
