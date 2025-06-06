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
		return $this->board_title ?? '';
	}

	public function getBoardSubTitle(): string {
		return $this->board_sub_title ?? '';
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

	public function drawThread(int $res): void {
		$boardRebuilder = new boardRebuilder($this, $this->templateEngine);
		$boardRebuilder->drawThread($res);
	}

	public function drawPage(int $pageNumber): void {
		$boardRebuilder = new boardRebuilder($this, $this->templateEngine);
		$boardRebuilder->drawPage($pageNumber);

	}

	public function rebuildBoard(bool $logRebuild = false): void {
		$boardRebuilder = new boardRebuilder($this, $this->templateEngine);
		$boardRebuilder->rebuildBoardHtml($logRebuild);
	}

	public function rebuildBoardPage(int $pageNumber, bool $logRebuild = false): void {
		$boardRebuilder = new boardRebuilder($this, $this->templateEngine);
		$boardRebuilder->rebuildBoardPageHtml($pageNumber, $logRebuild);
	}

	public function rebuildBoardPages(int $amountOfPagesToRebuild): void {
		$boardRebuilder = new boardRebuilder($this, $this->templateEngine);
		$boardRebuilder->rebuildBoardPages($amountOfPagesToRebuild);
	}

	public function getBoardThreadURL(int $threadNumber, int $replyNumber = 0, bool $isQuoteRedirect = false): string {
		$phpSelf = $this->config['PHP_SELF'];
		$replyString = '';

		if($replyNumber > 0) {
			$replyString = $isQuoteRedirect ? '#q' . $replyNumber 
				: '#p'.$this->getBoardUID().'_'.$replyNumber;
		
		}

		$threadUrl = $this->getBoardURL()."$phpSelf?res=$threadNumber$replyString";
		
		return $threadUrl ?? '';
	}

	public function getLastPostNoFromBoard(): int {
		$query = "SELECT post_number FROM {$this->postNumberTable} WHERE board_uid = :board_uid";
		$params = [':board_uid' => $this->getBoardUID()];

		return (int) $this->databaseConnection->fetchColumn($query, $params);
	}


	public function incrementBoardPostNumber(): void {
		$query = "
			INSERT INTO {$this->postNumberTable} (board_uid, post_number)
			VALUES (:board_uid, 1)
			ON DUPLICATE KEY UPDATE post_number = post_number + 1
		";
		$params = [':board_uid' => $this->getBoardUID()];

		$this->databaseConnection->execute($query, $params);
	}


	public function incrementBoardPostNumberMultiple(int $count): void {
		if ($count <= 0) {
			return;
		}

		$query = "
			INSERT INTO {$this->postNumberTable} (board_uid, post_number)
			VALUES (:board_uid, :count)
			ON DUPLICATE KEY UPDATE post_number = post_number + VALUES(post_number)
		";
		$params = [
			':board_uid' => $this->getBoardUID(),
			':count' => $count
		];

		$this->databaseConnection->execute($query, $params);
	}


}
