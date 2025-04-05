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
	private $functionCalls;

	public function __construct(string $tplname, array $dependencies, array $functionCalls = array()) {
		$this->config = $dependencies['config'] ?? [];
		$this->boardData = $dependencies['boardData'] ?? [];
		$this->functionCalls = $functionCalls;

		static $tplCache = [];

		if (isset($tplCache[$tplname])) {
			$this->tpl = $tplCache[$tplname];
		} else {
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

	public function BlockValue($blockName) {
		return trim($this->_readBlock($blockName));
	}

	public function ParseBlock(string $blockName, array $ary_val) {
		$ary_val = array_merge([
			'{$LANGUAGE}'		=> $this->config['PIXMICAT_LANGUAGE'] ?? '',
			'{$OVERBOARD}'		=> !empty($this->config['ADMINBAR_OVERBOARD_BUTTON']) ? '[<a href="'.$this->config['PHP_SELF'].'?mode=overboard">Overboard</a>]' : ' ',
			'{$STATIC_URL}'		=> $this->config['STATIC_URL'] ?? '',
			'{$REF_URL}'		=> $this->config['REF_URL'] ?? '',
			'{$PHP_SELF}'		=> $this->config['PHP_SELF'] ?? '',
			'{$PHP_SELF2}'		=> $this->config['PHP_SELF2'] ?? '',
			'{$PHP_EXT}'		=> $this->config['PHP_EXT'] ?? '',
			'{$TITLE}'			=> $this->boardData['title'] ?? '',
			'{$TITLESUB}'		=> $this->boardData['subtitle'] ?? '',
			'{$HOME}'			=> $this->config['HOME'] ?? '',
			'{$TOP_LINKS}'		=> $this->config['TOP_LINKS'] ?? '',
			'{$FOOTTEXT}'		=> $this->config['FOOTTEXT'] ?? '',
			'{$BLOTTER}'		=> '',
			'{$GLOBAL_MESSAGE}'	=> '',
			'{$PAGE_TITLE}'		=> strip_tags($this->boardData['title'] ?? ''),
		], $ary_val);

		$this->runInjectedFunctions($ary_val);

		if (($tmp_block = $this->_readBlock($blockName)) === false) return "";

		foreach ($ary_val as $akey => $aval)
			$ary_val[$akey] = str_replace('{$', '{'.chr(1).'$', $ary_val[$akey]);

		$tmp_block = $this->EvalFOREACH($tmp_block, $ary_val);
		$tmp_block = $this->EvalIF($tmp_block, $ary_val);
		$tmp_block = $this->EvalFile($tmp_block, $ary_val);
		$tmp_block = $this->EvalInclude($tmp_block, $ary_val);

		return str_replace('{'.chr(1).'$','{$', str_replace(array_keys($ary_val), array_values($ary_val), $tmp_block));
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

	private function runInjectedFunctions(&$ary_val) {
		if(!isset($this->functionCalls)) return;

		foreach($this->functionCalls as $call) {
			$call['callback']($ary_val);
		}
	}

	public function setFunctionCallbacks(array $callbacks) {
		$this->functionCalls = $callbacks;
	}
}
