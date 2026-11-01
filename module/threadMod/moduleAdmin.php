<?php

namespace Kokonotsuba\Modules\threadMod;

require_once __DIR__ . '/threadRole.php';
require_once __DIR__ . '/threadModPolicy.php';
require_once __DIR__ . '/threadModRepository.php';
require_once __DIR__ . '/threadModService.php';

use Kokonotsuba\action_log\actionType;
use Kokonotsuba\cache\thread_fragment\threadFragments;
use Kokonotsuba\database\databaseConnection;
use Kokonotsuba\error\BoardException;
use Kokonotsuba\module_classes\abstractModuleAdmin;
use Kokonotsuba\module_classes\traits\AuditableTrait;
use Kokonotsuba\module_classes\traits\ToggleActionTrait;
use Kokonotsuba\module_classes\traits\listeners\OpeningPostListenerTrait;
use Kokonotsuba\post\Post;
use Kokonotsuba\thread\Thread;
use Kokonotsuba\userRole;

use function Kokonotsuba\libraries\_T;
use function Kokonotsuba\libraries\generateModerateForm;
use function Kokonotsuba\libraries\getBoardsByUIDs;
use function Kokonotsuba\libraries\getIdFromSession;
use function Kokonotsuba\libraries\rebuildBoardsByArray;
use function Puchiko\json\sendAjaxAndDetach;
use function Puchiko\request\redirect;
use function Puchiko\strings\sanitizeStr;

/**
 * Staff override of OP moderation, for anyone who may delete posts (CAN_DELETE_POST).
 */
class moduleAdmin extends abstractModuleAdmin {
	use AuditableTrait;
	use OpeningPostListenerTrait;
	use ToggleActionTrait {
		applyToggleState as applyFlagToggle;
	}

	/** Strip and undo-all sit together in the Thread submenu. */
	private const WIDGET_GROUP = 'threadMod';

	public const ACTION_OVERRIDE = 'threadMod.override';

	private threadModService $service;

	public function getRequiredRole(): userRole {
		return $this->getConfig('AuthLevels.CAN_DELETE_POST', userRole::LEV_JANITOR);
	}

	public function getName(): string {
		return 'Thread moderation (staff)';
	}

	public function getVersion(): string {
		return 'Koko BBS Release';
	}

	protected function getToggleFlagKey(): string { return threadModService::STRIP_FLAG; }
	protected function getToggleActiveLabel(): string { return 'op+'; }
	protected function getToggleInactiveLabel(): string { return 'op-'; }
	protected function getToggleActiveTitle(): string { return _T('threadmod_unstrip'); }
	protected function getToggleInactiveTitle(): string { return _T('threadmod_strip'); }
	protected function getToggleCssClass(): string { return 'adminThreadModStripFunction'; }
	protected function getToggleActionName(): string { return 'threadModStrip'; }
	protected function getToggleWidgetGroup(): string { return self::WIDGET_GROUP; }
	protected function getToggleJsFile(): string { return 'threadModAdmin.js'; }
	protected function getToggleUrlParams(Post $post): array { return ['post_uid' => $post->getUid()]; }
	protected function getToggleLogLabel(bool $active): string {
		return $active ? _T('threadmod_log_stripped') : _T('threadmod_log_unstripped');
	}

	public function initialize(): void {
		$policy = new threadModPolicy((int)$this->getModuleConfig('MAX_DELETIONS_PER_THREAD', 0));

		$this->service = new threadModService(
			new threadModRepository(
				databaseConnection::getInstance(),
				$this->moduleContext->getTableName('THREAD_MOD_TABLE'),
				$this->moduleContext->getTableName('POST_TABLE'),
				$this->moduleContext->getTableName('DELETED_POSTS_TABLE')
			),
			$this->moduleContext->postRepository,
			$this->moduleContext->threadRepository,
			fn(): string => $this->moduleContext->banService->hashIncomingToken(),
			$this->moduleContext->cookieService,
			$this->moduleContext->request,
			$policy,
			(bool)$this->getModuleConfig('ALLOW_PASSWORD_OWNERSHIP', true)
		);

		$this->registerActionType(self::ACTION_OVERRIDE, _T('threadmod_log_type_override'), 'post');

		// the strip toggle: thread menu, admin controls, mass moderation, its indicator
		$this->registerToggleHooks();
		$this->registerOpeningPostIndicator(
			$this->getToggleActionName(),
			'<span class="threadModStripIndicator" title="' . sanitizeStr(_T('threadmod_stripped')) . '">' . sanitizeStr(_T('threadmod_strip_indicator')) . '</span>',
			fn(Post $post) => $this->service->isStripped($post),
			15
		);

		$engine = $this->moduleContext->moduleEngine;
		$role = $this->getRequiredRole();
		$url = $this->getModulePageURL([], false, true);

		$engine->addRoleProtectedListener($role, 'ModeratePostWidget', function (array &$widgetArray, Post &$post) use ($url): void {
			if (threadModService::isOpDeleted($post)) {
				$widgetArray[] = $this->buildWidgetEntry($url, 'threadModUndo', _T('threadmod_staff_undo'), '', ['post_uid' => $post->getUid(), 'action' => 'undo']);
			}
		});

		$engine->addRoleProtectedListener($role, 'ModerateThreadWidget', function (array &$widgetArray, Post &$post, array &$threadPosts, ?Thread $thread = null) use ($url): void {
			$widgetArray[] = $this->buildWidgetEntry($url, 'threadModUndoAll', _T('threadmod_staff_undo_all'), '', ['post_uid' => $post->getUid(), 'action' => 'undoAll'], self::WIDGET_GROUP);
		});

		$engine->addRoleProtectedListener($role, 'PostAdminControls', function (string &$modControlSection, Post &$post) use ($url): void {
			if (threadModService::isOpDeleted($post)) {
				$modControlSection .= generateModerateForm(
					$this->getModulePageURL(['post_uid' => $post->getUid(), 'action' => 'undo'], false, true),
					'OPu', _T('threadmod_staff_undo'), 'adminThreadModUndoFunction', true
				);
			}
		});

		$engine->addRoleProtectedListener($role, 'ManagePostsControls', function (string &$modControlSection, Post &$post): void {
			if (threadModService::isOpDeleted($post)) {
				$modControlSection .= generateModerateForm(
					$this->getModulePageURL(['post_uid' => $post->getUid(), 'action' => 'undo'], false, true),
					'OPu', _T('threadmod_staff_undo'), 'adminThreadModUndoFunction', false
				);
			}
		});
	}

