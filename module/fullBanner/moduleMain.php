<?php

/*
	fullBanner
	By: bobman (Yahoo! ^_^)
*/

namespace Kokonotsuba\Modules\fullBanner;

require_once __DIR__ . '/fullBannerEntry.php';
require_once __DIR__ . '/fullBannerRepository.php';
require_once __DIR__ . '/fullBannerService.php';
require_once __DIR__ . '/fullBannerLib.php';

use Kokonotsuba\error\BoardException;
use Kokonotsuba\module_classes\abstractModuleMain;
use Kokonotsuba\module_classes\traits\listeners\AboveThreadsGlobalListenerTrait;
use Kokonotsuba\module_classes\traits\listeners\BelowThreadsGlobalListenerTrait;

use function Kokonotsuba\libraries\_T;
use function Kokonotsuba\libraries\serveMedia;
use function Puchiko\request\redirect;
use function Puchiko\strings\sanitizeStr;
use function Kokonotsuba\libraries\html\drawPager;

class moduleMain extends abstractModuleMain {
	use AboveThreadsGlobalListenerTrait;
	use BelowThreadsGlobalListenerTrait;

	private readonly bool $showTopAd;
	private readonly bool $showBottomAd;
	private readonly int $submissionCooldown;
	private readonly int $requiredWidth;
	private readonly int $requiredHeight;
	private readonly int $maxFileSize;
	private readonly string $modulePageUrl;
	private readonly string $bannerServerUrl;
	private readonly string $serveImageUrl;
	private fullBannerService $fullBannerService;
	private bool $hasActiveBanners = false;

	// Names
	public function getName(): string {
		return 'Kokonotsuba Full Banners';
	}

	public function getVersion(): string {
		return 'Twendy twendy sex';
	}

	public function initialize(): void {
		$this->showTopAd = $this->getConfig('ModuleSettings.SHOW_TOP_AD');
		$this->showBottomAd = $this->getConfig('ModuleSettings.SHOW_BOTTOM_AD');
		$this->submissionCooldown = $this->getConfig('ModuleSettings.FULLBANNER_SUBMISSION_COOLDOWN', 300);
		$this->requiredWidth = $this->getConfig('ModuleSettings.FULLBANNER_REQUIRED_WIDTH', 468);
		$this->requiredHeight = $this->getConfig('ModuleSettings.FULLBANNER_REQUIRED_HEIGHT', 60);
		$this->maxFileSize = $this->getConfig('ModuleSettings.FULLBANNER_MAX_FILE_SIZE', 204800);
		$this->modulePageUrl = $this->getModulePageURL([], false, false);
		$this->bannerServerUrl = $this->getModulePageURL(['pageName' => 'bannerServer'], false, false);
		$this->serveImageUrl = $this->getModulePageURL(['pageName' => 'bannerServeImage'], false, false);

		$this->fullBannerService = getFullBannerService($this->moduleContext->transactionManager);

		$this->hasActiveBanners = $this->fullBannerService->getRandomActiveBanner() !== null;

		$this->listenAboveThreadsGlobal('onRenderAboveThreadArea');
		$this->listenBelowThreadsGlobal('onRenderBelowThreadArea');
	}

	private function renderBannerFrame(): string {
		return '<iframe class="fullbannerIframe" title="Banner" src="' . sanitizeStr($this->bannerServerUrl) . '"></iframe>
				<div class="fullbannerSuggestionContainer centerText">
					<small class="fullbannerSuggestion">
						<a class="fullbannerSuggestionAnchor" href="' . sanitizeStr($this->modulePageUrl) . '">' . sanitizeStr(_T('self_serve_banner_suggest')) . '</a>
					</small>
				</div>
				<hr class="hrAds">';
	}

	// Top Ad
	private function onRenderAboveThreadArea(string &$aboveThreadsHtml): void {
		if ($this->showTopAd && $this->hasActiveBanners) {
			$aboveThreadsHtml .= $this->renderBannerFrame();
		}
	}

	// Bottom Ad
	private function onRenderBelowThreadArea(string &$belowThreadsHtml): void {
		if ($this->showBottomAd && $this->hasActiveBanners) {
			$belowThreadsHtml .= $this->renderBannerFrame();
		}
	}

	private function handleBannerSubmission(): void {
		$file = $this->moduleContext->request->getFile('banner_file');
		if (!$file) {
			throw new BoardException('No file uploaded.');
		}

		$link = trim($this->moduleContext->request->getParameter('banner_link', 'POST', '') ?? '');
		if ($link !== '' && !filter_var($link, FILTER_VALIDATE_URL)) {
			throw new BoardException('Invalid destination link URL.');
		}
		if ($link === '') {
			$link = null;
		}

		$ipAddress = $this->moduleContext->request->getRemoteAddr();

		$this->fullBannerService->submitBanner($file, $link, $ipAddress, $this->submissionCooldown, $this->requiredWidth, $this->requiredHeight, $this->maxFileSize);
	}

