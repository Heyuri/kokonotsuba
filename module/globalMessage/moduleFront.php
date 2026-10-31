<?php

namespace Kokonotsuba\Modules\globalMessage;

include_once __DIR__ . '/globalMessageLibrary.php';

use Kokonotsuba\module_classes\abstractModuleFront;

/** The global message on the front page, when SITE.SHOW_GLOBAL_MESSAGE is on. */
class moduleFront extends abstractModuleFront {
	public function initialize(): void {
		if (!$this->getConfig('SITE.SHOW_GLOBAL_MESSAGE', true)) {
			return;
		}

		$file = (string) $this->getModuleConfig('GLOBAL_TXT', getBackendGlobalDir() . 'globalmsg.txt');

		$this->listen('FrontHeader', function (string &$headHtml): void {
			$this->includeStylesheet('globalMessage.css');
		});

		$this->listen('FrontInfo', function (string &$html) use ($file): void {
			$message = getGlobalMessage($file);
			if ($message !== '') {
				$html .= '<div id="globalmsg">' . $message . '</div>';
			}
		});

		// the message is a file, so a cached page goes stale when it changes
		$this->listen('FrontCacheDependencies', function (array &$files) use ($file): void {
			$files[] = $file;
		});
	}
}
