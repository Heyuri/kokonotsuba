<?php

namespace Kokonotsuba\Modules\threadWatcher;

use Kokonotsuba\database\databaseConnection;
use Kokonotsuba\module_classes\abstractModuleMain;
use Kokonotsuba\module_classes\traits\listeners\IncludeScriptTrait;
use Kokonotsuba\module_classes\traits\listeners\ModuleHeaderListenerTrait;
use Kokonotsuba\module_classes\traits\listeners\ThreadWidgetListenerTrait;
use Kokonotsuba\module_classes\traits\listeners\TopLinksListenerTrait;
use Kokonotsuba\post\Post;
use Kokonotsuba\request\request;

use function Kokonotsuba\libraries\_T;
use function Puchiko\json\renderJsonPage;
use function Puchiko\json\renderJsonErrorPage;
use function Puchiko\strings\sanitizeStr;

require_once __DIR__ . '/threadWatcherRepository.php';

class moduleMain extends abstractModuleMain {
	use IncludeScriptTrait;
	use ModuleHeaderListenerTrait;
	use ThreadWidgetListenerTrait;
	use TopLinksListenerTrait;

	/** Max characters for a generated thread label (subject / comment preview / filename). */
	private const LABEL_MAX_LENGTH = 50;

	public function getName(): string {
		return 'Thread watcher';
	}

	public function getVersion(): string {
		return '1.0';
	}

	public function initialize(): void {
		// The watcher window is opened from a top-link in the admin bar.
		$this->listenTopLinks('onRenderTopLinks');
		$this->registerScript('threadWatcher.js?v=9');
		$this->listenModuleHeader('onGenerateModuleHeader');
		$this->listenThreadWidget('onRenderThreadWidget');
	}

	/**
	 * Batch counts endpoint:
	 *   ?mode=module&load=threadWatcher&pageName=counts&thread_uids=uid1,uid2,...&you=board:no,board:no,...
	 *
	 * Returns, per thread: post_count, board_title, label (display text) and quote_count
	 * (number of live posts that quote one of the caller's own posts).
	 */
	public function ModulePage(): void {
		$pageName = $this->moduleContext->request->getParameter('pageName', 'GET', '');

		if ($pageName !== 'counts') {
			renderJsonErrorPage('Not found', 404);
		}

		$request = $this->moduleContext->request;
		$threadUids = $this->parseThreadUids($request->getParameter('thread_uids', 'GET', ''));
		$ownPosts = $this->parseOwnPosts($request->getParameter('you', 'GET', ''));
		$seenMap = $this->parseSeen($request->getParameter('seen', 'GET', ''));
		$wantNewThreads = $request->getParameter('newthreads', 'GET', '') !== '';

		$dbSettings = getDatabaseSettings();
		$repo = new threadWatcherRepository(
			databaseConnection::getInstance(),
			$dbSettings['THREAD_TABLE'],
			$dbSettings['POST_TABLE'],
			$dbSettings['DELETED_POSTS_TABLE'],
			$dbSettings['BOARD_TABLE'],
			$dbSettings['FILE_TABLE'],
			$dbSettings['QUOTE_LINK_TABLE']
		);

		$defaultComment = (string) $this->getConfig('DEFAULT_NOCOMMENT', '');
		$response = ['threads' => [], 'deleted' => []];

		// Watched-thread counts / quote-replies.
		if (!empty($threadUids)) {
			$rows = $repo->batchGetThreadMeta($threadUids);
			$quoteCounts = $repo->batchGetQuoteCounts($threadUids, $ownPosts);

			// Resolve each thread's first-unread post number from the client's seen counts,
			// scoped to the threads actually requested.
			$firstUnread = [];
			$seenScoped = array_intersect_key($seenMap, array_flip($threadUids));
			if (!empty($seenScoped)) {
				$firstUnread = $repo->batchGetFirstUnreadNo($seenScoped);
			}

			$found = [];
			foreach ($rows as $row) {
				$threadUid = (string) $row['thread_uid'];
				$found[$threadUid] = [
					'post_count'  => (int) $row['post_count'],
					'board_title' => (string) $row['board_title'],
					'label'       => $this->buildThreadLabel(
						(string) $row['subject'],
						(string) $row['comment'],
						(string) $row['op_file_name'],
						$defaultComment
					),
					'quote_count' => $quoteCounts[$threadUid] ?? 0,
					// Post number of the first unread reply (null when nothing is unread),
					// so the client can link straight to it.
					'first_unread_no' => $firstUnread[$threadUid] ?? null,
				];
			}

			// Any requested UID not in $found is deleted or non-existent
			$response['threads'] = $found;
			$response['deleted'] = array_values(array_diff($threadUids, array_keys($found)));
		}

		// New-thread alerts across listed, non-blacklisted boards.
		if ($wantNewThreads) {
			$response['newThreads'] = $this->buildNewThreads($repo, $request, $defaultComment);
		}

		renderJsonPage($response);
	}

