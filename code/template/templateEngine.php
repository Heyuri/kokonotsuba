<?php

/*
Kokonotsuba! Template engine for interacting with templates.
Derived from pixmicat's PTELibrary
*/

class templateEngine {
	private $tpl_block = [];
	private $tpl;
	private $config;
	private $boardData;

	public function __construct(string $tplname, array $dependencies) {
		$this->config = $dependencies['config'] ?? [];
		$this->boardData = $dependencies['boardData'] ?? [];

		// load the template
		$this->loadTemplate($tplname);
	}

	private function loadTemplate(string $tplname): void {
		// cache to store template file contents
		static $tplCache = [];

		// if its cached then load the ecache
		if (isset($tplCache[$tplname])) {
			$this->tpl = $tplCache[$tplname];
		} else {
			// die if the template file doesn't exist
			if(!file_exists($tplname)) {
				die("Template file ($tplname) doesn't exist for {$this->boardData['title']}");
			}

			$this->tpl = file_get_contents($tplname);
			$tplCache[$tplname] = $this->tpl;
		}
	}

	private function _readBlock(string $blockName) {
		if (!isset($this->tpl_block[$blockName])) {
			if (preg_match('/<!--&'.$blockName.'-->(.*)<!--\/&'.$blockName.'-->/smU', $this->tpl, $matches))
				$this->tpl_block[$blockName] = $matches[1];
			else
				$this->tpl_block[$blockName] = false;
		}
		return $this->tpl_block[$blockName];
	}

	public function setTemplateFile(string $templateName): void {
		// generate template path
		$templatePath = getBackendDir() . 'templates/' . $templateName;

		// clear the block cache
		$this->tpl_block = [];
		
		// load the specified template
		$this->loadTemplate($templatePath);
	}

	public function BlockValue($blockName) {
		return trim($this->_readBlock($blockName));
	}

	public function ParseBlock(string $blockName, array $ary_val) {
		static $defaultPlaceholders = [
			'{$LANGUAGE}'        => '',
			'{$OVERBOARD}'       => '',
			'{$STATIC_URL}'      => '',
			'{$REF_URL}'         => '',
			'{$LIVE_INDEX_FILE}'        => '',
			'{$STATIC_INDEX_FILE}'       => '',
			'{$PHP_EXT}'         => '',
			'{$TITLE}'           => '',
			'{$TITLESUB}'        => '',
			'{$HOME}'            => '',
			'{$TOP_LINKS}'       => '',
			'{$FOOTTEXT}'        => '',
			'{$BLOTTER}'         => '',
			'{$GLOBAL_MESSAGE}'  => '',
			'{$PAGE_TITLE}'      => '',
		];
	
		// Merge default placeholders with passed values, but only once for efficiency
		$ary_val = array_merge($defaultPlaceholders, [
			'{$LANGUAGE}'        => $this->config['PIXMICAT_LANGUAGE'] ?? '',
			'{$OVERBOARD}'       => !empty($this->config['ADMINBAR_OVERBOARD_BUTTON']) ? '[<a href="'.$this->config['LIVE_INDEX_FILE'].'?mode=overboard">Overboard</a>]' : ' ',
			'{$STATIC_URL}'      => $this->config['STATIC_URL'] ?? '',
			'{$REF_URL}'         => $this->config['REF_URL'] ?? '',
			'{$LIVE_INDEX_FILE}'        => $this->config['LIVE_INDEX_FILE'] ?? '',
			'{$STATIC_INDEX_FILE}'       => $this->config['STATIC_INDEX_FILE'] ?? '',
			'{$PHP_EXT}'         => $this->config['PHP_EXT'] ?? '',
			'{$TITLE}'           => $this->boardData['title'] ?? '',
			'{$TITLESUB}'        => $this->boardData['subtitle'] ?? '',
			'{$HOME}'            => $this->config['HOME'] ?? '',
			'{$TOP_LINKS}'       => $this->config['TOP_LINKS'] ?? '',
			'{$FOOTTEXT}'        => $this->config['FOOTTEXT'] ?? '',
			'{$BLOTTER}'         => '',
			'{$GLOBAL_MESSAGE}'  => '',
			'{$PAGE_TITLE}'      => strip_tags($this->boardData['title'] ?? ''),
			'{$INPUT_MAX}' => htmlspecialchars($this->config['INPUT_MAX'])
		], $ary_val);
	
		// Load template block
		$tmp_block = $this->_readBlock($blockName);
		if ($tmp_block === false) {
			return '';
		}
	
		// Escape {$ in template content to protect from premature replacement
		$tmp_block = str_replace('{$', '{' . chr(1) . '$', $tmp_block);
	
		// Process all logic blocks (combined regex for replacements can be optimized further)
		$tmp_block = $this->EvalFOREACH($tmp_block, $ary_val);
		$tmp_block = $this->EvalIF($tmp_block, $ary_val);
		$tmp_block = $this->EvalFile($tmp_block, $ary_val);
		$tmp_block = $this->EvalInclude($tmp_block, $ary_val);
	
		// Build the search/replace array in one go for efficiency
		$replacePairs = [];
		foreach ($ary_val as $key => $val) {
			if (is_scalar($val) || is_null($val)) {
				// Escape {$ for placeholders to prevent premature replacement
				$escapedKey = str_replace('{$', '{' . chr(1) . '$', $key);
				$replacePairs[$escapedKey] = strval($val);
			}
		}
	
		// Perform all replacements in one go with strtr or a single str_replace
		$tmp_block = strtr($tmp_block, $replacePairs);
	
		// Restore original placeholder syntax
		return str_replace('{'.chr(1).'$','{$', $tmp_block);
	}	

