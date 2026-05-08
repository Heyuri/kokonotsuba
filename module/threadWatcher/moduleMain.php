<?php

namespace Kokonotsuba\Modules\threadWatcher;

use Kokonotsuba\database\databaseConnection;
use Kokonotsuba\module_classes\abstractModuleMain;
use Kokonotsuba\module_classes\traits\listeners\FormFuncsListenerTrait;
use Kokonotsuba\module_classes\traits\listeners\IncludeScriptTrait;
use Kokonotsuba\module_classes\traits\listeners\ModuleHeaderListenerTrait;
use Kokonotsuba\module_classes\traits\listeners\ThreadWidgetListenerTrait;
use Kokonotsuba\post\Post;

use function Kokonotsuba\libraries\_T;
use function Puchiko\json\renderJsonPage;
use function Puchiko\json\renderJsonErrorPage;
use function Puchiko\strings\sanitizeStr;

require_once __DIR__ . '/threadWatcherRepository.php';

class moduleMain extends abstractModuleMain {
	use FormFuncsListenerTrait;
	use IncludeScriptTrait;
	use ModuleHeaderListenerTrait;
	use ThreadWidgetListenerTrait;

	public function getName(): string {
		return 'Thread watcher';
	}

	public function getVersion(): string {
		return '1.0';
	}

	public function initialize(): void {
		$this->addFormFuncLink('javascript:void(0)', _T('thread_watch_link'), true);
		$this->registerScript('threadWatcher.js');
		$this->listenModuleHeader('onGenerateModuleHeader');
		$this->listenThreadWidget('onRenderThreadWidget');
	}

	/** Batch counts endpoint: ?mode=module&load=threadWatcher&pageName=counts&thread_uids=uid1,uid2,... */
	public function ModulePage(): void {
		$pageName = $this->moduleContext->request->getParameter('pageName', 'GET', '');

		if ($pageName !== 'counts') {
			renderJsonErrorPage('Not found', 404);
		}

		$raw = $this->moduleContext->request->getParameter('thread_uids', 'GET', '');

		if ($raw === '') {
			renderJsonPage(['threads' => [], 'deleted' => []]);
		}

		// Parse, sanitize and cap the list of thread UIDs
		$parts = array_slice(explode(',', $raw), 0, 100);
		$threadUids = [];
		foreach ($parts as $part) {
			$uid = trim($part);
			// thread_uid is VARCHAR(255); allow alphanumeric, dash, underscore only
			if ($uid !== '' && preg_match('/^[a-zA-Z0-9_\-]{1,255}$/', $uid)) {
				$threadUids[] = $uid;
			}
		}

		if (empty($threadUids)) {
			renderJsonPage(['threads' => [], 'deleted' => []]);
		}

		$dbSettings = getDatabaseSettings();
		$repo = new threadWatcherRepository(
			databaseConnection::getInstance(),
			$dbSettings['THREAD_TABLE'],
			$dbSettings['POST_TABLE'],
			$dbSettings['DELETED_POSTS_TABLE']
		);

		$rows = $repo->batchGetThreadCounts($threadUids);

		// Index results by thread_uid
		$found = [];
		foreach ($rows as $row) {
			$found[$row['thread_uid']] = [
				'post_count' => (int) $row['post_count'],
				'subject'    => (string) $row['subject'],
			];
		}

		// Any requested UID not in $found is deleted or non-existent
		$deleted = array_values(array_diff($threadUids, array_keys($found)));

		renderJsonPage(['threads' => $found, 'deleted' => $deleted]);
	}

	private function onGenerateModuleHeader(string &$moduleHeader): void {
		$linkText = sanitizeStr(_T('thread_watch_link'));
		$watchLabel = sanitizeStr(_T('thread_watch_label'));
		$unwatchLabel = sanitizeStr(_T('thread_unwatch_label'));

		$apiUrl = sanitizeStr($this->getModulePageURL(['pageName' => 'counts'], false));
		$moduleHeader .= '<meta name="threadWatcherApiUrl" content="' . $apiUrl . '">';

		$moduleHeader .= '<meta name="threadWatcherLinkText" content="' . $linkText . '">';
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
		]);
		$moduleHeader .= $this->generateTemplate('threadWatcherRowTpl', $rowHtml);

		// Content wrapper template
		$contentHtml = $this->moduleContext->templateEngine->ParseBlock('THREAD_WATCHER_CONTENT', []);
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