	protected function handleModuleRequest(): void {
		$action = (string)$this->moduleContext->request->getParameter('action', 'POST', '');

		match ($action) {
			'undo' => $this->handleUndo(),
			'undoAll' => $this->handleUndoAll(),
			default => $this->handleToggleRequest(),
		};
	}

	/** Reveal one post. */
	private function handleUndo(): void {
		$posts = $this->fetchRequestedPosts($this->getRequestedPostUids(), true);
		$cleared = [];

		foreach ($posts as $post) {
			if (!threadModService::isOpDeleted($post)) {
				continue;
			}

			$this->service->clearDeleted($post);
			$cleared[] = $post;
			$this->logAction(_T('threadmod_log_staff_undo', $post->getNumber()), $post->getBoardUID(), self::ACTION_OVERRIDE);
		}

		if (!$cleared) {
			throw new BoardException(_T('threadmod_not_deleted'), 409);
		}

		$this->finish($cleared, $cleared);
	}

	/** Reveal every OP-deleted reply in the selected threads. */
	private function handleUndoAll(): void {
		$openingPosts = array_values(array_filter(
			$this->fetchRequestedPosts($this->getRequestedPostUids(), true),
			fn(Post $post) => $post->isOp()
		));

		if (!$openingPosts) {
			throw new BoardException(_T('threadmod_not_a_thread'), 400);
		}

		$revealedUids = [];
		foreach ($openingPosts as $op) {
			$uids = $this->service->clearAllDeleted($op->getThreadUid());
			array_push($revealedUids, ...$uids);

			if (count($uids) === 1) {
				$this->logAction(_T('threadmod_log_staff_undo_all_one', $op->getNumber()), $op->getBoardUID(), self::ACTION_OVERRIDE);
			} elseif ($uids) {
				$this->logAction(_T('threadmod_log_staff_undo_all', count($uids), $op->getNumber()), $op->getBoardUID(), self::ACTION_OVERRIDE);
			}
		}

		$revealed = $revealedUids ? ($this->moduleContext->postRepository->getPostsByUids($revealedUids) ?: []) : [];
		$this->finish($openingPosts, $revealed);
	}

	/** Switching a thread's OP tools off also lets its mods go. */
	protected function applyToggleState(array $openingPosts, ?bool $state): array {
		$states = $this->applyFlagToggle($openingPosts, $state);
		$accountId = getIdFromSession();

		foreach ($openingPosts as $post) {
			if (!empty($states[$post->getUid()])) {
				$this->service->repository()->revokeAll($post->getThreadUid(), $accountId);
			}
		}

		return $states;
	}

	/**
	 * Forget and rebuild what changed. The window gets every revealed post redrawn, keyed by uid,
	 * so it can swap them in place instead of reloading.
	 *
	 * @param Post[] $posts    The posts whose threads changed.
	 * @param Post[] $revealed The posts that were revealed, as they stood before the change.
	 */
	private function finish(array $posts, array $revealed): void {
		$boards = getBoardsByUIDs(array_unique(array_map(fn(Post $post) => $post->getBoardUID(), $posts)));
		threadFragments::forgetPosts($posts);

		if ($this->moduleContext->request->isAjax()) {
			$threadView = $this->moduleContext->request->getParameter('threadView', 'POST', '1') !== '0';
			$html = [];

			// re-read, since the objects in hand still carry the hidden flag
			foreach ($revealed as $post) {
				$fresh = $this->moduleContext->postRepository->getPostByUid($post->getUid());
				if ($fresh) {
					$html[$fresh->getUid()] = threadModService::renderPostForWindow($this->moduleContext, $fresh, true, $threadView);
				}
			}

			sendAjaxAndDetach(['success' => true, 'count' => count($revealed), 'posts' => $html]);
			rebuildBoardsByArray($boards);
			exit;
		}

		rebuildBoardsByArray($boards);
		redirect($this->moduleContext->request->getReferer());
	}
}
