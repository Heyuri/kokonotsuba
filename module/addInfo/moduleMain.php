<?php

namespace Kokonotsuba\Modules\addInfo;

use Kokonotsuba\ModuleClasses\abstractModuleMain;

class moduleMain extends abstractModuleMain {
	private array $dotPoints;

	public function initialize(): void {
		// Load configuration values
		$this->dotPoints = $this->getConfig('ModuleSettings.ADD_INFO', []);
		
		// Register the listener for the PostInfo hook
		$this->moduleContext->moduleEngine->addListener('PostInfo', function (string &$form) {
			$this->onRenderPostInfo($form);  // Call the method to modify the form
		});
	}

	public function getName(): string {
		return 'Additional Info';
	}

	public function getVersion(): string {
		return 'Kokonotsuba 2024';
	}

	private function onRenderPostInfo(string &$form): void {
		// Build additional information HTML
		$addinfoHTML = '';  
		$addinfoHTML .= '</ul><hr><ul class="rules">';
		
		// Add custom rules/points
		foreach ($this->dotPoints as $rule) {
			$addinfoHTML .= '<li>' . $rule . '</li>';
		}
		
		$addinfoHTML .= '</ul>';

		// Append the additional info to the form
		$form .= $addinfoHTML;
	}
	
}
