<?php

namespace Kokonotsuba\Modules\staffAlerts;

use Kokonotsuba\board\boardRebuilder;
use Kokonotsuba\module_classes\abstractModuleAdmin;
use Kokonotsuba\module_classes\traits\listeners\PostControlHooksTrait;
use Kokonotsuba\userRole;

use function Kokonotsuba\libraries\_T;
use function Puchiko\json\renderPrivateJsonPage;
use function Puchiko\strings\sanitizeStr;

/**
 * The staff alerts widget.
 *
 * A small docked panel listing the moderation queues this staff member can reach and how many
 * entries in each they have not seen yet. It knows nothing about any particular queue: the list is
 * assembled by dispatching the 'StaffAlerts' hook, which each interested module answers with one
 * row (see StaffAlertsListenerTrait). Rows whose module gates its listener on a role this account
 * doesn't have simply never arrive.
 *
 * Like the staff nav, it reaches the page two ways: a page rendered live for staff carries the
 * finished panel from the PageBottom hook, while static HTML — written once and served to
 * everyone — carries the same blocks as empty <template>s for staffAlerts.js to fill from an
 * endpoint that answers staff only. The counts are refreshed on a poll either way, which is what
 * the row template is always emitted for.
 */
class moduleAdmin extends abstractModuleAdmin {
	use PostControlHooksTrait;

	private string $alertsApiUrl;

	/** The widget is for staff generally; each row is gated by the module that contributes it. */
	public function getRequiredRole(): userRole {
		return userRole::LEV_JANITOR;
	}

	public function getName(): string {
		return 'Staff alerts widget';
	}

	public function getVersion(): string {
		return 'Koko BBS Release';
	}

	public function initialize(): void {
		// Board URL rather than the current request URI: the header this ends up in is also
		// written into static HTML, where the request URI is index.html and not a PHP entry point.
		$this->alertsApiUrl = $this->getModulePageURL(['pageName' => 'alerts'], false);

		$this->listenProtected('ModuleHeader', function (string &$moduleHeader) {
			$this->onGenerateModuleHeader($moduleHeader);
		});

		$this->listenProtected('PageBottom', function (string &$pageBottom) {
			$this->onRenderPageBottom($pageBottom);
		});
	}

	private function onGenerateModuleHeader(string &$moduleHeader): void {
		$moduleHeader .= '<link rel="stylesheet" href="'
			. sanitizeStr($this->getConfig('STATIC_URL') . 'css/module/staffAlerts.css') . '">';

		$this->includeScript('staffAlerts.js?v=9', $moduleHeader);

		$moduleHeader .= '<meta name="staffAlertsApi" content="' . sanitizeStr($this->alertsApiUrl) . '">';
		$moduleHeader .= '<meta name="staffAlertsInterval" content="'
			. (int) $this->getModuleConfig('POLL_SECONDS', 60) . '">';

		// Needed on every page: the poll redraws the rows from it, whoever built the panel.
		$moduleHeader .= $this->generateTemplate('staffAlertsRowTemplate', $this->renderBlankBlock('STAFF_ALERTS_ROW'));

		// The panel itself is only ever built by the client on a static page, which is the one
		// kind of page that cannot be given a finished one.
		if (boardRebuilder::isRenderingStaticHtml()) {
			$moduleHeader .= $this->generateTemplate('staffAlertsWidgetTemplate', $this->renderBlankBlock('STAFF_ALERTS_WIDGET'));
		}
	}

	/** The finished panel, for a page being rendered for the staff member reading it. */
	private function onRenderPageBottom(string &$pageBottom): void {
		if (boardRebuilder::isRenderingStaticHtml()) {
			return;
		}

		$alerts = $this->collectAlerts();

		// No queues this account can reach, nothing to dock.
		if (empty($alerts)) {
			return;
		}

		$pageBottom .= $this->renderWidget($alerts);
	}

	/** The module has one page and it is data. */
	public function ModulePage(): void {
		$this->handleAlertsRequest();
	}

	/**
	 * What this staff member has waiting, as data.
	 *
	 * Private and uncached: the numbers are per-account, and the route has already refused
	 * anyone below the widget's role.
	 */
	private function handleAlertsRequest(): void {
		renderPrivateJsonPage([
			'title' => _T('staffalerts_title'),
			'unreadTitle' => _T('staffalerts_unread_title'),
			'alerts' => $this->collectAlerts(),
		]);
	}

	// ─── Building the panel ───────────────────────────────────────

	/**
	 * Every queue this staff member can reach, normalised.
	 *
	 * @return array<int, array{key: string, label: string, count: int, url: string, title: string}>
	 */
	private function collectAlerts(): array {
		$alerts = [];
		$this->moduleContext->moduleEngine->dispatch('StaffAlerts', [&$alerts]);

		$normalised = [];
		foreach ($alerts as $alert) {
			$normalised[] = [
				'key' => (string) ($alert['key'] ?? ''),
				'label' => (string) ($alert['label'] ?? ''),
				'count' => max(0, (int) ($alert['count'] ?? 0)),
				'url' => (string) ($alert['url'] ?? ''),
				'title' => (string) ($alert['title'] ?? ''),
			];
		}

		return $normalised;
	}

	/** The whole panel, from the same blocks the client fills its own from. */
	private function renderWidget(array $alerts): string {
		$rowsHtml = '';
		$total = 0;

		foreach ($alerts as $alert) {
			$total += $alert['count'];
			$rowsHtml .= $this->renderRow($alert);
		}

		return $this->moduleContext->adminPageRenderer->ParseBlock('STAFF_ALERTS_WIDGET', [
			'{$WIDGET_TITLE}' => sanitizeStr(_T('staffalerts_title')),
			'{$UNREAD_TITLE}' => sanitizeStr(_T('staffalerts_unread_title')),
			'{$TOTAL}' => '(' . $total . ')',
			'{$TOTAL_CLASS}' => $total === 0 ? 'indicatorHidden' : '',
			'{$ROWS}' => $rowsHtml,
		]);
	}

	private function renderRow(array $alert): string {
		return $this->moduleContext->adminPageRenderer->ParseBlock('STAFF_ALERTS_ROW', [
			'{$URL}' => sanitizeStr($alert['url']),
			'{$LINK_TITLE}' => sanitizeStr($alert['title']),
			'{$LABEL}' => sanitizeStr($alert['label']),
			'{$COUNT}' => '(' . $alert['count'] . ')',
			'{$COUNT_CLASS}' => $alert['count'] === 0 ? 'indicatorHidden' : '',
			'{$ROW_CLASS}' => $alert['count'] === 0 ? '' : 'staffAlertsRowUnread',
			'{$UNREAD_TITLE}' => sanitizeStr(_T('staffalerts_unread_title')),
		]);
	}

	/**
	 * A block with every placeholder blanked, for the <template>s the client fills.
	 *
	 * The template engine leaves a placeholder it was given no value for standing as text, so
	 * these have to be passed explicitly rather than left out.
	 */
	private function renderBlankBlock(string $block): string {
		return $this->moduleContext->adminPageRenderer->ParseBlock($block, [
			'{$WIDGET_TITLE}' => '', '{$UNREAD_TITLE}' => '',
			'{$TOTAL}' => '', '{$TOTAL_CLASS}' => 'indicatorHidden', '{$ROWS}' => '',
			'{$URL}' => '#', '{$LINK_TITLE}' => '', '{$LABEL}' => '',
			'{$COUNT}' => '', '{$COUNT_CLASS}' => 'indicatorHidden', '{$ROW_CLASS}' => '',
		]);
	}
}
