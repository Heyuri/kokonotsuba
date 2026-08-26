<?php

namespace Kokonotsuba\install;

/** Renders the installer's pages. All output goes through here; nothing else echoes. */
final class installerPage {
	private const STATUS_MARK = [
		checkResult::OK => '&#10003;',
		checkResult::WARN => '!',
		checkResult::FAIL => '&#10007;',
	];

	public function __construct(
		private readonly string $selfUrl
	) {}

	public function header(string $subtitle = ''): void {
		echo '<!DOCTYPE html><html lang="en"><head>',
			'<meta charset="UTF-8">',
			'<meta name="viewport" content="width=device-width, initial-scale=1.0">',
			'<meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate">',
			'<meta name="robots" content="noarchive, noindex, nofollow">',
			'<title>Kokonotsuba installer</title>',
			$this->style(),
			'</head><body><div class="wrap">',
			'<h1>Kokonotsuba installer</h1>';

		if ($subtitle !== '') {
			echo '<p class="subtitle">', self::escape($subtitle), '</p>';
		}
	}

	public function footer(): void {
		echo '<hr><p class="footnote">',
			'Kokonotsuba is in active development. Problems go to the ',
			'<a href="https://github.com/Heyuri/kokonotsuba">repository</a>; setup notes are at ',
			'<a href="https://kokonotsuba.github.io/">kokonotsuba.github.io</a>.',
			'</p></div></body></html>';
	}

	/** The page shown once the marker file exists. */
	public function alreadyInstalled(string $appRoot): void {
		$this->header();
		echo '<div class="panel ok"><h2>Already installed</h2>',
			'<p>This instance has been set up. The installer will not run again.</p>',
			'<p>Delete it so nothing can probe it:</p>',
			$this->commandBlock(['rm '.escapeshellarg(rtrim($appRoot, '/').'/install.php')]),
			'</div>';
		$this->footer();
	}

	/** The preflight report: every check, in groups, with a summary of what still blocks the install. */
	public function report(checkReport $report): void {
		$failures = count($report->failures());
		$warnings = count($report->warnings());

		$summaryClass = $failures > 0 ? 'fail' : ($warnings > 0 ? 'warn' : 'ok');
		$summary = $failures > 0
			? $failures.' problem'.($failures === 1 ? '' : 's').' must be fixed before installing'
			: ($warnings > 0
				? 'Ready to install, with '.$warnings.' warning'.($warnings === 1 ? '' : 's')
				: 'Everything checks out');

		echo '<div class="panel ', $summaryClass, '"><h2>', self::escape($summary), '</h2>';

		foreach ($report->grouped() as $group => $results) {
			echo '<h3>', self::escape($group), '</h3><table class="checks">';

			foreach ($results as $result) {
				echo '<tr class="', self::escape($result->status), '">',
					'<td class="mark">', self::STATUS_MARK[$result->status] ?? '', '</td>',
					'<td class="name">', self::escape($result->label), '</td>',
					'<td class="detail">', self::escape($result->detail);

				if ($result->fix !== null && $result->fix !== '') {
					echo '<div class="fix"><span>Run:</span><code>', self::escape($result->fix), '</code></div>';
				}

				echo '</td></tr>';
			}

			echo '</table>';
		}

		echo '</div>';

		if ($failures > 0) {
			echo '<div class="panel fail"><h3>Fix these, then reload this page</h3>',
				$this->commandBlock($report->fixCommands()),
				'</div>';
		}
	}

	/** nginx does not read .htaccess, so its rules are printed for pasting into the server block. */
	public function webServerHelp(string $urlPrefix): void {
		echo '<details class="panel"><summary>Web server rules (nginx)</summary>',
			'<p>Apache is covered by the <code>.htaccess</code> files in the tree. On nginx, paste this ',
			'into the <code>server</code> block and reload, then reload this page to re-run the exposure check:</p>',
			'<pre class="command">', self::escape(webServerRules::nginxSnippet($urlPrefix)), '</pre>',
			'</details>';
	}

