<?php

use Kokonotsuba\migrations\migration;
use Kokonotsuba\migrations\migrationContext;
use Kokonotsuba\migrations\tableBlueprint;

/**
 * Failed staff login ledger, backing the brute-force throttle.
 *
 * Attempt counting used to live in $_SESSION, which an attacker sidesteps simply by discarding
 * the cookie. Rows here survive that, and outlive the session long enough to warn the account
 * holder about attempts made against them while they were away.
 */
return new class extends migration {
	public function description(): string {
		return 'Failed login attempt ledger';
	}

	/** DDL, so MariaDB commits implicitly: not a rollback unit. */
	public function isTransactional(): bool {
		return false;
	}

	public function up(migrationContext $ctx): void {
		$ctx->schema->createTable('LOGIN_ATTEMPT_TABLE', function (tableBlueprint $t): void {
			$t->column('id', 'BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT');
			$t->column('username', 'VARCHAR(255) NOT NULL');
			$t->column('username_key', 'VARCHAR(255) NOT NULL');
			$t->column('account_id', 'INT(11) DEFAULT NULL');
			$t->column('ip', 'VARCHAR(45) NOT NULL');
			$t->column('user_agent', 'VARCHAR(255) DEFAULT NULL');
			$t->column('attempted_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP()');
			$t->column('counted', 'TINYINT(1) NOT NULL DEFAULT 1');
			$t->column('notified', 'TINYINT(1) NOT NULL DEFAULT 0');
			$t->primary('id');
			$t->index('idx_login_attempts_username', ['username_key', 'counted', 'attempted_at']);
			$t->index('idx_login_attempts_ip', ['ip', 'counted', 'attempted_at']);
			$t->index('idx_login_attempts_pending', ['account_id', 'notified']);
			$t->index('idx_login_attempts_attempted_at', ['attempted_at']);
			$t->foreign('fk_login_attempts_account_id', 'account_id', 'ACCOUNT_TABLE', 'id', 'CASCADE');
		});
	}

	public function down(migrationContext $ctx): void {
		$ctx->schema->dropTable('LOGIN_ATTEMPT_TABLE');
	}

	public function detect(migrationContext $ctx): ?bool {
		return $ctx->schema->tableExists('LOGIN_ATTEMPT_TABLE');
	}
};
