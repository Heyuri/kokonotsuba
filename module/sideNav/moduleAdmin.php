<?php

namespace Kokonotsuba\Modules\sideNav;

require_once __DIR__ . '/sideNavRepository.php';
require_once __DIR__ . '/sideNavRenderer.php';
require_once __DIR__ . '/sideNavActionType.php';

use Kokonotsuba\database\databaseConnection;
use Kokonotsuba\error\BoardException;
use Kokonotsuba\front_pages\frontPageCache;
use Kokonotsuba\module_classes\abstractModuleAdmin;
use Kokonotsuba\module_classes\traits\AuditableTrait;
use Kokonotsuba\module_classes\traits\listeners\PostControlHooksTrait;
use Kokonotsuba\userRole;

use const Kokonotsuba\GLOBAL_BOARD_UID;

use function Kokonotsuba\libraries\_T;
use function Kokonotsuba\libraries\getCsrfHiddenInput;
use function Kokonotsuba\libraries\rebuildAllBoards;
use function Puchiko\request\redirect;
use function Puchiko\strings\sanitizeStr;
use function Kokonotsuba\libraries\html\getBoardStyleSet;

/**
 * Manage the side nav's groups and links. Each link sits in one group.
 *
 * The page is one form holding the whole layout. Every button submits it with an action
 * (save, add, delete, move), which is applied to the submitted layout before it is saved, so the
 * page works without JS. sideNavAdmin.js does the same actions in place and saves once.
 */
class moduleAdmin extends abstractModuleAdmin {
	use AuditableTrait;
	use PostControlHooksTrait;

	private sideNavRepository $sideNavRepository;
	private string $modulePage;
	private int $newKeys = 0;

	public function getRequiredRole(): userRole {
		return $this->getConfig('AuthLevels.CAN_EDIT_SIDE_NAV', userRole::LEV_ADMIN);
	}

	public function getName(): string {
		return 'Side navigation manager';
	}

	public function getVersion(): string {
		return 'Koko 2026';
	}

	public function initialize(): void {
		$this->sideNavRepository = new sideNavRepository(
			databaseConnection::getInstance(),
			$this->moduleContext->getTableName('SIDE_NAV_GROUP_TABLE'),
			$this->moduleContext->getTableName('SIDE_NAV_LINK_TABLE')
		);
		$this->modulePage = $this->getModulePageURL([], false);

		$this->registerLinksAboveBarHook(_T('admin_nav_sidenav_title'), $this->modulePage, _T('admin_nav_sidenav'));

		$this->listenProtected('ModuleHeader', function (string &$moduleHeader) {
			if ($this->moduleContext->request->getParameter('load', 'GET') === 'sideNav') {
				$this->includeStylesheet('sideNavAdmin.css', $moduleHeader);
				$this->includeScript('sideNavAdmin.js', $moduleHeader);
			}
		});
	}

	protected function handleModuleRequest(): void {
		[$verb, $target] = array_pad(explode(':', (string) $this->moduleContext->request->getParameter('action', 'POST', 'save'), 2), 2, '');

		$layout = $this->applyAction($this->submittedLayout(), $verb, $target);
		$this->saveLayout($layout);

		$links = array_sum(array_map(fn(array $group) => count($group['links']), $layout));
		$this->logAction("Saved side nav ($verb): " . count($layout) . " groups, $links links", GLOBAL_BOARD_UID, sideNavActionType::SIDE_NAV);
		rebuildAllBoards();
		frontPageCache::forSite()->forgetAll();

		redirect($this->modulePage);
	}

