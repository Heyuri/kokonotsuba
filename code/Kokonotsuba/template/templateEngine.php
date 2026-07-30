<?php

/*
Kokonotsuba! Template engine for interacting with templates.
Derived from pixmicat's PTELibrary

Templates are directory-based: each block is a separate .tpl file.
Block resolution order: primary dir → additional paths → global fallback.
Within a directory, root files take precedence over subdirectory files.

Blocks are compiled once and rendered many times. A rebuild renders the same handful of blocks for
every post on the page, so locating directives and splitting out placeholders happens on first use,
and each render is a walk over the resulting node list.
*/

namespace Kokonotsuba\template;

use function Puchiko\strings\sanitizeStr;

class templateEngine {
	private array $tpl_block = [];
	private string $templateDir;
	private string $globalDir;
	private array $additionalPaths = [];
	private ?array $dirBlockMap = null;
	private array $config;
	private array $boardData;

	private static array $fileCache = [];

	/** Node kinds produced by the compiler. Literal text is stored as a bare string. */
	private const N_PLACEHOLDER = 0;
	private const N_FOREACH     = 1;
	private const N_IF          = 2;
	private const N_FILE        = 3;
	private const N_INCLUDE     = 4;

	/**
	 * Stands in for a directive while the surrounding text is still being parsed. Templates are
	 * HTML, so a pair of C0 control characters cannot collide with anything real inside them.
	 */
	private const MARK_OPEN  = "\x02";
	private const MARK_CLOSE = "\x03";

	/** Compiled node lists, keyed by block name. */
	private array $compiledCache = [];

	/** The site-wide placeholders, which depend only on config and board data. */
	private ?array $baseReplacements = null;

	public function __construct(string $templateDir, array $dependencies) {
		$this->config = $dependencies['config'] ?? [];
		$this->boardData = $dependencies['boardData'] ?? [];
		$this->templateDir = $templateDir;
		$this->globalDir = getBackendDir() . 'templates/global';
	}

	public function addSearchPath(string $path): void {
		if (!in_array($path, $this->additionalPaths, true)) {
			$this->additionalPaths[] = $path;
			$this->dirBlockMap = null;
		}
	}

	private function buildBlockMap(): array {
		$map = [];

		// Index global directory (lowest priority)
		$this->indexDirectory($this->globalDir, $map);

		// Index additional paths (middle priority, first added = lowest)
		foreach ($this->additionalPaths as $path) {
			$this->indexDirectory($path, $map);
		}

		// Index primary directory (highest priority)
		$this->indexDirectory($this->templateDir, $map);

		return $map;
	}

	private function indexDirectory(string $dir, array &$map): void {
		if (!is_dir($dir)) return;

		// Collect from subdirectories first (lower priority within this dir)
		foreach (glob($dir . '/*', GLOB_ONLYDIR) as $subdir) {
			foreach (glob($subdir . '/*.tpl') as $file) {
				$blockName = basename($file, '.tpl');
				$map[$blockName] = $file;
			}
		}

		// Root files override subdirectory files (higher priority within this dir)
		foreach (glob($dir . '/*.tpl') as $file) {
			$blockName = basename($file, '.tpl');
			$map[$blockName] = $file;
		}
	}

	private function getBlockMap(): array {
		if ($this->dirBlockMap === null) {
			$this->dirBlockMap = $this->buildBlockMap();
		}
		return $this->dirBlockMap;
	}

	private function readBlockFile(string $path): string {
		if (!isset(self::$fileCache[$path])) {
			self::$fileCache[$path] = file_get_contents($path);
		}
		return self::$fileCache[$path];
	}

	private function _readBlock(string $blockName) {
		if (!isset($this->tpl_block[$blockName])) {
			$map = $this->getBlockMap();
			if (isset($map[$blockName])) {
				$this->tpl_block[$blockName] = $this->readBlockFile($map[$blockName]);
			} else {
				$this->tpl_block[$blockName] = false;
			}
		}
		return $this->tpl_block[$blockName];
	}

	public function setTemplateFile(string $templateName): void {
		// clear the block cache
		$this->tpl_block = [];
		$this->compiledCache = [];

		// reset the directory block map
		$this->dirBlockMap = null;

		// set the new template directory
		$this->templateDir = getBackendDir() . 'templates/' . $templateName . '/';
	}

	public function BlockValue(string $blockName) {
		return trim($this->_readBlock($blockName));
	}

