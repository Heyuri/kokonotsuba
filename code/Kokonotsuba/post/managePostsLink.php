<?php

namespace Kokonotsuba\post;

/**
 * Links into the manage-posts table, filtered to one person across every board.
 *
 * Several staff pages hand out an "everything this person posted" link and each needs the same
 * all-boards query string, so building it lives here rather than once per caller.
 */
final class managePostsLink {
	/**
	 * Every post from an address.
	 *
	 * The address travels in the link, so this is only for staff cleared to see addresses -
	 * manage-posts drops the filter for anyone else. It is passed in whatever form it is stored,
	 * which is what lets an anonymized address still match posts anonymized by the same run.
	 */
	public static function forIp(string $baseUrl, string $ipAddress): string {
		return self::build($baseUrl, ['ip_address' => $ipAddress]);
	}

	/**
	 * Every post from whoever made the given post, without naming their address - manage-posts
	 * resolves postsFrom itself, so the address stays out of the link.
	 */
	public static function forPost(string $baseUrl, int|string $postUid): string {
		return self::build($baseUrl, ['postsFrom' => $postUid]);
	}

	/** Every post from one browser. The whole hash travels, so the filter cannot drift. */
	public static function forVisitorToken(string $baseUrl, string $tokenHash): string {
		return self::build($baseUrl, ['visitor_token_hash' => $tokenHash]);
	}

	private static function build(string $baseUrl, array $filter): string {
		return $baseUrl . '?' . http_build_query(array_merge(
			['mode' => 'managePosts'],
			$filter,
			['board' => self::allBoardUids()]
		));
	}

	/** Manage-posts defaults to the current board, so every board has to be named explicitly. */
	private static function allBoardUids(): string {
		$boardUids = [];

		foreach (GLOBAL_BOARD_ARRAY as $board) {
			$boardUids[] = $board->getBoardUID();
		}

		return implode(' ', $boardUids);
	}
}
