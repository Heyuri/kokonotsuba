<?php

use Kokonotsuba\config\configRepository;
use Kokonotsuba\config\configService;
use Kokonotsuba\migrations\migration;
use Kokonotsuba\migrations\migrationContext;
use Kokonotsuba\post\legacyPostConverter;

/**
 * Convert posts stored as HTML into the plain text the renderer formats at render time.
 *
 * Rows are converted in batches and flagged as they go, so an interrupted run resumes where it
 * stopped; that is also why there is no transaction. Two things do not survive exactly, both
 * visible beforehand with `php Utilities/post-text-format-cli.php preview`: a post made with the
 * rawHtml tool is reduced to text like any other legacy row (set its text_format to 2 by hand
 * afterwards), and tripcode/capcode markup kept inside very old name columns goes, leaving the
 * text. Rebuild the boards afterwards.
 */
return new class extends migration {
	public function description(): string {
		return 'Convert legacy HTML posts to plain text';
	}

	public function isTransactional(): bool {
		return false;
	}

	public function up(migrationContext $ctx): void {
		// A dry run skips the post_text_format DDL this reads through, so there is nothing to count yet.
		if ($ctx->isDryRun() && !$ctx->inspector->columnExists($ctx->table('POST_TABLE'), 'text_format')) {
			$ctx->note('post_text_format is not applied yet; the conversion runs once it is');
			return;
		}

		$converter = $this->converter($ctx);
		$legacy = $converter->countLegacy();

		if ($legacy === 0) {
			return;
		}

		$ctx->note(($ctx->isDryRun() ? 'would convert ' : 'converting ') . number_format($legacy) . ' post(s)');

		$result = $converter->convert($ctx->isDryRun(), null, null, 500, static function (int $done, int $target) use ($ctx): void {
			if ($done === $target || $done % 5000 === 0) {
				$ctx->note("  {$done} / {$target}");
			}
		});

		$ctx->note("converted {$result['converted']} post(s); {$result['rewritten']} had markup to unwind. Rebuild the boards.");
	}

	/** The HTML is gone once unwound; converted rows render the same, so there is nothing to undo. */
	public function down(migrationContext $ctx): void {}

	public function detect(migrationContext $ctx): ?bool {
		if (!$ctx->inspector->columnExists($ctx->table('POST_TABLE'), 'text_format')) {
			return false;
		}

		return $this->converter($ctx)->countLegacy() === 0;
	}

	private function converter(migrationContext $ctx): legacyPostConverter {
		$connection = $ctx->getConnection();

		return new legacyPostConverter(
			$connection,
			new configService(new configRepository($connection, $ctx->table('BOARD_CONFIG_TABLE'))),
			$ctx->table('POST_TABLE'),
			$ctx->table('BOARD_TABLE')
		);
	}
};
