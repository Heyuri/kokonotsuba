<?php

namespace Koko\Tests\Unit\Modules;

use Koko\Tests\Framework\TestCase;
use Kokonotsuba\userRole;
use Kokonotsuba\Modules\privateMessage\messagePolicy;
use Kokonotsuba\Modules\privateMessage\messageService;

/**
 * Unit tests for the private-message permission policy.
 */
final class MessagePolicyTest extends TestCase {

	protected function setUp(): void {
		requireModuleFile('privateMessage/messageService.php');
		requireModuleFile('privateMessage/messagePolicy.php');
	}

	private function stubService(bool $hasConversation): messageService {
		return new class($hasConversation) extends messageService {
			public function __construct(private bool $has) {}
			public function hasConversationWith(string $userTripCode, string $contactTripCode): bool {
				return $this->has;
			}
		};
	}

	private function makePolicy(bool $hasConversation): messagePolicy {
		$policy = new messagePolicy([], userRole::LEV_USER, null);
		$policy->setMessageService($this->stubService($hasConversation));
		return $policy;
	}

	public function testCanSendMessageRequiresNonEmptyTripcode(): void {
		$policy = $this->makePolicy(false);
		$this->assertTrue($policy->canSendMessage('◆abcdef1234'));
		$this->assertFalse($policy->canSendMessage(''));
	}

	public function testCanViewContactDelegatesToService(): void {
		$this->assertTrue($this->makePolicy(true)->canViewContact('◆contact', '◆me'));
		$this->assertFalse($this->makePolicy(false)->canViewContact('◆contact', '◆me'));
	}
}
