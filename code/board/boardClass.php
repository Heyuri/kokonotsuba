<?php
//board class to encapsulate data gotten directly from board table
class board implements IBoard {
	private $databaseConnection;
	private $postNumberTable;
	private $templateEngine;
	private array $config = [];

	public int $board_uid;
	public string $board_identifier;
	public string $board_title;
	public string $board_sub_title;
	public string $config_name;
	public string $storage_directory_name;
	public string $date_added;
	public string $board_file_url;
	public bool $listed = false;

	// Getters
	public function getBoardUID(): int {
		return intval($this->board_uid);
	}

	public function getBoardTitle(): string {
		return htmlspecialchars($this->board_title ?? '');
	}

	public function getBoardSubTitle(): string {
		return htmlspecialchars($this->board_sub_title ?? '');
	}

	public function getFullConfigPath(): string {
		return getBoardConfigDir() . ($this->config_name ?? '');
	}

	public function getConfigFileName(): string {
		return $this->config_name ?? '';
	}

	public function getBoardStorageDirName(): string {
		return $this->storage_directory_name ?? '';
	}

	public function getDateAdded(): string {
		return $this->date_added ?? '';
	}

	public function getBoardIdentifier(): string {
		return $this->board_identifier ?? '';
	}

	public function getBoardListed(): bool {
		return $this->listed ?? false;
	}

	public function getBoardTemplateEngine(): templateEngine {
		return $this->templateEngine;
	}

	public function getBoardStoragePath(): string {
		return getBoardStoragesDir() . $this->getBoardStorageDirName() . '/';
	}

	public function updateBoardPathCache(): void {
		$boardPathCachingIO = boardPathCachingIO::getInstance();
		$currentDirectory = getcwd() . '/';

		if ($boardPathCachingIO->getRowByBoardUID($this->getBoardUID())) {
			$boardPathCachingIO->updateBoardPathCacheByBoardUID($this->getBoardUID(), $currentDirectory);
		} else {
			$boardPathCachingIO->addNewCachedBoardPath($this->getBoardUID(), $currentDirectory);
		}
	}

	public function getBoardCachedPath(): string {
		$boardPathCachingIO = boardPathCachingIO::getInstance();
		$boardPathCache = $boardPathCachingIO->getRowByBoardUID($this->getBoardUID());
		return $boardPathCache ? $boardPathCache->getBoardPath() : '';
	}

	public function getBoardCdnDir(): ?string {
		if (!isset($this->config['CDN_DIR']) || !$this->board_identifier) {
			return null;
		}
		return $this->config['CDN_DIR'] . $this->board_identifier . '/';
	}

	public function getBoardCdnUrl(): ?string {
		if (!isset($this->config['CDN_URL']) || !$this->board_identifier) {
			return null;
		}
		return $this->config['CDN_URL'] . $this->getBoardUID() . '-' . $this->board_identifier . '/';
	}

	public function getBoardLocalUploadDir(): string {
		$boardPathCachingIO = boardPathCachingIO::getInstance();
		$boardPathCache = $boardPathCachingIO->getRowByBoardUID($this->getBoardUID());

		return $boardPathCache ? $boardPathCache->getBoardPath() : '';
	}

	public function getBoardLocalUploadURL(): ?string {
		if (!isset($this->config['WEBSITE_URL'])) {
			return '';
		}
		return $this->config['WEBSITE_URL'] . $this->getBoardIdentifier() . '/';
	}

	public function getBoardUploadedFilesDirectory(): ?string {
		return !empty($this->config['USE_CDN'])
			? $this->getBoardCdnDir()
			: $this->getBoardLocalUploadDir();
	}

	public function getBoardUploadedFilesURL(): ?string {
		return !empty($this->config['USE_CDN'])
			? $this->getBoardCdnUrl()
			: $this->getBoardLocalUploadURL();
	}

	public function getBoardURL(): ?string {
		return isset($this->config['WEBSITE_URL'])
			? $this->config['WEBSITE_URL'] . $this->getBoardIdentifier() . '/'
			: '';
	}

	public function getBoardRootURL(): ?string {
		return $this->config['WEBSITE_URL'] ?? '';
	}

	private function __construct() {
		$config = $this->loadBoardConfig();
		$this->config = is_array($config) ? $config : [];

		$dbSettings = getDatabaseSettings();
		$this->databaseConnection = DatabaseConnection::getInstance();
		$this->postNumberTable = $dbSettings['POST_NUMBER_TABLE'] ?? '';

		// Template engine setup
		if (
			isset($this->config['REPLY_TEMPLATE_FILE']) &&
			isset($this->config['TEMPLATE_FILE'])
		) {
			$isReply = $_GET['res'] ?? '';
			$templateFileName = $isReply
				? $this->config['REPLY_TEMPLATE_FILE']
				: $this->config['TEMPLATE_FILE'];

			$templateFile = getBackendDir() . 'templates/' . $templateFileName;

			$dependencies = [
				'config'	=> $this->config,
				'boardData'	=> [
					'title'		=> $this->getBoardTitle(),
					'subtitle'	=> $this->getBoardSubTitle()
				]
			];

			$this->templateEngine = new templateEngine($templateFile, $dependencies);
		}
	}

	public function loadBoardConfig(): array {
		$fullConfigPath = $this->getFullConfigPath();

		if (!file_exists($fullConfigPath) || is_dir($fullConfigPath)) {
			return [];
		}		

		if (!empty($this->config)) {
			return $this->config;
		}

		// Load config if not already loaded
		require $fullConfigPath;
		$this->config = $config ?? [];

		return $this->config;
	}

	public function getBoardThreadURL(int $threadNumber): string {
		$phpSelf = $this->config['PHP_SELF'];
		$threadUrl = $this->getBoardURL()."$phpSelf?res=$threadNumber";
		
		return $threadUrl ?? '';
	}

	public function rebuildBoard(int $resno = 0, mixed $pagenum = -1, bool $single_page = false, int $last = -1, bool $logRebuild = false): void {
		$boardRebuilder = new boardRebuilder($this, $this->templateEngine);
		$boardRebuilder->rebuildBoardHtml($resno, $pagenum, $single_page, $last, $logRebuild);
	}

	public function getLastPostNoFromBoard(): int {
		$query = "SELECT COUNT(post_number) FROM {$this->postNumberTable} WHERE board_uid = :board_uid";
		$params = [':board_uid' => $this->getBoardUID()];

		return (int) $this->databaseConnection->fetchColumn($query, $params);
	}

	public function incrementBoardPostNumber(): void {
		$query = "INSERT INTO {$this->postNumberTable} (board_uid) VALUES(:board_uid)";
		$params = [':board_uid' => $this->getBoardUID()];

		$this->databaseConnection->execute($query, $params);
	}
}
