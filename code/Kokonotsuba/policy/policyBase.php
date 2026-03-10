<?php

namespace Kokonotsuba\policy;

use Kokonotsuba\userRole;

class policyBase {
	public function __construct(
		protected array $authLevels,
		protected userRole $roleLevel,
		protected ?int $accountId,
	) {}
}