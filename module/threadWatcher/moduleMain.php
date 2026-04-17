<?php

namespace Kokonotsuba\Modules\threadWatcher;

use Kokonotsuba\module_classes\abstractModuleMain;
use Kokonotsuba\module_classes\traits\listeners\FormFuncsListenerTrait;
use Kokonotsuba\module_classes\traits\listeners\IncludeScriptTrait;
use Kokonotsuba\module_classes\traits\listeners\ModuleHeaderListenerTrait;
use Kokonotsuba\module_classes\traits\listeners\ThreadWidgetListenerTrait;
use Kokonotsuba\post\Post;

use function Kokonotsuba\libraries\_T;
use function Puchiko\strings\sanitizeStr;

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

	private function onGenerateModuleHeader(string &$moduleHeader): void {
		$linkText = sanitizeStr(_T('thread_watch_link'));
		$watchLabel = sanitizeStr(_T('thread_watch_label'));
		$unwatchLabel = sanitizeStr(_T('thread_unwatch_label'));

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
