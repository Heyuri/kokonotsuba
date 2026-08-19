<?php

namespace Koko\Tests\Framework;

/**
 * Loads install.php's functions and classes without running the installer.
 *
 * install.php is a front controller: requiring it defines ROOTPATH, reads
 * databaseSettings.php, opens a PDO connection and dispatches on $_REQUEST.
 * None of that is unit-testable, but the declarations it carries (the table
 * DDL, the config-template builder, the identifier validator) are.
 *
 * So the source is tokenized and only its top-level `use`, `function` and
 * `class` declarations are re-declared, inside a private namespace, with the
 * global classes it references imported. Everything else - the requires, the
 * define, the switch - is dropped.
 */
final class InstallerHarness {

	/** Namespace the extracted declarations are evaluated into. */
	public const NS = 'Koko\\Tests\\Installer';

	/** Load the declarations once per process. */
	public static function load(): void {
		if (function_exists(self::NS . '\\sanitizeTableName')) {
			return;
		}

		// install.php's helpers resolve their paths against ROOTPATH.
		if (!defined('ROOTPATH')) {
			define('ROOTPATH', KOKO_TEST_ROOT);
		}

		$source = file_get_contents(KOKO_TEST_ROOT . '/install.php');
		if ($source === false) {
			throw new \RuntimeException('Could not read install.php');
		}

		// Unqualified class names do not fall back to the global namespace, so the
		// engine classes install.php uses have to be imported explicitly.
		$preamble = 'namespace ' . self::NS . ";\n"
			. "use PDO; use PDOException; use Exception; use InvalidArgumentException;\n";

		eval($preamble . self::extractDeclarations($source));

		// createTables() validates its table names with array_map('sanitizeTableName', …).
		// A string callback always resolves in the global namespace, which is where the
		// function lives when install.php runs for real, so give it one here too.
		if (!function_exists('sanitizeTableName')) {
			eval('function sanitizeTableName($tableName) { return \\' . self::NS . '\\sanitizeTableName($tableName); }');
		}
	}

	/** Fully-qualified name of an extracted installer function. */
	public static function fn(string $name): string {
		self::load();
		return self::NS . '\\' . $name;
	}

	/** Fully-qualified name of an extracted installer class. */
	public static function cls(string $name): string {
		self::load();
		return self::NS . '\\' . $name;
	}

	/**
	 * The logical table-name map install.php passes to tableCreator::createTables().
	 *
	 * Built by the `install` branch of the switch, which the harness does not
	 * evaluate, so it is recovered from the source instead - keeping the list in
	 * one place (install.php) rather than duplicating it in the tests.
	 *
	 * @return string[] logical key => the $dbSettings key it reads
	 */
	public static function installerTableKeys(): array {
		$source = file_get_contents(KOKO_TEST_ROOT . '/install.php');
		if ($source === false) {
			throw new \RuntimeException('Could not read install.php');
		}
		preg_match_all("/'([A-Z_]+)'\s*=>\s*\\\$dbSettings\['([A-Z_]+)'\]/", $source, $matches, PREG_SET_ORDER);

		$keys = [];
		foreach ($matches as $match) {
			$keys[$match[1]] = $match[2];
		}
		return $keys;
	}

	/** Logical table keys that createTables() actually emits a CREATE for. */
	public static function createdTableKeys(): array {
		$source = file_get_contents(KOKO_TEST_ROOT . '/install.php');
		preg_match_all(
			'/CREATE TABLE IF NOT EXISTS \{\$sanitizedTableNames\[\'([A-Z_]+)\'\]\}/',
			(string)$source,
			$matches
		);
		return array_values(array_unique($matches[1]));
	}

	/**
	 * The regex getRootPath() uses to recover the backend path from a board's koko.php stub.
	 *
	 * getRootPath() itself resolves everything against install.php's own __DIR__, so it cannot be
	 * pointed at a fixture; the pattern it applies is lifted out of the source instead, so the
	 * tests exercise the shipped one rather than a copy of it.
	 */
	public static function rootPathPattern(): string {
		$source = file_get_contents(KOKO_TEST_ROOT . '/install.php');
		$tokens = \PhpToken::tokenize((string)$source);

		foreach ($tokens as $index => $token) {
			if (!$token->is(T_STRING) || $token->text !== 'preg_match') {
				continue;
			}
			// The first literal after preg_match( is the pattern.
			for ($i = $index; $i < count($tokens); $i++) {
				if ($tokens[$i]->is(T_CONSTANT_ENCAPSED_STRING)) {
					return eval('return ' . $tokens[$i]->text . ';');
				}
			}
		}

		throw new \RuntimeException('No preg_match pattern found in install.php');
	}

	/**
	 * Keep only the top-level use/function/class declarations of a PHP source file.
	 */
	private static function extractDeclarations(string $source): string {
		$tokens = \PhpToken::tokenize($source);
		$out = '';
		$depth = 0;
		$count = count($tokens);

		for ($i = 0; $i < $count; $i++) {
			$token = $tokens[$i];

			if ($token->text === '{') {
				$depth++;
				continue;
			}
			if ($token->text === '}') {
				$depth--;
				continue;
			}
			if ($depth !== 0) {
				continue;
			}

			// `use function Puchiko\createDirectory;` and friends: copy up to the ';'.
			if ($token->is(T_USE)) {
				$out .= self::consumeUntil($tokens, $i, ';');
				continue;
			}

			// A declaration: copy it whole, tracking braces from its own body.
			if ($token->is([T_FUNCTION, T_CLASS])) {
				$out .= self::consumeBlock($tokens, $i);
				continue;
			}
		}

		return $out;
	}

	/** Copy tokens from $i up to and including the first $stop character. */
	private static function consumeUntil(array $tokens, int &$i, string $stop): string {
		$text = '';
		for ($count = count($tokens); $i < $count; $i++) {
			$text .= $tokens[$i]->text;
			if ($tokens[$i]->text === $stop) {
				break;
			}
		}
		return $text . "\n";
	}

	/** Copy a function/class declaration from $i through its balanced body. */
	private static function consumeBlock(array $tokens, int &$i): string {
		$text = '';
		$depth = 0;
		$opened = false;

		for ($count = count($tokens); $i < $count; $i++) {
			$text .= $tokens[$i]->text;

			if ($tokens[$i]->text === '{') {
				$depth++;
				$opened = true;
			} elseif ($tokens[$i]->text === '}') {
				$depth--;
				if ($opened && $depth === 0) {
					break;
				}
			}
		}

		return $text . "\n";
	}
}
