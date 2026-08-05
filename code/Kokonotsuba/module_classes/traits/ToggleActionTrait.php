<?php

namespace Kokonotsuba\module_classes\traits;

use Kokonotsuba\error\BoardException;
use Kokonotsuba\module_classes\traits\listeners\MassModerateListenerTrait;
use Kokonotsuba\post\Post;
use Kokonotsuba\thread\Thread;

use function Kokonotsuba\libraries\generateModerateForm;
use function Kokonotsuba\libraries\getBoardsByUIDs;
use function Kokonotsuba\libraries\rebuildBoardsByArray;
use function Puchiko\json\sendAjaxAndDetach;
use function Puchiko\request\redirect;

/**
 * Trait for thread-level toggle modules (lock, sticky, autosage).
 *
 * Provides shared hook registration, button rendering, widget rendering,
 * and module header handling for modules that toggle a flag on a thread's OP.
 *
 * Requires the using class to extend abstractModuleAdmin.
 */
trait ToggleActionTrait {
	use MassModerateListenerTrait;

	abstract protected function getToggleFlagKey(): string;
	abstract protected function getToggleActiveLabel(): string;
	abstract protected function getToggleInactiveLabel(): string;
	abstract protected function getToggleActiveTitle(): string;
	abstract protected function getToggleInactiveTitle(): string;
	abstract protected function getToggleCssClass(): string;
	abstract protected function getToggleActionName(): string;
	abstract protected function getToggleJsFile(): string;
	abstract protected function getToggleUrlParams(Post $post): array;

	/** Wording for the action log, e.g. "Locked thread" / "Unlocked thread". */
	abstract protected function getToggleLogLabel(bool $active): string;

	protected function shouldRegisterThreadAdminControls(): bool {
		return true;
	}

	protected function registerToggleHooks(): void {
		$this->moduleContext->moduleEngine->addRoleProtectedListener(
			$this->getRequiredRole(),
			'ManagePostsThreadControls',
			function(string &$modControlSection, Post &$post) {
				$this->renderToggleButton($modControlSection, $post, false);
			}
		);

		if ($this->shouldRegisterThreadAdminControls()) {
			$this->moduleContext->moduleEngine->addRoleProtectedListener(
				$this->getRequiredRole(),
				'ThreadAdminControls',
				function(string &$modControlSection, Post &$post) {
					$this->renderToggleButton($modControlSection, $post, true);
				}
			);
		}

		$this->moduleContext->moduleEngine->addRoleProtectedListener(
			$this->getRequiredRole(),
			'ModerateThreadWidget',
			function(array &$widgetArray, Post &$post, array &$threadPosts, ?Thread $thread = null) {
				$this->onRenderToggleWidget($widgetArray, $post, $thread);
			}
		);

		$this->moduleContext->moduleEngine->addRoleProtectedListener(
			$this->getRequiredRole(),
			'ModuleAdminHeader',
			function(&$moduleHeader) {
				$this->onToggleModuleHeader($moduleHeader);
			}
		);

		$this->listenMassModerateTools('onToggleMassModerateTools', $this->getToggleMassPriority());
	}

	protected function getToggleMassPriority(): int {
		return 50;
	}

	/** All three thread toggles read as one area of moderation, so they share a heading. */
	protected function getToggleMassGroup(): string {
		return 'Thread';
	}

	/**
	 * Set and unset are separate entries rather than one toggle: a selection of threads is rarely
	 * all in the same state, and staff mean "make these sticky", not "flip each of these".
	 */
	protected function onToggleMassModerateTools(array &$tools): void {
		$priority = $this->getToggleMassPriority();
		$actionName = $this->getToggleActionName();

		$tools[] = $this->buildMassTool($actionName . 'On', $this->getToggleInactiveTitle(), [
			'group' => $this->getToggleMassGroup(),
			'scope' => 'thread',
			'effect' => 'flag',
			'indicator' => 'indicator-' . $actionName,
			'params' => ['action' => $actionName, 'state' => 1],
			'priority' => $priority,
		]);

		$tools[] = $this->buildMassTool($actionName . 'Off', $this->getToggleActiveTitle(), [
			'group' => $this->getToggleMassGroup(),
			'scope' => 'thread',
			'effect' => 'flag',
			'indicator' => 'indicator-' . $actionName,
			'params' => ['action' => $actionName, 'state' => 0],
			'priority' => $priority - 1,
		]);
	}

