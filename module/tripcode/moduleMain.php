<?php

namespace Kokonotsuba\Modules\tripcode;

use Kokonotsuba\ModuleClasses\abstractModuleMain;
use Kokonotsuba\Root\Constants\userRole;
use tripcodeProcessor;

require __DIR__ . '/tripcode_src/tripcodeProcessor.php';
require __DIR__ . '/tripcode_src/tripcodeRenderer.php';

class moduleMain extends abstractModuleMain {
	// handles rendering of tripcode/capcodes
	private tripcodeRenderer $tripcodeRenderer;
	
	// process tripcodes/capcodes
	private tripcodeProcessor $tripcodeProcessor;

	public function getName(): string {
		return 'Tripcode and capcode module';
	}

	public function getVersion(): string {
		return 'Koko 2025';
	}

	public function initialize(): void {
		
		// init tripcode renderer
		$this->tripcodeRenderer = new tripcodeRenderer(
			$this->moduleContext->userCapcodes,
			$this->getConfig('staffCapcodes')
		);

		// init tripcode processer
		$this->tripcodeProcessor = new tripcodeProcessor($this->moduleContext->config);

		// add hookpoint for tripcode rendering
		$this->moduleContext->moduleEngine->addListener('RenderTripcode', function (string &$nameHtml, string &$tripcode, string &$secure_tripcode, string &$capcode) {
			$this->onRenderTripcode($nameHtml, $tripcode, $secure_tripcode, $capcode);
		});

		// add hookpoint for tripcode generatoring 
		$this->moduleContext->moduleEngine->addListener('RegistBegin', function (array &$registInfo) {
			$this->onGenerateTripcode(
				$registInfo['name'], 
				$registInfo['tripcode_input'], 
				$registInfo['secure_tripcode_input'], 
				$registInfo['tripcode'], 
				$registInfo['secure_tripcode'], 
				$registInfo['capcode'], 
				$registInfo['roleLevel']
			);
		});
	}

	private function onRenderTripcode(
		string &$nameHtml, 
		string $tripcode, 
		string $secure_tripcode, 
		string $capcode
	): void {
		// process tripcode related html
		$nameHtml = $this->tripcodeRenderer->renderTripcode(
			$nameHtml, 
			$tripcode, 
			$secure_tripcode, 
			$capcode
		);
	}

	private function onGenerateTripcode(
		string &$name, 
		string &$tripcode_input, 
		string &$secure_tripcode_input, 
		string &$tripcode, 
		string &$secure_tripcode, 
		string &$capcode,
		userRole $roleLevel
	): void {
		// generate the tripcode/capcode
		$this->tripcodeProcessor->apply($name, $tripcode_input, $secure_tripcode_input, $capcode, $roleLevel);

		// then assign the *_input tripcode values to the tripcode variables that'll be used in the insert.
		// this is done so if the module is disabled - the plain trip password wont end up stored as plain text in `tripcode`/`secure_tripcode`
		// tripcode logic is a little too integral to base posting
		$tripcode = $tripcode_input;
		$secure_tripcode = $secure_tripcode_input;
	}
}