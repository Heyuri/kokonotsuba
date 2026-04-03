<?php

namespace Kokonotsuba\Modules\wordFilter;

use Kokonotsuba\module_classes\abstractModuleMain;
use Kokonotsuba\module_classes\listeners\RegistBeforeCommitListenerTrait;

class moduleMain extends abstractModuleMain {
	use RegistBeforeCommitListenerTrait;
	private array $FILTERS;

	public function initialize(): void {
		$this->FILTERS = $this->getConfig('ModuleSettings.FILTERS');
		
		$this->listenRegistBeforeCommit('onBeforeCommit');
	}
		 
	public function getName(): string {
		return 'K! Word Filter';
	}
		 
	public function getVersion(): string {
		return 'Koko BBS Release 1';
	}

	public function onBeforeCommit($name, &$email, &$emailForInsertion, &$sub, &$com): void {
		//VAGINA filter
		$this->FILTERS['~<a\b[^>]*>.*?</a>(*SKIP)(*F)|vagina~i'] = $this->generateColorSpan('VAGINA');
		 
		//PENIS filter
		$this->FILTERS['~<a\b[^>]*>.*?</a>(*SKIP)(*F)|penis~i'] = $this->generateColorSpan('PENIS');
		 
		//ANUS filter
		$this->FILTERS['~<a\b[^>]*>.*?</a>(*SKIP)(*F)|anus~i'] = $this->generateColorSpan('ANUS');
		 
		// Apply each filter in $FILTERS array to user's comment
		foreach ($this->FILTERS as $filterin => $filterout) {
			// apply filter
			$com = preg_replace($filterin, $filterout, $com);
		}
	}

	private function generateColorSpan(string $textContent): string {
		// generate random color values
		$red5 = mt_rand(0, 255);
		$green5 = mt_rand(0, 255);
		$blue5 = mt_rand(0, 255);
		$red6 = mt_rand(0, 255);
		$green6 = mt_rand(0, 255);
		$blue6 = mt_rand(0, 255);
				
		// style for span
		$bgstyle = sprintf('background-color: rgb(%d, %d, %d);', $red5, $green5, $blue5);
		$fontstyle = sprintf('color: rgb(%d, %d, %d);', $red6, $green6, $blue6);

		// construct span
		$span = '<span style="' . $bgstyle . ' ' . $fontstyle . '">' . htmlspecialchars($textContent) . '</span>';

		// return the span html
		return $span;
	}
}