	/** Compile a named block into a node list, or false when the block does not exist. */
	private function compileBlock(string $blockName): array|false {
		if (!array_key_exists($blockName, $this->compiledCache)) {
			$raw = $this->_readBlock($blockName);
			$this->compiledCache[$blockName] = $raw === false ? false : $this->compileText($raw);
		}

		return $this->compiledCache[$blockName];
	}

	/**
	 * Compile a run of template text into a node list.
	 *
	 * Directives are found with the same four patterns, applied in the same order, that this engine
	 * once ran on every single render: FOREACH, then IF, then FILE, then INCLUDE. Each match is
	 * replaced by a marker before the next pattern runs, which is the protection the sequential text
	 * rewrites used to give - a later pattern cannot match inside an earlier one's arguments. What
	 * changed is only how often this happens: once per block, rather than once per block per post.
	 */
	private function compileText(string $text): array {
		$directives = [];
		return $this->splitToNodes($this->markDirectives($text, $directives), $directives);
	}

	/**
	 * Replace every directive with a marker, recording its node in $directives (by reference, so
	 * the closures below and any nested branch share one table).
	 */
	private function markDirectives(string $text, array &$directives): string {
		$text = preg_replace_callback('/<!--&FOREACH\((\$.*),\'(.*)\'\)-->/smU',
			function (array $m) use (&$directives): string {
				return $this->mark($directives, [self::N_FOREACH, '{' . $m[1] . '}', $m[2]]);
			}, $text);

		$text = preg_replace_callback('/<!--&IF\(([\$&].*),\'(.*)\',\'(.*)\'\)-->/smU',
			function (array $m) use (&$directives): string {
				// A leading & tests a block's own contents rather than a value passed in.
				//
				// Each branch is compiled here, against this same directive table. A FOREACH written
				// into a branch was turned into a marker by the pass above (the historic engine
				// expanded such a FOREACH before it parsed the IF around it); resolving the branch
				// against the shared table restores that node, and any FILE or INCLUDE the branch
				// carries of its own is marked by markBranch(). The branch is only walked when the
				// condition selects it, so the other branch's directives never run - which is what
				// the old engine did too, having discarded the untaken branch.
				$trueBranch  = $this->splitToNodes($this->markBranch($m[2], $directives), $directives);
				$falseBranch = $this->splitToNodes($this->markBranch($m[3], $directives), $directives);

				return $this->mark($directives, [self::N_IF, $m[1][0] === '&', substr($m[1], 1), $trueBranch, $falseBranch]);
			}, $text);

		$text = preg_replace_callback('/<!--&FILE\(\'(.*)\'\)-->/smU',
			function (array $m) use (&$directives): string {
				return $this->mark($directives, [self::N_FILE, $m[1]]);
			}, $text);

		$text = preg_replace_callback('/<!--&(.*)\/-->/smU',
			function (array $m) use (&$directives): string {
				return $this->mark($directives, [self::N_INCLUDE, $m[1]]);
			}, $text);

		return $text;
	}

	/**
	 * Mark the directives that can appear inside an IF branch. FOREACH and IF were already lifted
	 * out by the passes in markDirectives() (a branch holds their markers by the time it reaches
	 * here); what remains to handle is the branch's own FILE and INCLUDE directives.
	 */
	private function markBranch(string $text, array &$directives): string {
		$text = preg_replace_callback('/<!--&FILE\(\'(.*)\'\)-->/smU',
			function (array $m) use (&$directives): string {
				return $this->mark($directives, [self::N_FILE, $m[1]]);
			}, $text);

		$text = preg_replace_callback('/<!--&(.*)\/-->/smU',
			function (array $m) use (&$directives): string {
				return $this->mark($directives, [self::N_INCLUDE, $m[1]]);
			}, $text);

		return $text;
	}

	/** Record a directive and return the marker standing in for it. */
	private function mark(array &$directives, array $node): string {
		$directives[] = $node;
		return self::MARK_OPEN . (count($directives) - 1) . self::MARK_CLOSE;
	}

