<?php
//board class to encapsulate data gotten directly from board table
class board implements IBoard {
	private $databaseConnection, $postNumberTable, $templateEngine;
	private $config;

	public $board_uid, $board_identifier, $board_title, $board_sub_title;
	public $config_name, $storage_directory_name, $date_added;
	public $board_file_url, $listed, $can_edit;

	// Getters
	public function getBoardUID(): int {
		return intval($this->board_uid);
	}

	public function getBoardTitle(): string {
		return htmlspecialchars($this->board_title);
	}

	public function getBoardSubTitle(): string {
		return htmlspecialchars($this->board_sub_title);
	}

	public function getFullConfigPath(): string {
		return getBoardConfigDir() . $this->config_name;
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

	public function getBoardCanEdit(): bool {
		return $this->can_edit ?? false;
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
		return $this->config['CDN_DIR'] . $this->board_identifier . '/';
	}

	public function getBoardCdnUrl(): ?string {
		return $this->config['CDN_URL'] . $this->getBoardUID() . '-' . $this->getBoardIdentifier() . '/';
	}

	public function getBoardLocalUploadDir(): ?string {
		$boardPathCachingIO = boardPathCachingIO::getInstance();
		$boardPathCache = $boardPathCachingIO->getRowByBoardUID($this->getBoardUID());

		return $boardPathCache ? $boardPathCache->getBoardPath() : null;
	}

	public function getBoardLocalUploadURL(): ?string {
		return $this->config['WEBSITE_URL'] . $this->getBoardIdentifier() . '/';
	}

	public function getBoardUploadedFilesDirectory(): ?string {
		return !empty($this->config['USE_CDN']) ?
			$this->getBoardCdnDir() :
			$this->getBoardLocalUploadDir();
	}

	public function getBoardUploadedFilesURL(): ?string {
		return !empty($this->config['USE_CDN']) ?
			$this->getBoardCdnUrl() :
			$this->getBoardLocalUploadURL();
	}

	public function getBoardURL(): ?string {
		return $this->config['WEBSITE_URL'] . $this->getBoardIdentifier() . '/';
	}

	public function getBoardRootURL(): ?string {
		return $this->config['WEBSITE_URL'];
	}
	
	private function __construct() {
		$config = $this->loadBoardConfig();
	
		if (!is_array($config)) {
			// Optionally log or mark the object as invalid
			$this->config = false;
			return;
		}
	
		$this->config = $config;
	
		$dbSettings = getDatabaseSettings();
		$this->databaseConnection = DatabaseConnection::getInstance();
		$this->postNumberTable = $dbSettings['POST_NUMBER_TABLE'];
	
		// Only continue if config is valid
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

	public function loadBoardConfig(): bool|array {
		$fullConfigPath = $this->getFullConfigPath();

		if(!file_exists($fullConfigPath) || empty($fullConfigPath)) return false;
		if($this->config) return $this->config;

		//only require when the config hasn't been set yet so it doesn't read from disk every time.
		require $fullConfigPath;
		$this->config = $config;

		return $config; 
	}

	/* Rebuild board HTML or output page HTML to a live PHP page */ 
	public function rebuildBoard(int $resno = 0, mixed $pagenum = -1, bool $single_page = false, int $last = -1, bool $logRebuild = false): void {
		$boardRebuilder = new boardRebuilder($this, $this->templateEngine);
		$boardRebuilder->rebuildBoardHtml($resno, $pagenum, $single_page, $last, $logRebuild);
	}

	/* Get the last post number */
	public function getLastPostNoFromBoard() {
		$query = "SELECT COUNT(post_number) FROM {$this->postNumberTable} WHERE board_uid = :board_uid";
		$params = [':board_uid' => $this->getBoardUID()];
		
		$result = $this->databaseConnection->fetchColumn($query, $params);
		return $result;
	}
	
	public function incrementBoardPostNumber() {
		$query = "INSERT INTO {$this->postNumberTable} (board_uid) VALUES(:board_uid)";
		$params = [':board_uid' => $this->getBoardUID()];
		
		$this->databaseConnection->execute($query, $params);	
	}

}
