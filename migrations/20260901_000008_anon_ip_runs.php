<?php

use Kokonotsuba\migrations\migration;
use Kokonotsuba\migrations\tableBlueprint;
use Kokonotsuba\migrations\migrationContext;

/**
 * A record of every anonymization run.
 *
 * The anonymizer can now run on a schedule, which needs somewhere durable to answer "when did
 * this last go". A row is written when a run is dispatched and completed when the background
 * task finishes, so an unfinished row is either still running or a job that died - either way
 * the schedule counts it as having gone, and does not pile a second run on top of it.
 *
 * The insert is also the claim: a scheduled dispatch inserts only if no run is recent enough,
 * so two requests arriving together cannot both dispatch.
 */
return new class extends migration {
	public function description(): string {
		return 'Anonymization run ledger';
	}

	/** DDL, so MariaDB commits implicitly: not a rollback unit. */
	public function isTransactional(): bool {
		return false;
	}

	public function up(migrationContext $ctx): void {
		$ctx->schema->createTable('ANON_IP_RUN_TABLE', function (tableBlueprint $t): void {
			$t->column('id', 'BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT');
			// The run's scope: the number of days a record had to be older than, or NULL for
			// everything regardless of age.
			$t->column('older_than_days', 'INT(11) DEFAULT NULL');
			// 'manual' when staff pressed the button, 'scheduled' when the interval came due.
			$t->column('trigger_source', 'VARCHAR(16) NOT NULL');
			$t->column('dispatched_at', 'DATETIME NOT NULL');
			$t->column('finished_at', 'DATETIME DEFAULT NULL');
			$t->column('rows_changed', 'INT(11) DEFAULT NULL');
			// Per-target counts as JSON, for answering afterwards which tables it actually touched.
			$t->column('breakdown', 'TEXT DEFAULT NULL');
			$t->primary('id');
			$t->index('idx_anon_ip_runs_dispatched_at', ['dispatched_at']);
		});
	}

	public function down(migrationContext $ctx): void {
		$ctx->schema->dropTable('ANON_IP_RUN_TABLE');
	}

	public function detect(migrationContext $ctx): ?bool {
		return $ctx->schema->tableExists('ANON_IP_RUN_TABLE');
	}
};
