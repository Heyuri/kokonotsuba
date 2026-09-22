<?php

namespace Koko\Tests\Framework;

use Kokonotsuba\quote_link\textQuoteRepository;

/**
 * A thread held in an array, answering what textQuoteRepository asks of the database.
 *
 * The text search is byte-exact, as the real one is (CAST ... AS BINARY), while the file name
 * comparison is loose, as the column's collation is - so the resolver's own checking is what the
 * tests and the fuzzer exercise.
 *
 * Posts are ['post_uid', 'no', 'is_op', 'com', 'text_format', 'files' => [[name, ext]], 'deleted'].
 */
final class InMemoryTextQuoteRepository extends textQuoteRepository {
	/** @var int Statements answered, so a test can pin how much work a lookup costs. */
	public int $queries = 0;

	public function __construct(private array $posts, private string $threadUid = 't') {}

	public function findCandidates(
		string $threadUid,
		int $beforeUid,
		array $needles,
		int $window,
		int $limit,
		bool $withOpeningPost,
		?array $fileName = null
	): array {
		$this->queries++;

		if ($threadUid !== $this->threadUid || !$this->isReplyOfThisThread($beforeUid)) {
			return [];
		}

		$replies = array_filter($this->posts, static fn(array $p): bool => !$p['is_op'] && $p['post_uid'] < $beforeUid);
		usort($replies, static fn(array $a, array $b): int => $b['post_uid'] <=> $a['post_uid']);

		$rows = [];
		foreach (array_slice($replies, 0, $window) as $post) {
			if (empty($post['deleted']) && $this->holdsAny($post['com'], $needles)) {
				$rows[] = $this->row($post);
			}
		}

		$rows = array_slice($rows, 0, $limit);

		if ($withOpeningPost) {
			foreach ($this->posts as $post) {
				if ($post['is_op'] && empty($post['deleted']) && $this->holdsAny($post['com'], $needles)) {
					$rows[] = $this->row($post);
					break;
				}
			}
		}

		if ($fileName !== null) {
			$rows = array_merge($rows, $this->fileRows($beforeUid, $fileName, $limit));
		}

		usort($rows, static fn(array $a, array $b): int =>
			[$a['is_op'], $b['post_uid'], $a['file_name'] !== null]
			<=> [$b['is_op'], $a['post_uid'], $b['file_name'] !== null]);

		return $rows;
	}

	public function findPostUidByNumber(string $threadUid, int $beforeUid, int $postNumber): ?int {
		$this->queries++;

		if ($threadUid !== $this->threadUid || !$this->isReplyOfThisThread($beforeUid)) {
			return null;
		}

		foreach ($this->visibleBefore($beforeUid) as $post) {
			if ($post['no'] === $postNumber) {
				return $post['post_uid'];
			}
		}

		return null;
	}

	/** @return array<int, array> */
	private function fileRows(int $beforeUid, array $fileName, int $limit): array {
		[$name, $extension] = $fileName;
		$rows = [];

		foreach ($this->visibleBefore($beforeUid) as $post) {
			foreach ($post['files'] ?? [] as [$storedName, $ext]) {
				if (mb_strtolower($storedName) === mb_strtolower($name)
					&& in_array(strtolower($ext), [strtolower($extension), '.' . strtolower($extension)], true)) {
					$rows[] = $this->row($post, $storedName, $ext);
				}
			}
		}

		return array_slice($rows, 0, $limit);
	}

	/** The quoting post must be a reply of this thread, as the real query's guard requires. */
	private function isReplyOfThisThread(int $postUid): bool {
		foreach ($this->posts as $post) {
			if ($post['post_uid'] === $postUid) {
				return !$post['is_op'];
			}
		}

		return false;
	}

	private function row(array $post, ?string $fileName = null, ?string $fileExtension = null): array {
		return [
			'post_uid' => $post['post_uid'],
			'is_op' => (int)$post['is_op'],
			'com' => $post['com'],
			'text_format' => $post['text_format'],
			'file_name' => $fileName,
			'file_ext' => $fileExtension,
		];
	}

	private function visibleBefore(int $beforeUid): array {
		return array_filter($this->posts, static fn(array $p): bool =>
			empty($p['deleted']) && $p['post_uid'] !== $beforeUid && ($p['is_op'] || $p['post_uid'] < $beforeUid));
	}

	private function holdsAny(string $haystack, array $needles): bool {
		foreach ($needles as $needle) {
			if ($needle === '' || str_contains($haystack, $needle)) {
				return true;
			}
		}

		return false;
	}
}