	/**
	 * The install form.
	 *
	 * @param array<string, string> $values Field values to put back into the inputs.
	 * @param array<string, string> $errors field => message.
	 */
	public function form(array $values, array $errors, bool $blocked): void {
		echo '<form method="POST" action="', self::escape($this->selfUrl), '" autocomplete="off">',
			'<input type="hidden" name="action" value="install">';

		echo '<div class="panel"><h2>Database</h2>',
			'<p>Create the database and user first; the installer only connects to them.</p>',
			'<pre class="command">', self::escape(
				"sudo mariadb -e \"CREATE DATABASE kokonotsuba CHARACTER SET utf8mb4;\n"
					."CREATE USER 'koko_user'@'localhost' IDENTIFIED BY 'your_password';\n"
					."GRANT ALL PRIVILEGES ON kokonotsuba.* TO 'koko_user'@'localhost';\n"
					.'FLUSH PRIVILEGES;"'
			), '</pre>',
			'<table class="fields">';

		$this->field($values, $errors, 'db_host', 'Host', 'text', 'localhost or 127.0.0.1');
		$this->field($values, $errors, 'db_port', 'Port', 'text', '3306');
		$this->field($values, $errors, 'db_name', 'Database name', 'text', 'kokonotsuba');
		$this->field($values, $errors, 'db_user', 'Database user', 'text', 'koko_user');
		$this->field($values, $errors, 'db_password', 'Database password', 'password', '');

		echo '</table></div>';

		echo '<div class="panel"><h2>Admin account</h2>',
			'<p>The first staff account. It can do anything, including creating more boards.</p>',
			'<table class="fields">';

		$this->field($values, $errors, 'admin_username', 'Username', 'text', '');
		$this->field($values, $errors, 'admin_password', 'Password', 'password', 'at least 8 characters');
		$this->field($values, $errors, 'admin_password_confirm', 'Password again', 'password', '');

		echo '</table></div>';

		echo '<div class="panel"><h2>First board</h2>',
			'<p>Created under <code>boards/</code> inside this directory. More can be added from the admin panel later.</p>',
			'<table class="fields">';

		$this->field($values, $errors, 'board_identifier', 'Identifier', 'text', 'b');
		$this->field($values, $errors, 'board_title', 'Title', 'text', 'board@example.net');
		$this->field($values, $errors, 'board_sub_title', 'Sub-title', 'text', 'an example board');

		echo '</table></div>';

		echo '<div class="panel"><h2>URLs</h2>',
			'<p>Detected from the address you are reading this on. Change them if the site is served from somewhere else.</p>',
			'<table class="fields">';

		$this->field($values, $errors, 'website_url', 'Board base URL', 'text', 'https://example.net/kokonotsuba/boards/');
		$this->field($values, $errors, 'home_url', 'Home link', 'text', 'https://example.net/');
		$this->field($values, $errors, 'static_url', 'Static URL', 'text', 'https://example.net/kokonotsuba/static/');
		$this->field($values, $errors, 'static_path', 'Static path on disk', 'text', '/var/www/html/kokonotsuba/static/');

		echo '</table></div>';

		echo '<div class="panel actions">';

		if ($blocked) {
			echo '<p class="blocked">Installing is blocked until the failed checks above are cleared.</p>';
		}

		echo '<button type="submit"', $blocked ? ' disabled' : '', '>Install</button>',
			'</div></form>';
	}

	/**
	 * @param array<string, string> $values
	 * @param array<string, string> $errors
	 */
	private function field(array $values, array $errors, string $name, string $label, string $type, string $placeholder): void {
		$error = $errors[$name] ?? '';
		$value = $type === 'password' ? '' : ($values[$name] ?? '');

		echo '<tr class="', $error !== '' ? 'has-error' : '', '">',
			'<td class="postblock"><label for="', self::escape($name), '">', self::escape($label), '</label></td>',
			'<td><input id="', self::escape($name), '" name="', self::escape($name), '"',
			' type="', self::escape($type), '"',
			' value="', self::escape($value), '"',
			$placeholder !== '' ? ' placeholder="'.self::escape($placeholder).'"' : '',
			'>';

		if ($error !== '') {
			echo '<div class="error">', self::escape($error), '</div>';
		}

		echo '</td></tr>';
	}