	/**
	 * Handle both shapes of request: the per-post controls flip one thread, the [Moderate] window
	 * sets an explicit state on a whole selection. Either way it is one read, one write pass and
	 * one rebuild per board involved.
	 */
	protected function handleToggleRequest(?array $postUids = null): void {
		$openingPosts = array_values(array_filter(
			$this->fetchRequestedPosts($postUids ?? $this->getRequestedPostUids(), true),
			fn(Post $post) => $post->isOp()
		));

		if (!$openingPosts) {
			throw new BoardException('ERROR: Cannot ' . $this->getToggleActionName() . ' a reply.');
		}

		// no state means the historic behaviour: flip whatever the thread is now
		$requestedState = $this->moduleContext->request->getParameter('state', 'POST');
		$state = $requestedState === null ? null : (bool)(int)$requestedState;

		$states = [];
		$this->moduleContext->transactionManager->run(function () use ($openingPosts, $state, &$states): void {
			$states = $this->applyToggleState($openingPosts, $state);
			$this->logToggleActions($openingPosts, $states);
		});

		$boards = getBoardsByUIDs(array_unique(array_map(fn(Post $post) => $post->getBoardUID(), $openingPosts)));

		if ($this->moduleContext->request->isAjax()) {
			$results = [];
			foreach ($states as $postUid => $active) {
				$results[$postUid] = ['active' => $active];
			}

			// 'active' stays for the per-post widget, which flips one thread and reads it directly
			sendAjaxAndDetach([
				'success' => true,
				'active' => $states[$openingPosts[0]->getUid()] ?? false,
				'results' => $results,
			]);

			rebuildBoardsByArray($boards);
			exit;
		}

		rebuildBoardsByArray($boards);

		redirect($this->moduleContext->request->getReferer());
	}

	/**
	 * Write the new state for every opening post and report what each ended up as.
	 *
	 * Flags live on the post row, so the whole selection goes out as a single statement. Modules
	 * keeping their state elsewhere (sticky, on the thread row) override this.
	 *
	 * @param Post[]    $openingPosts
	 * @param bool|null $state True/false to set, null to flip each thread.
	 * @return array<int, bool> post UID => resulting state.
	 */
	protected function applyToggleState(array $openingPosts, ?bool $state): array {
		$flagKey = $this->getToggleFlagKey();
		$statuses = [];
		$states = [];

		foreach ($openingPosts as $post) {
			$flags = $post->getFlags();
			$current = (bool)$flags->value($flagKey);
			$next = $state ?? !$current;

			if ($next !== $current) {
				$next ? $flags->add($flagKey) : $flags->remove($flagKey);
				$statuses[$post->getUid()] = $flags->toString();
			}

			$states[$post->getUid()] = $next;
		}

		$this->moduleContext->postRepository->setPostStatuses($statuses);

		return $states;
	}

	/**
	 * One log line per board and resulting state, listing the threads it covered.
	 *
	 * @param Post[]           $openingPosts
	 * @param array<int, bool> $states
	 */
	protected function logToggleActions(array $openingPosts, array $states): void {
		$grouped = [];

		foreach ($openingPosts as $post) {
			$active = !empty($states[$post->getUid()]);
			$grouped[$post->getBoardUID()][$active ? 1 : 0][] = 'No.' . $post->getNumber();
		}

		foreach ($grouped as $boardUid => $byState) {
			foreach ($byState as $active => $numbers) {
				$this->logAction($this->getToggleLogLabel((bool)$active) . ' ' . implode(', ', $numbers), (int)$boardUid);
			}
		}
	}

	protected function renderToggleButton(string &$modfunc, Post $post, bool $noScript): void {
		$isActive = $post->getFlags()->value($this->getToggleFlagKey());
		$url = $this->generateToggleActionUrl($post);

		$modfunc .= generateModerateForm(
			$url,
			$isActive ? $this->getToggleActiveLabel() : $this->getToggleInactiveLabel(),
			$isActive ? $this->getToggleActiveTitle() : $this->getToggleInactiveTitle(),
			$this->getToggleCssClass(),
			$noScript
		);
	}

	protected function onRenderToggleWidget(array &$widgetArray, Post &$post, ?Thread $thread = null): void {
		$isActive = $post->getFlags()->value($this->getToggleFlagKey());
		$url = $this->getModulePageURL([], false, true);
		$label = $isActive ? $this->getToggleActiveTitle() : $this->getToggleInactiveTitle();

		$widgetArray[] = $this->buildWidgetEntry(
			$url,
			$this->getToggleActionName(),
			$label,
			'',
			['post_uid' => $post->getUid()]
		);
	}

	protected function generateToggleActionUrl(Post $post): string {
		return $this->getModulePageURL(
			$this->getToggleUrlParams($post),
			false,
			true
		);
	}

	protected function onToggleModuleHeader(string &$moduleHeader): void {
		$this->includeScript($this->getToggleJsFile(), $moduleHeader);
	}
}