	/**
	 * Split marked text into literal strings, placeholder nodes and the recorded directives.
	 *
	 * The markers keep their delimiters through the split rather than being reduced to a bare
	 * index, because template text of its own can perfectly well be a run of digits - "{$W}0{$H}"
	 * would otherwise read its literal 0 as a reference to directive zero and render that directive.
	 */
	private function splitToNodes(string $text, array $directives): array {
		if ($text === '') {
			return [];
		}

		$nodes = [];
		$pattern = '/(' . self::MARK_OPEN . '\d+' . self::MARK_CLOSE . ')|(\{\$[^}]*\})/s';
		$pieces = preg_split($pattern, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

		foreach ($pieces as $piece) {
			if ($piece[0] === self::MARK_OPEN) {
				$nodes[] = $directives[(int)substr($piece, 1, -1)];
			} elseif (isset($piece[1]) && $piece[0] === '{' && $piece[1] === '$' && str_ends_with($piece, '}')) {
				$nodes[] = [self::N_PLACEHOLDER, $piece];
			} else {
				$nodes[] = $piece;
			}
		}

		return $nodes;
	}

	/**
	 * Placeholders that are the same for every block on the page.
	 *
	 * These read only config and board data, neither of which changes once the engine is built, so
	 * the sanitising and tag-stripping below used to run once per rendered post for no reason.
	 */
	private function getBaseReplacements(): array {
		if ($this->baseReplacements === null) {
			$this->baseReplacements = [
				'{$LANGUAGE}'          => $this->config['PIXMICAT_LANGUAGE'] ?? '',
				'{$OVERBOARD}'         => !empty($this->config['ADMINBAR_OVERBOARD_BUTTON']) ? '[<a href="'.$this->config['LIVE_INDEX_FILE'].'?mode=overboard">Overboard</a>]' : ' ',
				'{$CONTACT}'           => !empty($this->config['CONTACT_URL']) ? '[<a href="' . sanitizeStr($this->config['CONTACT_URL']) . '">Contact</a>]' : '',
				'{$STATIC_URL}'        => $this->config['STATIC_URL'] ?? '',
				'{$REF_URL}'           => $this->config['REF_URL'] ?? '',
				'{$LIVE_INDEX_FILE}'   => $this->config['LIVE_INDEX_FILE'] ?? '',
				'{$STATIC_INDEX_FILE}' => $this->config['STATIC_INDEX_FILE'] ?? '',
				'{$PHP_EXT}'           => $this->config['PHP_EXT'] ?? '',
				'{$TITLE}'             => $this->boardData['title'] ?? '',
				'{$TITLESUB}'          => $this->boardData['subtitle'] ?? '',
				'{$HOME}'              => $this->config['HOME'] ?? '',
				'{$TOP_LINKS}'         => $this->config['TOP_LINKS'] ?? '',
				'{$FOOTTEXT}'          => $this->config['FOOTTEXT'] ?? '',
				'{$BLOTTER}'           => '',
				'{$GLOBAL_MESSAGE}'    => '',
				'{$PAGE_TITLE}'        => strip_tags($this->boardData['title'] ?? ''),
				'{$INPUT_MAX}'         => htmlspecialchars($this->config['INPUT_MAX']),
			];
		}

		return $this->baseReplacements;
	}

	public function ParseBlock(string $blockName, array $ary_val) {
		$nodes = $this->compileBlock($blockName);
		if ($nodes === false) {
			return '';
		}

		return $this->renderNodes($nodes, array_merge($this->getBaseReplacements(), $ary_val));
	}

	/**
	 * Walk a compiled node list, appending each piece's text.
	 *
	 * Only the template's own placeholders are substituted; whatever a directive produces is
	 * appended as it stands. That is deliberate, and is what the old escape-substitute-unescape
	 * dance achieved: a value carrying a {$...} sequence must not go on to be substituted itself.
	 */
	private function renderNodes(array $nodes, array $ary_val): string {
		$out = [];

		foreach ($nodes as $node) {
			if (is_string($node)) {
				$out[] = $node;
				continue;
			}

			switch ($node[0]) {
				case self::N_PLACEHOLDER:
					$key = $node[1];
					if (array_key_exists($key, $ary_val)) {
						$val = $ary_val[$key];
						// A non-scalar has no textual form, so the placeholder is left standing
						// rather than replaced with something meaningless.
						$out[] = (is_scalar($val) || $val === null) ? strval($val) : $key;
					} else {
						$out[] = $key;
					}
					break;

				case self::N_FOREACH:
					$key = $node[1];
					if (isset($ary_val[$key]) && is_array($ary_val[$key])) {
						foreach ($ary_val[$key] as $eachvar) {
							$out[] = $this->ParseBlock($node[2], $eachvar);
						}
					}
					break;

				case self::N_IF:
					$truthy = $node[1]
						? $this->BlockValue($node[2])
						: ($ary_val['{$' . $node[2] . '}'] ?? false);
					$out[] = $this->renderNodes($truthy ? $node[3] : $node[4], $ary_val);
					break;

				case self::N_FILE:
					$buf = @file_get_contents($node[1]);
					$out[] = $buf === false ? '' : $buf;
					break;

				case self::N_INCLUDE:
					$out[] = $this->ParseBlock($node[1], $ary_val);
					break;
			}
		}

		return implode('', $out);
	}

}
