<?php

namespace Kokonotsuba\Modules\debugBar;

require_once __DIR__ . '/debugBarFormatter.php';

use Kokonotsuba\board\boardRebuilder;
use Kokonotsuba\debug\requestMetrics;
use Kokonotsuba\debug\requestProfiler;
use Kokonotsuba\module_classes\abstractModuleMain;
use Kokonotsuba\module_classes\traits\listeners\FootListenerTrait;
use Kokonotsuba\module_classes\traits\listeners\ModuleHeaderListenerTrait;

use function Kokonotsuba\libraries\_T;
use function Kokonotsuba\libraries\getRoleLevelFromSession;
use function Puchiko\strings\sanitizeStr;

/**
 * A line of timings in the page footer for every reader of a live page, and for those allowed
 * it, a button that downloads an Excimer profile of the page as speedscope JSON. Off unless the
 * module is enabled; the profile is staff-only by default.
 */
class moduleMain extends abstractModuleMain {
	use FootListenerTrait;
	use ModuleHeaderListenerTrait;

	/** Whether this reader may have the profile download. */
	private bool $canProfile = false;

	public function getName(): string {
		return 'Debug bar';
	}

	public function getVersion(): string {
		return '1.0';
	}

	public function initialize(): void {
		$this->canProfile = !$this->getModuleConfig('PROFILER_STAFF_ONLY', true) || getRoleLevelFromSession()->isStaff();

		// The sampler was started on trust by the front controller; this is where it is allowed.
		if ($this->canProfile) {
			requestProfiler::authorize();
		}

		$this->listenModuleHeader('onGenerateModuleHeader');
		$this->listenFoot('onRenderFoot');
	}

	private function onGenerateModuleHeader(string &$moduleHeader): void {
		if (boardRebuilder::isRenderingStaticHtml()) {
			return;
		}

		$moduleHeader .= '<link rel="stylesheet" href="'
			. sanitizeStr($this->getConfig('STATIC_URL') . 'css/module/debugBar.css') . '">';
	}

	/**
	 * Timings are for the page being served now, so a static page, which is read long after it
	 * was built, gets none.
	 */
	private function onRenderFoot(string &$footer): void {
		if (boardRebuilder::isRenderingStaticHtml()) {
			return;
		}

		$values = debugBarFormatter::format(requestMetrics::snapshot());

		$footer .= $this->moduleContext->adminPageRenderer->ParseBlock('DEBUG_BAR', [
			'{$TOTAL_LABEL}' => sanitizeStr(_T('debugbar_total_time')),
			'{$TOTAL}' => sanitizeStr($values['total']),
			'{$CPU_LABEL}' => sanitizeStr(_T('debugbar_cpu')),
			'{$CPU_HINT}' => sanitizeStr(_T('debugbar_cpu_hint')),
			'{$CPU}' => sanitizeStr($values['cpu']),
			'{$QUERIES_LABEL}' => sanitizeStr(_T('debugbar_queries')),
			'{$QUERIES}' => sanitizeStr($values['queries']),
			'{$MEMORY_LABEL}' => sanitizeStr(_T('debugbar_memory')),
			'{$MEMORY}' => sanitizeStr($values['memory']),
			'{$PROFILE_HTML}' => $this->buildProfileControl(),
		]);
	}

	/** The download button, or why there is none; nothing at all for a reader who may not have it. */
	private function buildProfileControl(): string {
		if (!$this->canProfile) {
			return '';
		}

		if (!requestProfiler::isAvailable()) {
			return '<span class="debugBarUnavailable" title="' . sanitizeStr(_T('debugbar_profile_unavailable_hint')) . '">'
				. sanitizeStr(_T('debugbar_profile_unavailable')) . '</span>';
		}

		$url = requestProfiler::profileUrlFor((string)$this->moduleContext->request->getServer('REQUEST_URI', ''));

		return '<a class="debugBarProfile" href="' . sanitizeStr($url) . '" title="' . sanitizeStr(_T('debugbar_profile_hint')) . '">'
			. sanitizeStr(_T('debugbar_profile')) . '</a>';
	}
}
