<?php

namespace Kokonotsuba\Modules\autoSage;

require_once __DIR__ . '/autoSageLibrary.php';

use Kokonotsuba\post\Post;
use Kokonotsuba\module_classes\abstractModuleAdmin;
use Kokonotsuba\module_classes\traits\AuditableTrait;
use Kokonotsuba\module_classes\traits\ToggleActionTrait;
use Kokonotsuba\userRole;

// auto sage module made for kokonotsuba by deadking
class moduleAdmin extends abstractModuleAdmin {
	use ToggleActionTrait;
	use AuditableTrait;

    public function getRequiredRole(): userRole {
        return $this->getConfig('AuthLevels.CAN_AUTO_SAGE');
    }

	public function getName(): string {
		return 'Autosage tool';
	}

	public function getVersion(): string {
		return 'Koko 2025';
	}

	protected function getToggleFlagKey(): string { return 'as'; }
	protected function getToggleActiveLabel(): string { return 'as'; }
	protected function getToggleInactiveLabel(): string { return 'AS'; }
	protected function getToggleActiveTitle(): string { return 'Un-autosage'; }
	protected function getToggleInactiveTitle(): string { return 'Autosage'; }
	protected function getToggleCssClass(): string { return 'adminAutoSageFunction'; }
	protected function getToggleActionName(): string { return 'autosage'; }
	protected function getToggleJsFile(): string { return 'autosage.js'; }

	protected function getToggleUrlParams(Post $post): array {
		return ['post_uid' => $post->getUid()];
	}

	protected function getToggleLogLabel(bool $active): string {
		return $active ? 'Autosaged' : 'Took off autosage on';
	}

	public function initialize(): void {
		$this->registerToggleHooks();
	}

	protected function handleModuleRequest(): void {
		$this->handleToggleRequest();
	}
}
