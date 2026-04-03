<?php

namespace Kokonotsuba\Modules\tripcode;

use Kokonotsuba\module_classes\abstractModuleMain;
use Kokonotsuba\module_classes\listeners\RenderTripcodeListenerTrait;
use Kokonotsuba\module_classes\listeners\RegistBeginListenerTrait;
use Kokonotsuba\userRole;

require __DIR__ . '/tripcode_src/tripcodeProcessor.php';
require __DIR__ . '/tripcode_src/tripcodeRenderer.php';

class moduleMain extends abstractModuleMain {
	use RenderTripcodeListenerTrait;
	use RegistBeginListenerTrait;

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
		$this->listenRenderTripcode('onRenderTripcode');

		// add hookpoint for tripcode generatoring 
		$this->listenRegistBegin('onGenerateTripcode');
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

	private function onGenerateTripcode(array &$registInfo): void {
		// generate the tripcode/capcode
		$this->tripcodeProcessor->apply(
			$registInfo['name'], 
			$registInfo['tripcode_input'], 
			$registInfo['secure_tripcode_input'], 
			$registInfo['capcode'], 
			$registInfo['roleLevel']
		);

		// then assign the *_input tripcode values to the tripcode variables that'll be used in the insert.
		// this is done so if the module is disabled - the plain trip password wont end up stored as plain text in `tripcode`/`secure_tripcode`
		// tripcode logic is a little too integral to base posting
		$registInfo['tripcode'] = $registInfo['tripcode_input'];
		$registInfo['secure_tripcode'] = $registInfo['secure_tripcode_input'];
	}
}