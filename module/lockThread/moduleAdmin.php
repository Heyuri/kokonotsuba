<?php

namespace Kokonotsuba\Modules\lockThread;

require_once __DIR__ . '/lockThreadLibrary.php';

use Kokonotsuba\post\Post;
use Kokonotsuba\module_classes\abstractModuleAdmin;
use Kokonotsuba\module_classes\traits\AuditableTrait;
use Kokonotsuba\module_classes\traits\ToggleActionTrait;
use Kokonotsuba\userRole;

class moduleAdmin extends abstractModuleAdmin {
	use ToggleActionTrait;
	use AuditableTrait;

	public function getRequiredRole(): userRole {
		return $this->getConfig('AuthLevels.CAN_LOCK');
	}

	public function getName(): string {
		return 'Thread locking tool';
	}

	public function getVersion(): string {
		return 'Koko 2025';
	}

	protected function getToggleFlagKey(): string { return 'stop'; }
	protected function getToggleActiveLabel(): string { return 'l'; }
	protected function getToggleInactiveLabel(): string { return 'L'; }
	protected function getToggleActiveTitle(): string { return 'Unlock thread'; }
	protected function getToggleInactiveTitle(): string { return 'Lock thread'; }
	protected function getToggleCssClass(): string { return 'adminLockFunction'; }
	protected function getToggleActionName(): string { return 'lock'; }
	protected function getToggleJsFile(): string { return 'lock.js'; }

	protected function getToggleUrlParams(Post $post): array {
		return ['post_uid' => $post->getUid()];
	}

	protected function getToggleLogLabel(bool $active): string {
		return $active ? 'Locked thread' : 'Unlocked thread';
	}

	public function initialize(): void {
		$this->registerToggleHooks();
	}

	protected function handleModuleRequest(): void {
		$this->handleToggleRequest();
	}
}