	private function EvalIF(string $tpl, array $ary) {
		$tmp_tpl = $tpl;
		if (preg_match_all('/<!--&IF\(([\$&].*),\'(.*)\',\'(.*)\'\)-->/smU', $tmp_tpl, $matches, PREG_SET_ORDER)) {
			foreach ($matches as $submatches) {
				$isblock = substr($submatches[1], 0, 1) == "&";
				$vari = substr($submatches[1], 1);
				$iftrue = $submatches[2];
				$iffalse = $submatches[3];
				$tmp_tpl = str_replace(
					$submatches[0],
					($isblock ? $this->BlockValue($vari) : ($ary['{$'.$vari.'}'] ?? false)) ? $this->EvalInclude($iftrue, $ary) : $this->EvalInclude($iffalse, $ary),
					$tmp_tpl
				);
			}
		}
		return $tmp_tpl;
	}

	private function EvalFOREACH(string $tpl, array $ary) {
		$tmp_tpl = $tpl;
		if (preg_match_all('/<!--&FOREACH\((\$.*),\'(.*)\'\)-->/smU', $tmp_tpl, $matches, PREG_SET_ORDER)) {
			foreach ($matches as $submatches) {
				$vari = $submatches[1];
				$block = $submatches[2];
				$foreach_tmp = '';
				if (isset($ary['{'.$vari.'}']) && is_array($ary['{'.$vari.'}']))
					foreach ($ary['{'.$vari.'}'] as $eachvar)
						$foreach_tmp .= $this->ParseBlock($block, $eachvar);
				$tmp_tpl = str_replace($submatches[0], $foreach_tmp, $tmp_tpl);
			}
		}
		return $tmp_tpl;
	}

	private function EvalFile(string $tpl, array $ary) {
		$tmp_tpl = $tpl;
		if (preg_match_all('/<!--&FILE\(\'(.*)\'\)-->/smU', $tmp_tpl, $matches, PREG_SET_ORDER)) {
			foreach ($matches as $submatches) {
				$buf = @file_get_contents($submatches[1]);
				$tmp_tpl = str_replace($submatches[0], ($buf ? $buf : ''), $tmp_tpl);
			}
		}
		return $tmp_tpl;
	}

	private function EvalInclude(string $tpl, array $ary) {
		$tmp_tpl = $tpl;
		if (preg_match_all('/<!--&(.*)\/-->/smU', $tmp_tpl, $matches, PREG_SET_ORDER)) {
			foreach ($matches as $submatches) {
				$tmp_tpl = str_replace($submatches[0], $this->ParseBlock($submatches[1], $ary), $tmp_tpl);
			}
		}
		return $tmp_tpl;
	}

}