	/**
	 * Build the new-threads payload: the newest thread time (high-water marker) and any
	 * threads created since the client's last marker. Boards on the user's overboard
	 * blacklist are excluded server-side.
	 */
	private function buildNewThreads(threadWatcherRepository $repo, request $request, string $defaultComment): array {
		$blacklist = $this->getBoardBlacklist();
		$since = (string) $request->getParameter('since', 'GET', '');

		// First run (or malformed marker): seed silently with the current newest time.
		if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $since)) {
			return ['latest' => $repo->getLatestThreadTime(), 'items' => []];
		}

		// Items to notify about are blacklist-filtered, but the marker advances past every
		// board (blacklist included) so a re-enabled board doesn't replay its backlog.
		$rows = $repo->getNewThreadsSince($since, $blacklist, 25);
		$latest = $repo->getLatestThreadTime();
		if ($latest === '') {
			$latest = $since;
		}

		$websiteUrl = (string) $this->getConfig('WEBSITE_URL', '/');
		$liveIndex = (string) $this->getConfig('LIVE_INDEX_FILE', 'koko.php');

		$items = [];
		foreach ($rows as $row) {
			$items[] = [
				'board_uid'      => (int) $row['boardUID'],
				'board_title'    => (string) $row['board_title'],
				'thread_uid'     => (string) $row['thread_uid'],
				// OP post number, so the client can skip notifying for its own new threads.
				'post_op_number' => (int) $row['post_op_number'],
				'label'       => $this->buildThreadLabel(
					(string) $row['subject'],
					(string) $row['comment'],
					(string) $row['op_file_name'],
					$defaultComment
				),
				'url'         => $websiteUrl . rawurlencode((string) $row['board_identifier']) . '/' . $liveIndex . '?res=' . (int) $row['post_op_number'],
			];
		}

		return ['latest' => $latest, 'items' => $items];
	}

	/** Read the user's overboard board blacklist (cookie) as an array of board UIDs. */
	private function getBoardBlacklist(): array {
		$cookieService = $this->moduleContext->getContainer()->get('cookieService');
		$raw = (string) $cookieService->get('overboard_black_list', '');
		if ($raw === '') {
			return [];
		}
		$decoded = json_decode($raw, true);
		if (!is_array($decoded)) {
			return [];
		}
		$out = [];
		foreach ($decoded as $v) {
			if (is_numeric($v)) {
				$out[] = (int) $v;
			}
		}
		return $out;
	}

	/** Parse, sanitize and cap the comma-separated list of watched thread UIDs. */
	private function parseThreadUids(string $raw): array {
		if ($raw === '') {
			return [];
		}

		$threadUids = [];
		foreach (array_slice(explode(',', $raw), 0, 100) as $part) {
			$uid = trim($part);
			// thread_uid is VARCHAR(255); allow alphanumeric, dash, underscore only
			if ($uid !== '' && preg_match('/^[a-zA-Z0-9_\-]{1,255}$/', $uid)) {
				$threadUids[] = $uid;
			}
		}
		return $threadUids;
	}

	/**
	 * Parse the client's per-thread seen counts from a "uid:count,uid:count,..." list into a
	 * thread_uid => count map. Counts how many posts (OP + replies) the client has already read.
	 * Capped to keep the first-unread query bounded; malformed entries are skipped.
	 *
	 * @return array<string,int>
	 */
	private function parseSeen(string $raw): array {
		if ($raw === '') {
			return [];
		}

		$seen = [];
		foreach (array_slice(explode(',', $raw), 0, 100) as $part) {
			$bits = explode(':', trim($part), 2);
			if (count($bits) !== 2) {
				continue;
			}
			$uid = trim($bits[0]);
			$count = trim($bits[1]);
			// thread_uid is VARCHAR(255); count is a small non-negative integer.
			if ($uid === '' || !preg_match('/^[a-zA-Z0-9_\-]{1,255}$/', $uid)) {
				continue;
			}
			if (!preg_match('/^\d{1,9}$/', $count)) {
				continue;
			}
			$seen[$uid] = (int) $count;
		}
		return $seen;
	}

	/**
	 * Parse the caller's own posts from a "board:no,board:no,..." list into [boardUID, no] pairs.
	 * Capped so the quote-count query stays bounded.
	 */
	private function parseOwnPosts(string $raw): array {
		if ($raw === '') {
			return [];
		}

		$ownPosts = [];
		foreach (array_slice(explode(',', $raw), 0, 300) as $part) {
			if (preg_match('/^(\d{1,10}):(\d{1,10})$/', trim($part), $m)) {
				$ownPosts[] = [(int) $m[1], (int) $m[2]];
			}
		}
		return $ownPosts;
	}

	/**
	 * Build the display label for a watched thread:
	 *   1. the OP subject, else
	 *   2. a plain-text preview of the OP comment (unless it's the board's default comment), else
	 *   3. the OP's first attachment filename.
	 * The result is truncated to LABEL_MAX_LENGTH characters.
	 */
	private function buildThreadLabel(string $subject, string $comment, string $fileName, string $defaultComment): string {
		$subject = trim($subject);
		if ($subject !== '') {
			return $this->truncateLabel($subject);
		}

		// Normalize the stored (HTML) comment down to a single line of plain text.
		$plain = str_replace(['<br>', '<br/>', '<br />'], ' ', $comment);
		$plain = html_entity_decode(strip_tags($plain), ENT_QUOTES, 'UTF-8');
		$plain = trim(preg_replace('/\s+/u', ' ', $plain));

		$isDefault = $defaultComment !== '' && $plain === trim($defaultComment);

		if ($plain !== '' && !$isDefault) {
			return $this->truncateLabel($plain);
		}

		$fileName = trim($fileName);
		if ($fileName !== '') {
			return $this->truncateLabel($fileName);
		}

		// Nothing else to show (e.g. default comment with no file): fall back to the plain text.
		return $this->truncateLabel($plain);
	}

	private function truncateLabel(string $text): string {
		if (mb_strlen($text, 'UTF-8') <= self::LABEL_MAX_LENGTH) {
			return $text;
		}
		return mb_substr($text, 0, self::LABEL_MAX_LENGTH - 1, 'UTF-8') . "\u{2026}";
	}

	private function onRenderTopLinks(string &$topLinkHtml, $isReply): void {
		$linkText = sanitizeStr(_T('thread_watch_link'));
		$topLinkHtml .= ' [<a id="threadWatcherToplink" class="threadWatcherToplink" href="javascript:void(0)">' . $linkText . '</a>]';
	}

	private function onGenerateModuleHeader(string &$moduleHeader): void {
		$watchLabel = sanitizeStr(_T('thread_watch_label'));
		$unwatchLabel = sanitizeStr(_T('thread_unwatch_label'));

		$apiUrl = sanitizeStr($this->getModulePageURL(['pageName' => 'counts'], false));
		$moduleHeader .= '<meta name="threadWatcherApiUrl" content="' . $apiUrl . '">';

		$moduleHeader .= '<meta name="threadWatcherWatchLabel" content="' . $watchLabel . '">';
		$moduleHeader .= '<meta name="threadWatcherUnwatchLabel" content="' . $unwatchLabel . '">';

		// Empty state template
		$emptyHtml = $this->moduleContext->templateEngine->ParseBlock('THREAD_WATCHER_EMPTY', [
			'{$EMPTY_TEXT}' => sanitizeStr(_T('thread_watch_empty')),
		]);
		$moduleHeader .= $this->generateTemplate('threadWatcherEmptyTpl', $emptyHtml);

		// Watch list row template (placeholders filled by JS)
		$rowHtml = $this->moduleContext->templateEngine->ParseBlock('THREAD_WATCHER_ROW', [
			'{$UNWATCH_TITLE}' => sanitizeStr(_T('thread_watch_unwatch_title')),
			'{$REMOVE_ICON}' => "\u{2716}",
			'{$MARK_READ_TITLE}' => sanitizeStr(_T('thread_watch_mark_read_title')),
			'{$MARK_READ_ICON}' => "\u{2713}",
		]);
		$moduleHeader .= $this->generateTemplate('threadWatcherRowTpl', $rowHtml);

		// Content wrapper template
		$contentHtml = $this->moduleContext->templateEngine->ParseBlock('THREAD_WATCHER_CONTENT', [
			'{$REFRESH_TITLE}' => sanitizeStr(_T('thread_watch_refresh_title')),
			'{$REFRESH_ICON}' => "\u{27F3}",
			'{$UPDATED_LABEL}' => sanitizeStr(_T('thread_watch_updated_label')),
		]);
		$moduleHeader .= $this->generateTemplate('threadWatcherContentTpl', $contentHtml);
	}

	private function onRenderThreadWidget(array &$widgetArray, Post &$openingPost, array &$threadPosts): void {
		$watchWidget = $this->buildWidgetEntry(
			'javascript:void(0)',
			'watchThread',
			_T('thread_watch_label'),
			'',
			['thread_uid' => $openingPost->getThreadUid()]
		);

		$widgetArray[] = $watchWidget;
	}

}
