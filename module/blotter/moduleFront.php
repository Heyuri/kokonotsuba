<?php

namespace Kokonotsuba\Modules\blotter;

require_once __DIR__ . '/blotterEntry.php';
require_once __DIR__ . '/blotterRepository.php';
require_once __DIR__ . '/blotterService.php';

use Kokonotsuba\module_classes\abstractModuleFront;

use function Puchiko\strings\sanitizeStr;

/**
 * The blotter preview on the front page and its full list at ?module=blotter, when
 * SITE.SHOW_BLOTTER is on. Blotter edits forget the front page cache.
 */
class moduleFront extends abstractModuleFront {
	private ?blotterService $blotterService = null;

	public function initialize(): void {
		if (!$this->isShown()) {
			return;
		}

		$this->listen('FrontHeader', function (string &$headHtml): void {
			$this->includeStylesheet('blotter.css');
		});

		$this->listen('FrontInfo', function (string &$html): void {
			$rows = $this->rows((int) $this->getModuleConfig('BLOTTER_PREVIEW_AMOUNT', 5));

			$html .= $this->context->templateEngine->ParseBlock('BLOTTER_PREVIEW', [
				'{$MODULE_URL}' => sanitizeStr($this->context->links->module($this->moduleName)),
				'{$ENTRIES}' => $rows,
				'{$EMPTY}' => empty($rows),
			]);
		});
	}

	public function renderModulePage(): ?array {
		if (!$this->isShown()) {
			return null;
		}

		$rows = $this->rows(-1);

		return [
			'title' => 'Blotter',
			'html' => $this->context->templateEngine->ParseBlock('BLOTTER_PAGE', [
				'{$ROWS}' => $rows,
				'{$EMPTY}' => empty($rows),
			]),
		];
	}

	private function isShown(): bool {
		return (bool) $this->getConfig('SITE.SHOW_BLOTTER', false);
	}

	/** @return array[] public template rows, newest first; a negative limit takes all */
	private function rows(int $limit): array {
		if ($this->blotterService === null) {
			$container = $this->context->container;
			$tableNames = $container->get('tableNames');
			$this->blotterService = new blotterService(
				new blotterRepository($container->get('databaseConnection'), $tableNames['BLOTTER_TABLE'], $tableNames['ACCOUNT_TABLE']),
				$container->get('transactionManager')
			);
		}

		return array_map(fn(blotterEntry $entry) => $entry->toPublicTemplateRow(), $this->blotterService->getEntries($limit));
	}
}
