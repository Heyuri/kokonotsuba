<?php

namespace Kokonotsuba\Modules\sticky;

require_once __DIR__ . '/stickyLibrary.php';
require_once __DIR__ . '/stickyRepository.php';

use Kokonotsuba\database\databaseConnection;
use Kokonotsuba\error\BoardException;
use Kokonotsuba\post\Post;
use Kokonotsuba\module_classes\abstractModuleAdmin;
use Kokonotsuba\module_classes\traits\AuditableTrait;
use Kokonotsuba\module_classes\traits\ToggleActionTrait;
use Kokonotsuba\userRole;

use function Kokonotsuba\libraries\generateModerateForm;

class moduleAdmin extends abstractModuleAdmin {
	use ToggleActionTrait;
	use AuditableTrait;

	private stickyRepository $stickyRepository;

	public function getRequiredRole(): userRole {
		return $this->getConfig('AuthLevels.CAN_STICKY', userRole::LEV_MODERATOR);
	}

	public function getName(): string {
		return 'Sticky tools';
	}

	public function getVersion(): string {
		return 'Koko 2025';
	}

	protected function getToggleFlagKey(): string { return 'sticky'; }
	protected function getToggleActiveLabel(): string { return 's'; }
	protected function getToggleInactiveLabel(): string { return 'S'; }
	protected function getToggleActiveTitle(): string { return 'Unsticky thread'; }
	protected function getToggleInactiveTitle(): string { return 'Sticky thread'; }
	protected function getToggleCssClass(): string { return 'adminStickyFunction'; }
	protected function getToggleActionName(): string { return 'sticky'; }
	protected function getToggleJsFile(): string { return 'sticky.js'; }

	protected function getToggleUrlParams(Post $post): array {
		return ['post_uid' => $post->getUid()];
	}

	protected function getToggleLogLabel(bool $active): string {
		return $active ? 'Stickied thread' : 'Un-stickied thread';
	}

	public function initialize(): void {
		$this->stickyRepository = new stickyRepository(
			databaseConnection::getInstance(),
			$this->moduleContext->getTableName('THREAD_TABLE')
		);

		$this->registerToggleHooks();
	}

	protected function renderToggleButton(string &$modfunc, Post $post, bool $noScript): void {
		$isActive = $this->stickyRepository->isSticky($post->getThreadUid());
		$url = $this->generateToggleActionUrl($post);

		$modfunc .= generateModerateForm(
			$url,
			$isActive ? $this->getToggleActiveLabel() : $this->getToggleInactiveLabel(),
			$isActive ? $this->getToggleActiveTitle() : $this->getToggleInactiveTitle(),
			$this->getToggleCssClass(),
			$noScript
		);
	}

	protected function onRenderToggleWidget(array &$widgetArray, Post &$post): void {
		$isActive = $this->stickyRepository->isSticky($post->getThreadUid());
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

	protected function handleModuleRequest(): void {
		// thread_uid is what the older admin control forms send
		$threadUid = $this->moduleContext->request->getParameter('thread_uid');

		if ($threadUid !== null && !$this->moduleContext->request->hasParameter('post_uid')) {
			$openingPost = $this->moduleContext->postRepository->getOpeningPostFromThread($threadUid);

			if (!$openingPost) {
				throw new BoardException('ERROR: Thread does not exist.');
			}

			$this->handleToggleRequest([$openingPost->getUid()]);
			return;
		}

		$this->handleToggleRequest();
	}

	/**
	 * Sticky lives on the thread row rather than the post's flags, so the whole selection is two
	 * statements: one for the threads being stickied, one for those being un-stickied.
	 */
	protected function applyToggleState(array $openingPosts, ?bool $state): array {
		$threadUids = array_map(fn(Post $post) => $post->getThreadUid(), $openingPosts);

		// only needed when flipping each thread from whatever it is now
		$currentlySticky = $state === null
			? array_flip($this->stickyRepository->getStickyThreadUids($threadUids))
			: [];

		$states = [];
		$toSticky = [];
		$toUnsticky = [];

		foreach ($openingPosts as $post) {
			$threadUid = $post->getThreadUid();
			$next = $state ?? !isset($currentlySticky[$threadUid]);

			if ($next) {
				$toSticky[] = $threadUid;
			} else {
				$toUnsticky[] = $threadUid;
			}

			$states[$post->getUid()] = $next;
		}

		$this->stickyRepository->setStickyForThreads($toSticky, true);
		$this->stickyRepository->setStickyForThreads($toUnsticky, false);

		return $states;
	}

}