<?php

namespace Kokonotsuba\Modules\excimerViewer;

use Kokonotsuba\error\BoardException;
use Kokonotsuba\module_classes\abstractModuleAdmin;
use Kokonotsuba\module_classes\traits\listeners\PostControlHooksTrait;
use Kokonotsuba\userRole;

use function Puchiko\strings\sanitizeStr;

class moduleAdmin extends abstractModuleAdmin {
	use PostControlHooksTrait;

	private readonly string $modulePageUrl;
	private readonly string $excimerDir;

	public function getRequiredRole(): userRole {
		return userRole::LEV_ADMIN;
	}

	public function getName(): string {
		return 'Excimer Profiler Viewer';
	}

	public function getVersion(): string {
		return 'Koko 2025';
	}

	public function initialize(): void {
		$this->excimerDir = getBackendGlobalDir() . 'excimer';
		$this->modulePageUrl = $this->getModulePageURL([], false);

		$this->registerLinksAboveBarHook('View Excimer profiles', $this->modulePageUrl, 'Excimer Profiles');
	}

	public function ModulePage() {
		$action = $this->moduleContext->request->getParameter('action', 'GET', '');

		if ($action === 'download') {
			$this->handleDownload();
			return;
		}

		$this->renderProfileList();
	}

	private function handleDownload(): void {
		$category = $this->moduleContext->request->getParameter('category', 'GET', '');
		$file = $this->moduleContext->request->getParameter('file', 'GET', '');

		if ($category === '' || $file === '') {
			throw new BoardException('Missing category or file parameter.');
		}

		// Validate inputs to prevent directory traversal
		if (preg_match('/[\/\\\\.]/', $category) || preg_match('/[\/\\\\]/', $file)) {
			throw new BoardException('Invalid parameter.');
		}

		$filepath = $this->excimerDir . DIRECTORY_SEPARATOR . $category . DIRECTORY_SEPARATOR . $file;

		if (!is_file($filepath) || !str_ends_with($file, '.speedscope.json')) {
			throw new BoardException('File not found.');
		}

		// Verify the resolved path is within the excimer directory
		$realExcimerDir = realpath($this->excimerDir);
		$realFilepath = realpath($filepath);
		if ($realExcimerDir === false || $realFilepath === false || !str_starts_with($realFilepath, $realExcimerDir)) {
			throw new BoardException('Invalid file path.');
		}

		header('Content-Type: application/json');
		header('Content-Disposition: attachment; filename="' . $file . '"');
		header('Content-Length: ' . filesize($realFilepath));
		readfile($realFilepath);
		exit;
	}

	private function renderProfileList(): void {
		$groupedFiles = $this->getGroupedProfiles();

		$categories = [];
		foreach ($groupedFiles as $category => $files) {
			$rows = [];
			foreach ($files as $fileInfo) {
				$rows[] = [
					'{$FILENAME}' => htmlspecialchars($fileInfo['name']),
					'{$SIZE}' => $this->formatFileSize($fileInfo['size']),
					'{$DATE}' => htmlspecialchars($fileInfo['date']),
					'{$DOWNLOAD_URL}' => $this->getModulePageURL([
						'action' => 'download',
						'category' => $category,
						'file' => $fileInfo['name'],
					]),
				];
			}

			$categories[] = [
				'{$CATEGORY_NAME}' => htmlspecialchars($category),
				'{$PROFILE_COUNT}' => count($files),
				'{$ROWS}' => $rows,
			];
		}

		$templateValues = [
			'{$HAS_PROFILES}' => !empty($categories),
			'{$CATEGORIES}' => $categories,
			'{$MODULE_URL}' => sanitizeStr($this->modulePageUrl),
		];

		$pageHtml = $this->moduleContext->adminPageRenderer->ParseBlock('EXCIMER_VIEWER_PAGE', $templateValues);
		echo $this->moduleContext->adminPageRenderer->ParsePage('GLOBAL_ADMIN_PAGE_CONTENT', ['{$PAGE_CONTENT}' => $pageHtml], true);
	}

	/**
	 * Scan the excimer directory and return profiles grouped by category,
	 * sorted newest-first within each category.
	 */
	private function getGroupedProfiles(): array {
		if (!is_dir($this->excimerDir)) {
			return [];
		}

		$grouped = [];
		$categories = scandir($this->excimerDir);

		foreach ($categories as $category) {
			if ($category === '.' || $category === '..') {
				continue;
			}

			$categoryPath = $this->excimerDir . DIRECTORY_SEPARATOR . $category;
			if (!is_dir($categoryPath)) {
				continue;
			}

			$files = glob($categoryPath . DIRECTORY_SEPARATOR . '*.speedscope.json');
			if (empty($files)) {
				continue;
			}

			// Sort by modification time descending (newest first)
			usort($files, fn($a, $b) => filemtime($b) <=> filemtime($a));

			$grouped[$category] = [];
			foreach ($files as $file) {
				$grouped[$category][] = [
					'name' => basename($file),
					'size' => filesize($file),
					'date' => date('Y-m-d H:i:s', filemtime($file)),
				];
			}
		}

		ksort($grouped);
		return $grouped;
	}

	private function formatFileSize(int $bytes): string {
		if ($bytes >= 1048576) {
			return round($bytes / 1048576, 2) . ' MB';
		} elseif ($bytes >= 1024) {
			return round($bytes / 1024, 2) . ' KB';
		}
		return $bytes . ' B';
	}
}
