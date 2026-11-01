<?php

namespace Kokonotsuba\news;

use Kokonotsuba\action_log\actionLoggerService;
use Kokonotsuba\action_log\actionLogReferences;
use Kokonotsuba\error\BoardException;

use function Kokonotsuba\libraries\_T;

use const Kokonotsuba\GLOBAL_BOARD_UID;

/**
 * Front page news: paging, validated writes, and the action log. Bodies are staff-written HTML
 * and stored as given; who may write them is the caller's check (frontPagePolicy).
 */
final class newsService {
	private const MAX_LINE = 255;
	private const MAX_BODY = 1048576;

	/** @var list<callable(): void> */
	private array $changeListeners = [];

	public function __construct(
		private readonly newsRepository $newsRepository,
		private readonly actionLoggerService $actionLoggerService,
	) {}

	/** Called after every write, e.g. to forget cached front page HTML. */
	public function onChange(callable $listener): void {
		$this->changeListeners[] = $listener;
	}

	/** Pages are 1-based; a page past the last one comes back empty. */
	public function listPage(int $page, int $perPage, bool $includeDrafts): newsPage {
		$perPage = max(1, $perPage);
		$page = max(1, $page);
		$total = $this->newsRepository->countEntries($includeDrafts);

		return new newsPage(
			$this->newsRepository->listEntries($includeDrafts, $perPage, ($page - 1) * $perPage),
			$page,
			max(1, (int)ceil($total / $perPage)),
			$total,
		);
	}

	public function getEntry(int $id, bool $includeDrafts): ?newsEntry {
		$entry = $this->newsRepository->findEntry($id);

		return $entry !== null && ($entry->isPublished || $includeDrafts) ? $entry : null;
	}

	/** @return int the new entry's id */
	public function createEntry(string $subject, string $body, string $byline, string $email, bool $isPublished, ?int $authorId): int {
		[$subject, $body, $byline, $email] = self::validate($subject, $body, $byline, $email);

		$id = $this->newsRepository->insertEntry($subject, $body, $byline, $email, $authorId, $isPublished);

		$this->log("Posted news entry " . self::reference($id, $subject) . ($isPublished ? '' : ' (draft)'), newsActionType::CREATE);
		$this->changed();

		return $id;
	}

	/**
	 * An entry from an import, keeping its original date. Writes no action log line and fires no
	 * change event: the importer reports once for the whole run.
	 * @param string $createdAt 'Y-m-d H:i:s'
	 * @return ?int the new entry's id, null when the same subject and date are already there
	 */
	public function importEntry(string $subject, string $body, string $byline, string $email, string $createdAt): ?int {
		[$subject, $body, $byline, $email] = self::validate($subject, $body, $byline, $email);

		if ($this->newsRepository->hasEntry($subject, $createdAt)) {
			return null;
		}

		return $this->newsRepository->insertEntry($subject, $body, $byline, $email, null, true, $createdAt);
	}

	public function updateEntry(int $id, string $subject, string $body, string $byline, string $email, bool $isPublished): void {
		$this->requireEntry($id);
		[$subject, $body, $byline, $email] = self::validate($subject, $body, $byline, $email);

		$this->newsRepository->updateEntry($id, $subject, $body, $byline, $email, $isPublished);

		$this->log("Edited news entry " . self::reference($id, $subject), newsActionType::EDIT);
		$this->changed();
	}

	public function deleteEntry(int $id): void {
		$entry = $this->requireEntry($id);

		$this->newsRepository->deleteEntry($id);

		$this->log("Deleted news entry #{$id} ({$entry->subject})", newsActionType::DELETE);
		$this->changed();
	}

	private function requireEntry(int $id): newsEntry {
		return $this->newsRepository->findEntry($id) ?? throw new BoardException(_T('news_not_found'), 404);
	}

	/** @return array{string, string, string, string} trimmed subject, body, byline, email */
	private static function validate(string $subject, string $body, string $byline, string $email): array {
		$subject = trim($subject);
		$byline = trim($byline);
		$email = trim($email);

		if ($subject === '' || trim($body) === '') {
			throw new BoardException(_T('news_subject_body_required'));
		}
		if (mb_strlen($subject) > self::MAX_LINE || mb_strlen($byline) > self::MAX_LINE || mb_strlen($email) > self::MAX_LINE) {
			throw new BoardException(_T('news_field_too_long', self::MAX_LINE));
		}
		if (strlen($body) > self::MAX_BODY) {
			throw new BoardException(_T('news_body_too_long'));
		}
		if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
			throw new BoardException(_T('news_email_invalid'));
		}

		return [$subject, $body, $byline, $email];
	}

	private static function reference(int $id, string $subject): string {
		return actionLogReferences::reference('news', $id, "#{$id} ({$subject})");
	}

	private function log(string $action, newsActionType $type): void {
		$this->actionLoggerService->logAction($action, GLOBAL_BOARD_UID, $type);
	}

	private function changed(): void {
		foreach ($this->changeListeners as $listener) {
			$listener();
		}
	}
}