	public function ModulePage(): void {
		$renderer = $this->moduleContext->adminPageRenderer;
		$navRenderer = $this->navRenderer();
		$layout = $navRenderer->resolve($this->sideNavRepository->getLayout());

		$groupsHtml = '';
		foreach ($layout as $group) {
			$groupsHtml .= $this->renderGroup((string) $group['id'], $group, $layout);
		}

		$html = $renderer->ParseBlock('SIDE_NAV_ADMIN', [
			'{$MODULE_URL}' => sanitizeStr($this->modulePage),
			'{$CSRF_TOKEN}' => getCsrfHiddenInput(),
			'{$GROUPS}' => $groupsHtml,
			'{$PREVIEW}' => $navRenderer->render($layout),
			'{$DATA}' => json_encode([
				'templates' => $navRenderer->templates() + [
					'adminGroup' => $this->renderGroup('%%KEY%%', ['title' => '%%TITLE%%', 'kind' => 'links', 'links' => []], [], true),
					'adminLink' => $this->renderLink('%%KEY%%', ['label' => '%%LABEL%%', 'url' => '%%URL%%'], '%%GROUP_OPTIONS%%'),
				],
			], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
		]);

		echo $renderer->ParsePage('GLOBAL_ADMIN_PAGE_CONTENT', ['{$PAGE_CONTENT}' => $html], true);
	}

	private function navRenderer(): sideNavRenderer {
		return new sideNavRenderer(
			$this->moduleContext->adminPageRenderer,
			$this->moduleContext->boardService->getAllListedBoards() ?? [],
			(string) $this->getConfig('HOME'),
			$this->moduleContext->board->getBoardURL(true) . '?mode=overboard',
			array_keys(getBoardStyleSet($this->moduleContext->board->loadBoardConfig())[1])
		);
	}

	// ─── Rendering ───────────────────────────────────────────────────────────

	/** One group's box. With $raw the title is a token and is not escaped. */
	private function renderGroup(string $key, array $group, array $layout, bool $raw = false): string {
		$renderer = $this->moduleContext->adminPageRenderer;
		$kind = in_array($group['kind'], ['boards', 'main'], true) ? $group['kind'] : 'links';

		$rows = '';
		foreach ($group['links'] as $link) {
			$rows .= $link['source'] ?? null
				? $this->renderAutoLink($link, $key)
				: $this->renderLink($link['key'], $link, $this->groupOptions($layout, $key));
		}

		return $renderer->ParseBlock('SIDE_NAV_ADMIN_GROUP', [
			'{$KEY}' => $key,
			'{$KIND}' => $kind,
			'{$TITLE}' => $raw ? $group['title'] : sanitizeStr($group['title']),
			'{$KIND_LABEL}' => match ($kind) {
				'boards' => '<span class="sideNavBoxKind" title="New boards are added at the end">Listed boards</span>',
				'main' => '<span class="sideNavBoxKind">Home, overboard, board directories, hide nav</span>',
				default => '',
			},
			'{$ROWS}' => $rows,
			'{$ADD_ROW}' => $renderer->ParseBlock('SIDE_NAV_ADMIN_ADD_LINK', ['{$KEY}' => $key]),
		]);
	}

	/** An automatic link's row: relabel (empty keeps the default), hide or move it, but not delete it. */
	private function renderAutoLink(array $link, string $groupKey): string {
		$plain = html_entity_decode(strip_tags($link['default_label']), ENT_QUOTES | ENT_HTML5, 'UTF-8');
		return $this->moduleContext->adminPageRenderer->ParseBlock('SIDE_NAV_ADMIN_AUTO_LINK', [
			'{$KEY}' => sanitizeStr($link['key']),
			'{$SOURCE}' => sanitizeStr($link['source']),
			'{$GROUP}' => sanitizeStr($groupKey),
			'{$LABEL}' => sanitizeStr($link['label']),
			'{$PLACEHOLDER}' => sanitizeStr($plain),
			// HTML, kept for the preview to draw as the server does
			'{$DEFAULT_LABEL}' => sanitizeStr($link['default_label']),
			'{$DEFAULT_URL}' => sanitizeStr($link['default_url']),
			'{$LINK_TITLE}' => sanitizeStr(html_entity_decode(strip_tags($link['link_title']), ENT_QUOTES | ENT_HTML5, 'UTF-8')),
			'{$HIDDEN_CHECKED}' => empty($link['hidden']) ? '' : ' checked',
		]);
	}

	/** One link's row. Tokens pass through sanitizeStr unchanged. */
	private function renderLink(string $key, array $link, string $groupOptions): string {
		return $this->moduleContext->adminPageRenderer->ParseBlock('SIDE_NAV_ADMIN_LINK', [
			'{$KEY}' => sanitizeStr($key),
			'{$LABEL}' => sanitizeStr($link['label']),
			'{$URL}' => sanitizeStr($link['url']),
			'{$GROUP_OPTIONS}' => $groupOptions,
		]);
	}

	/** Options for every group, each of which can hold links. */
	private function groupOptions(array $layout, string $selected): string {
		$html = '';
		foreach ($layout as $group) {
			$key = (string) $group['id'];
			$html .= '<option value="' . $key . '"' . ($key === $selected ? ' selected' : '') . '>' . sanitizeStr($group['title']) . '</option>';
		}
		return $html;
	}

	// ─── The submitted layout ────────────────────────────────────────────────

	/**
	 * The layout as the form holds it, in form order. Groups and links are keyed by id, or by
	 * "new..." for ones added in the browser.
	 *
	 * @return array<int, array{key: string, id: ?int, kind: string, title: string, links: array}>
	 */
	private function submittedLayout(): array {
		$request = $this->moduleContext->request;
		$existingGroups = array_column($this->sideNavRepository->getGroups(), null, 'id');
		$existingLinks = array_column($this->sideNavRepository->getLinks(), null, 'id');

		$groups = [];
		foreach ((array) $request->getParameter('group', 'POST', []) as $key => $fields) {
			$key = (string) $key;
			$id = ctype_digit($key) && isset($existingGroups[(int) $key]) ? (int) $key : null;
			if (!is_array($fields) || ($id === null && !preg_match('/^new\w+$/', $key))) {
				throw new BoardException(_T('sidenav_invalid_layout'));
			}
			// only the seeded listed boards group is a 'boards' group; new groups hold links
			$groups[$key] = [
				'key' => $key,
				'id' => $id,
				'kind' => $id !== null ? $existingGroups[$id]['kind'] : 'links',
				'title' => (string) ($fields['title'] ?? ''),
				'links' => [],
			];
		}

		foreach ((array) $request->getParameter('link', 'POST', []) as $key => $fields) {
			$key = (string) $key;
			$groupKey = is_array($fields) ? (string) ($fields['group'] ?? '') : '';
			if (!isset($groups[$groupKey])) {
				throw new BoardException(_T('sidenav_invalid_group'));
			}
			// an automatic link stays in a group of its own kind
			$source = isset($fields['source']) ? (string) $fields['source'] : null;
			if ($source !== null && (!preg_match('/^(board:\d+|home|overboard|dirs|hide)$/', $source)
				|| $groups[$groupKey]['kind'] !== (str_starts_with($source, 'board:') ? 'boards' : 'main'))) {
				throw new BoardException(_T('sidenav_invalid_layout'));
			}
			$groups[$groupKey]['links'][] = [
				'key' => $key,
				'id' => ctype_digit($key) && isset($existingLinks[(int) $key]) ? (int) $key : null,
				'label' => (string) ($fields['label'] ?? ''),
				'url' => (string) ($fields['url'] ?? ''),
				'source' => $source,
				'hidden' => empty($fields['hidden']) ? 0 : 1,
			];
		}

		return array_values($groups);
	}

	/** Apply one button's action to the submitted layout. */
	private function applyAction(array $layout, string $verb, string $target): array {
		$request = $this->moduleContext->request;
		$groupIndex = array_search($target, array_column($layout, 'key'), true);

		switch ($verb) {
			case 'save':
				break;
			case 'add_group':
				$layout[] = ['key' => $this->newKey(), 'id' => null, 'kind' => 'links', 'title' => (string) $request->getParameter('new_group_title', 'POST', ''), 'links' => []];
				break;
			case 'add_link':
				if ($groupIndex === false) {
					throw new BoardException(_T('sidenav_invalid_group'));
				}
				$fields = (array) (((array) $request->getParameter('new_link', 'POST', []))[$target] ?? []);
				$layout[$groupIndex]['links'][] = ['key' => $this->newKey(), 'id' => null, 'label' => (string) ($fields['label'] ?? ''), 'url' => (string) ($fields['url'] ?? ''), 'source' => null, 'hidden' => 0];
				break;
			case 'delete_group':
				if ($groupIndex !== false) {
					array_splice($layout, $groupIndex, 1);
				}
				break;
			case 'group_up':
			case 'group_down':
				if ($groupIndex !== false) {
					$layout = $this->swap($layout, $groupIndex, $verb === 'group_up' ? -1 : 1);
				}
				break;
			case 'delete_link':
			case 'link_up':
			case 'link_down':
				foreach ($layout as $g => $group) {
					$i = array_search($target, array_column($group['links'], 'key'), true);
					if ($i === false) {
						continue;
					}
					$layout[$g]['links'] = $verb === 'delete_link'
						? array_values(array_diff_key($group['links'], [$i => true]))
						: $this->swap($group['links'], $i, $verb === 'link_up' ? -1 : 1);
				}
				break;
			default:
				throw new BoardException(_T('sidenav_invalid_layout'));
		}

		return $layout;
	}

	private function swap(array $list, int $index, int $step): array {
		$other = $index + $step;
		if (isset($list[$other])) {
			[$list[$index], $list[$other]] = [$list[$other], $list[$index]];
		}
		return $list;
	}

	private function newKey(): string {
		return 'new' . (++$this->newKeys);
	}

	// ─── Saving ──────────────────────────────────────────────────────────────

	/**
	 * Write the layout: groups and links in the order given, anything not in it deleted.
	 * Everything is validated before anything is written.
	 */
	private function saveLayout(array $layout): void {
		$claimed = [];
		foreach ($layout as $g => $group) {
			$layout[$g]['title'] = $this->requireText($group['title']);
			foreach ($group['links'] as $l => $link) {
				// an id is kept only the first time it appears
				if ($link['id'] !== null && isset($claimed[$link['id']])) {
					$layout[$g]['links'][$l]['id'] = null;
				} elseif ($link['id'] !== null) {
					$claimed[$link['id']] = true;
				}
				if ($link['source'] !== null) {
					// an automatic link keeps only its label override; its URL is derived
					$layout[$g]['links'][$l]['label'] = mb_substr(trim($link['label']), 0, 255);
					$layout[$g]['links'][$l]['url'] = '';
				} else {
					$layout[$g]['links'][$l]['label'] = $this->requireText($link['label']);
					$layout[$g]['links'][$l]['url'] = $this->requireUrl($link['url']);
				}
			}
		}

		$existingGroupIds = array_map(fn(array $group) => (int) $group['id'], $this->sideNavRepository->getGroups());
		$existingLinkIds = array_map(fn(array $link) => (int) $link['id'], $this->sideNavRepository->getLinks());

		$this->moduleContext->transactionManager->run(function () use ($layout, $claimed, $existingGroupIds, $existingLinkIds) {
			$keptGroups = [];
			foreach ($layout as $order => $group) {
				if ($group['id'] !== null) {
					$this->sideNavRepository->updateGroup($group['id'], $group['title'], $order);
					$groupId = $group['id'];
				} else {
					$groupId = $this->sideNavRepository->addGroup($group['title'], 'links', $order);
				}
				$keptGroups[] = $groupId;

				foreach ($group['links'] as $linkOrder => $link) {
					if ($link['id'] !== null) {
						$this->sideNavRepository->updateLink($link['id'], $link, $groupId, $linkOrder);
					} else {
						$this->sideNavRepository->addLink($link, $groupId, $linkOrder);
					}
				}
			}

			$this->sideNavRepository->deleteLinks(array_values(array_diff($existingLinkIds, array_keys($claimed))));
			$this->sideNavRepository->deleteGroups(array_values(array_diff($existingGroupIds, $keptGroups)));
		});
	}

	private function requireText(mixed $value, int $maxLength = 255): string {
		$text = trim((string) $value);
		if ($text === '') {
			throw new BoardException(_T('sidenav_empty_field'));
		}
		return mb_substr($text, 0, $maxLength);
	}

	/** Relative URLs, http(s), irc(s) and mailto only, so a link can never run script. */
	private function requireUrl(mixed $value): string {
		$url = $this->requireText($value, 2048);
		$parts = preg_match('/[\x00-\x20\x7f]/', $url) ? false : parse_url($url);
		$scheme = $parts['scheme'] ?? null;
		if ($parts === false || ($scheme !== null && !in_array(strtolower($scheme), ['http', 'https', 'irc', 'ircs', 'mailto'], true))) {
			throw new BoardException(_T('sidenav_invalid_url'));
		}
		return $url;
	}
}