	private function handleRequests(): void {
		$action = $this->moduleContext->request->getParameter('action', 'POST', '');

		if ($action === 'submitBanner') {
			try {
				$this->handleBannerSubmission();
			} catch (BoardException $e) {
				$this->handleBannerIndexPage('<p style="color:red;font-weight:bold;">' . htmlspecialchars($e->getMessage()) . '</p>');
				exit;
			}

			redirect($this->modulePageUrl . '&submittedBanner=1');
			exit;
		}
	}

	private function serveBanners(): void {
		$banner = $this->fullBannerService->getRandomActiveBanner();

		if (!$banner) {
			echo '<!DOCTYPE html><html><body></body></html>';
			return;
		}

		$bannerImageUrl = $this->serveImageUrl . '&file=' . urlencode($banner->banner_file_name);
		$bannerLink = $banner->link ? sanitizeStr($banner->link) : '#';

		echo '<!DOCTYPE html>
		<html lang="en" style="overflow:hidden;">
			<head>
				<meta charset="UTF-8">
				<meta name="viewport" content="width=device-width, initial-scale=1.0">
				<title>Full banner</title>
			</head>
			<body style="margin: 0;">
				<a href="' . $bannerLink . '" target="_blank"><img style="max-width: 100%;" src="' . sanitizeStr($bannerImageUrl) . '"></a>
			</body>
		</html>';
	}

	private function serveBannerImage(): void {
		$fileName = $this->moduleContext->request->getParameter('file', 'GET', '');
		if ($fileName === '') {
			header("HTTP/1.0 400 Bad Request");
			exit;
		}

		$filePath = $this->fullBannerService->getBannerFilePath($fileName);
		if ($filePath === null) {
			header("HTTP/1.0 404 Not Found");
			exit;
		}

		// Cache for 1 hour
		header('Cache-Control: public, max-age=3600');
		header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 3600) . ' GMT');

		serveMedia($filePath);
	}

	private function handleBannerIndexPage(string $statusMessage = ''): void {
		$staticIndexFile = $this->getConfig('STATIC_INDEX_FILE', 'index.html');
		$perPage = (int)$this->getConfig('ADMIN_PAGE_DEF', 100);
		$request = $this->moduleContext->request;
		$pageParam = $request->getParameter('page', 'GET', 0);
		$requestedPage = is_numeric($pageParam) ? (int)$pageParam : 0;
		$paginationData = $this->fullBannerService->getApprovedActiveBannersPage($requestedPage, $perPage);
		$banners = $paginationData['items'];

		$rows = array_map(fn($b) => $b->toPublicTemplateRow($this->serveImageUrl, $this->requiredWidth, $this->requiredHeight, $this->moduleContext->postDateFormatter), $banners);

		// Use drawPager for pagination
		$paginationHtml = drawPager($paginationData['entriesPerPage'], $paginationData['totalEntries'], $this->modulePageUrl, $request);

		$templateValues = [
			'{$STATIC_INDEX_FILE}' => sanitizeStr($staticIndexFile),
			'{$MODULE_PAGE_URL}' => sanitizeStr($this->modulePageUrl),
			'{$UPLOAD_HEADING}' => _T('fullbanner_submit_heading'),
			'{$UPLOAD_BUTTON}' => _T('fullbanner_submit_button'),
			'{$REQ_DIMENSIONS}' => _T('fullbanner_req_dimensions', $this->requiredWidth, $this->requiredHeight),
			'{$REQ_FILETYPES}' => _T('fullbanner_req_filetypes'),
			'{$REQ_FILESIZE}' => _T('fullbanner_req_filesize', round($this->maxFileSize / 1024)),
			'{$BANNER_WIDTH}' => (string) $this->requiredWidth,
			'{$BANNER_HEIGHT}' => (string) $this->requiredHeight,
			'{$STATUS_MESSAGE}' => $statusMessage,
			'{$ROWS}' => $rows,
			'{$EMPTY}' => empty($rows) ? '1' : '',
			'{$PAGINATION}' => $paginationHtml,
		];

		$pageContent = $this->moduleContext->adminPageRenderer->ParseBlock('FULLBANNER_INDEX', $templateValues);
		echo $this->moduleContext->adminPageRenderer->ParsePage('GLOBAL_ADMIN_PAGE_CONTENT', [
			'{$PAGE_CONTENT}' => $pageContent,
			'{$PAGER}' => $paginationHtml,
		], false);
	}

	private function handlePages(): void {
		$pageName = $this->moduleContext->request->getParameter('pageName', 'GET', '');

		if ($pageName === 'bannerServer') {
			$this->serveBanners();
			exit;
		} else if ($pageName === 'bannerServeImage') {
			$this->serveBannerImage();
			exit;
		} else {
			$statusMessage = '';
			if ($this->moduleContext->request->getParameter('submittedBanner', 'GET', '') === '1') {
				$statusMessage = '<p>' . sanitizeStr(_T('fullbanner_submit_success')) . '</p>';
			}
			$this->handleBannerIndexPage($statusMessage);
			exit;
		}
	}

	public function ModulePage(): void {
		if ($this->moduleContext->request->isPost()) {
			$this->handleRequests();
		} else if ($this->moduleContext->request->isGet()) {
			$this->handlePages();
		}
	}
}