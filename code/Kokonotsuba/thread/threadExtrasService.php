<?php

namespace Kokonotsuba\thread;

use Kokonotsuba\database\databaseConnection;
use Kokonotsuba\database\rowCopyRepository;

/**
 * Carries the data hanging off posts and threads across a copy.
 *
 * Moving or merging with a shadow thread duplicates the posts instead of moving them, so the new
 * rows get fresh uids and anything keyed on the old ones would be left behind with the shadow.
 * Every side table that should survive a copy is listed here, and nowhere else.
 *
 * Reports and edit revisions are deliberately absent: a report belongs to the post that was
 * reported, and a revision to the board the edit happened on.
 */
class threadExtrasService {
	public function __construct(
		private databaseConnection $databaseConnection,
		private string $soudaneTable,
		private string $countryFlagTable,
		private string $displayIpTable,
		private string $noteTable,
		private string $threadThemeTable,
	) {}

	/**
	 * Copy the votes, flag, shown address and staff notes of every copied post onto its copy.
	 *
	 * @param array $postUidMap Original post uid => new post uid.
	 * @return void
	 */
	public function copyPostExtras(array $postUidMap): void {
		$tables = [
			$this->soudaneTable,
			$this->countryFlagTable,
			$this->displayIpTable,
			$this->noteTable,
		];

		foreach ($tables as $table) {
			$this->copierFor($table)->copyRowsToNewKeys('post_uid', $postUidMap, 'id');
		}
	}

	/**
	 * Copy a thread's theme onto another thread. A theme the destination already has is kept,
	 * so merging into a themed thread never restyles it.
	 *
	 * @param string $sourceThreadUid      Thread being copied from.
	 * @param string $destinationThreadUid Thread receiving the copy.
	 * @return void
	 */
	public function copyThreadTheme(string $sourceThreadUid, string $destinationThreadUid): void {
		$this->copierFor($this->threadThemeTable)
			->copyRowsToNewKeys('thread_uid', [$sourceThreadUid => $destinationThreadUid], 'id');
	}

	private function copierFor(string $table): rowCopyRepository {
		return new rowCopyRepository($this->databaseConnection, $table);
	}
}