	/** The step-by-step outcome of an install attempt. */
	public function result(installResult $result): void {
		$succeeded = $result->succeeded();

		echo '<div class="panel ', $succeeded ? 'ok' : 'fail', '">',
			'<h2>', $succeeded ? 'Installed' : 'Install failed', '</h2>',
			'<table class="checks">';

		foreach ($result->steps() as $step) {
			echo '<tr class="', self::escape($step->status), '">',
				'<td class="mark">', self::STATUS_MARK[$step->status] ?? '', '</td>',
				'<td class="name">', self::escape($step->label), '</td>',
				'<td class="detail">', self::escape($step->detail);

			if ($step->fix !== null && $step->fix !== '') {
				echo '<div class="fix"><span>Run:</span><code>', self::escape($step->fix), '</code></div>';
			}

			echo '</td></tr>';
		}

		echo '</table>';

		if (!$succeeded) {
			echo '<p>Nothing was left half-written: the database changes were rolled back and any config files ',
				'the installer replaced were put back. Fix what is above and submit the form again.</p>',
				'</div>';

			return;
		}

		echo '</div><div class="panel ok"><h2>Finish up</h2><ol>',
			'<li>Delete the installer and tighten permissions:', $this->commandBlock($result->followUpCommands()), '</li>',
			'<li>Your board is at <a href="', self::escape($result->boardUrl()), '">',
			self::escape($result->boardUrl()), '</a>. Opening it renders its index page for the first time.</li>',
			'<li>Log in through <code>?mode=admin</code> on the board with the account you just made.</li>',
			'</ol></div>';
	}

	/** @param list<string> $commands */
	private function commandBlock(array $commands): string {
		if ($commands === []) {
			return '';
		}

		return '<pre class="command">'.self::escape(implode("\n", $commands)).'</pre>';
	}

	private static function escape(string $value): string {
		return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}

	private function style(): string {
		return '<style>
			body { background:#ffffee; color:#800000; font-family:sans-serif; font-size:15px; margin:0; padding:16px; }
			.wrap { max-width:900px; margin:0 auto; }
			h1 { font-size:24px; margin:8px 0; }
			h2 { font-size:18px; margin:0 0 8px; }
			h3 { font-size:15px; margin:14px 0 4px; }
			a { color:#0000ee; }
			.subtitle { margin-top:0; }
			.panel { border:1px solid #d9bfb7; background:#f0e0d6; padding:12px; margin:12px 0; border-radius:3px; }
			.panel.ok { border-color:#4c7c2f; }
			.panel.warn { border-color:#b8860b; }
			.panel.fail { border-color:#a00000; }
			.postblock { border:1px solid #800043; background:#eeaa88; padding:3px 6px; white-space:nowrap; }
			table { border-collapse:collapse; width:100%; }
			table.checks td { padding:3px 6px; vertical-align:top; border-bottom:1px solid #e4d0c6; }
			table.checks td.mark { width:1.4em; font-weight:bold; text-align:center; }
			table.checks tr.ok td.mark { color:#2f6f1f; }
			table.checks tr.warn td.mark { color:#8a6100; }
			table.checks tr.fail td.mark { color:#a00000; }
			table.checks td.name { width:16em; font-weight:bold; }
			table.fields td { padding:4px 6px; vertical-align:top; }
			table.fields input { width:100%; box-sizing:border-box; padding:4px; }
			tr.has-error input { border:1px solid #a00000; }
			.error { color:#a00000; font-size:13px; padding-top:2px; }
			.fix { margin-top:4px; }
			.fix span { font-size:12px; text-transform:uppercase; letter-spacing:.05em; }
			code, pre.command { background:#2f2320; color:#f5e9e4; font-family:monospace; }
			code { padding:1px 4px; word-break:break-all; }
			pre.command { padding:10px; overflow-x:auto; white-space:pre-wrap; word-break:break-word; border-radius:3px; }
			.actions { text-align:center; }
			button { font-size:17px; padding:8px 28px; cursor:pointer; }
			button[disabled] { cursor:not-allowed; opacity:.5; }
			.blocked { color:#a00000; }
			.footnote { font-size:13px; }
			summary { cursor:pointer; font-weight:bold; }
		</style>';
	}
}
