<?php

use Kokonotsuba\config\configRepository;
use Kokonotsuba\config\configService;
use Kokonotsuba\migrations\migration;
use Kokonotsuba\migrations\migrationContext;
use Kokonotsuba\post\legacyPostConverter;

/**
 * Report posts still stored as HTML.
 *
 * Rows written before the plain-text switch keep their markup and are flagged LEGACY_HTML, which
 * the renderer emits verbatim, so they read the same as they always did. Unwinding that markup
 * into plain text is lossy (rawHtml posts, old name-column tripcode markup) and cannot be undone,
 * so `up` no longer does it. It only counts the legacy rows and points at
 * `php Utilities/post-text-format-cli.php`, which previews and converts them on request.
 */
return new class extends migration {
	public function description(): string {
		return 'Count legacy HTML posts (conversion is opt-in through post-text-format-cli)';
	}

	public function isTransactional(): bool {
		return false;
	}

	public function up(migrationContext $ctx): void {
		if (!$ctx->inspector->columnExists($ctx->table('POST_TABLE'), 'text_format')) {
			$ctx->note('post_text_format is not applied yet; nothing to count');
			return;
		}

		$legacy = $this->converter($ctx)->countLegacy();

		if ($legacy === 0) {
			return;
		}

		$ctx->note(number_format($legacy) . ' post(s) are still stored as HTML and render verbatim. '
			. 'To convert them to plain text: php Utilities/post-text-format-cli.php preview, then convert.');
	}

	/** Nothing is written on the way up. */
	public function down(migrationContext $ctx): void {}

	/** Writes nothing, so an install of any age is already past it. */
	public function detect(migrationContext $ctx): ?bool {
		return true;
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
