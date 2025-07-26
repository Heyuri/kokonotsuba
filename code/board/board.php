<?php
/**
 * Board class
 * 
 * This class represents an implementation of the IBoard interface and is responsible
 * for handling all board-related operations in the application. These include:
 * 
 * - Managing board configuration, metadata, and path caching
 * - Interacting with the database to manage post numbers
 * - Interfacing with the template engine and module system
 * - Providing utility methods for URL generation and content rendering
 * - Delegating rebuild operations to a boardRebuilder instance
 * 
 * Dependencies:
 * - boardPostNumbers: Encapsulates board post number-related operations
 * - templateEngine: For rendering board templates
 * - boardData: Encapsulates static metadata about the board
 * - boardRebuilder: Used to generate HTML output
 * - moduleEngine: Handles additional modular behavior for the board
 * 
 */

class board implements IBoard {
	private readonly boardPostNumbers $boardPostNumbers;
	private array $config = [];
	private boardData $boardData;
	private boardPathService $boardPathService;
	private ?boardRebuilder $boardRebuilder;
	private ?moduleEngine $moduleEngine;
	private ?templateEngine $templateEngine;

	public function __construct(boardPostNumbers $boardPostNumbers, boardData $boardData, boardPathService $boardPathService) {
		$this->boardData = $boardData;
		
		$config = $this->loadBoardConfig();
		$this->config = is_array($config) ? $config : [];

		$this->boardPostNumbers = $boardPostNumbers;
		$this->boardPathService = $boardPathService;
		
		$this->templateEngine = null;
		$this->boardRebuilder = null;
		$this->moduleEngine = null;
	}

	// Must be set for rebuilding methods to work
	public function setBoardRebuilder(boardRebuilder $boardRebuilder): void {
		$this->boardRebuilder = $boardRebuilder;
	}

	// Must be set for rebuilding methods to work
	public function setModuleEngine(moduleEngine $moduleEngine): void {
		$this->moduleEngine = $moduleEngine;
	}

	public function getModuleEngine(): moduleEngine {
		return $this->moduleEngine;
	}

	// Must be set for rebuilding and html methods to work
	public function setTemplateEngine(?templateEngine $templateEngine): void {
		$this->templateEngine = $templateEngine;
	}


	public function getBoardUID(): int {
		return $this->boardData->getBoardUID();
	}

	public function getBoardTitle(): string {
		return $this->boardData->getBoardTitle();
	}

	public function getBoardSubTitle(): string {
		return $this->boardData->getBoardSubTitle();
	}

	public function getFullConfigPath(): string {
		return getBoardConfigDir() . ($this->boardData->getConfigFileName() ?? '');
	}

	public function getConfigFileName(): string {
		return $this->boardData->getConfigFileName();
	}

	public function getBoardStorageDirName(): string {
		return $this->boardData->getBoardStorageDirName();
	}

	public function getDateAdded(): string {
		return $this->boardData->getDateAdded();
	}

	public function getBoardIdentifier(): string {
		return $this->boardData->getBoardIdentifier();
	}

	public function getBoardListed(): bool {
		return $this->boardData->getBoardListed();
	}

	public function getBoardTemplateEngine(): ?templateEngine {
		return $this->templateEngine;
	}

	public function getBoardStoragePath(): string {
		return getBoardStoragesDir() . $this->getBoardStorageDirName() . '/';
	}

	public function getConfigValue(string $key, $default = null, bool $throwOnMissing = false): mixed {
		$keys = explode('.', $key);
		$value = $this->config;

		foreach ($keys as $segment) {
			if (is_array($value) && array_key_exists($segment, $value)) {
				$value = $value[$segment];
			} else {
				if ($throwOnMissing) {
					throw new InvalidArgumentException("Missing config key: $key");
				}
				return $default;
			}
		}

		return $value;
	}

	public function updateBoardPathCache(): void {
		$currentDirectory = getcwd() . '/';

		if ($this->boardPathService->getByBoardUid($this->getBoardUID())) {
			$this->boardPathService->updatePath($this->getBoardUID(), $currentDirectory);
		} else {
			$this->boardPathService->addNew($this->getBoardUID(), $currentDirectory);
		}
	}

	public function getBoardCachedPath(): string {
		$boardPathCache = $this->boardPathService->getByBoardUid($this->getBoardUID());
		return $boardPathCache ? $boardPathCache->getBoardPath() : '';
	}
	
	public function getBoardLocalUploadDir(): string {
		$boardPathCache = $this->boardPathService->getByBoardUid($this->getBoardUID());

		return $boardPathCache ? $boardPathCache->getBoardPath() : '';
	}

	public function getBoardCdnDir(): ?string {
		if (!isset($this->config['CDN_DIR']) || !$this->boardData->getBoardIdentifier()) {
			return null;
		}
		return $this->config['CDN_DIR'] . $this->boardData->getBoardIdentifier() . '/';
	}

	public function getBoardCdnUrl(): ?string {
		if (!isset($this->config['CDN_URL']) || !$this->boardData->getBoardIdentifier()) {
			return null;
		}
		return $this->config['CDN_URL'] . $this->getBoardUID() . '-' . $this->boardData->getBoardIdentifier() . '/';
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

	public function getBoardURL(bool $liveFrontend = false, bool $staticView = false): ?string {
		$boardUrl = $this->getConfigValue('WEBSITE_URL') . $this->getBoardIdentifier() . '/';
		
		// For if you want to include the indez file explicitly
		// Live: koko.php
		// Static: index.html
		if($liveFrontend === true) {
			$boardUrl .= $this->getConfigValue('LIVE_INDEX_FILE');
		} else if($staticView === true) {
			$boardUrl .= $this->getConfigValue('STATIC_INDEX_FILE');
		}

		return $boardUrl;
	}

	public function getBoardRootURL(): ?string {
		return $this->config['WEBSITE_URL'] ?? '';
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
		$this->boardRebuilder->drawThread($res);
	}

	public function drawPage(int $pageNumber): void {
		$this->boardRebuilder->drawPage($pageNumber);
	}

	public function rebuildBoard(bool $logRebuild = false): void {
		$this->boardRebuilder->rebuildBoardHtml($logRebuild);
	}

	public function rebuildBoardPage(int $pageNumber, bool $logRebuild = false): void {
		$this->boardRebuilder->rebuildBoardPageHtml($pageNumber, $logRebuild);
	}

	public function rebuildBoardPages(int $amountOfPagesToRebuild): void {
		$this->boardRebuilder->rebuildBoardPages($amountOfPagesToRebuild);
	}

	public function getBoardThreadURL(int $threadNumber, int $replyNumber = 0, bool $isQuoteRedirect = false): string {
		$liveIndexFile = $this->config['LIVE_INDEX_FILE'];
		$replyString = '';

		if($replyNumber > 0) {
			$replyString = $isQuoteRedirect ? '#q' . $replyNumber 
				: '#p'.$this->getBoardUID().'_'.$replyNumber;
		
		}

		$threadUrl = $this->getBoardURL()."$liveIndexFile?res=$threadNumber$replyString";
		
		return $threadUrl ?? '';
	}

	public function getBoardHead(string $pageTitle = '', int $threadNumber = 0): string {
		$headHtml = generateHeadHtml($this->config, $this->templateEngine, $this->moduleEngine, $pageTitle, $threadNumber);

		return $headHtml;
	}

	public function getBoardPostForm(int $resno = 0, string $moduleInfoHook = '', string $name = '', string $email = '', string $subject = '', string $comment = '', string $category = ''): string {
		$postFormHtml = generatePostFormHTML($resno, $this, $this->config, $this->templateEngine, $this->moduleEngine, $moduleInfoHook, $name, $email, $subject, $comment, $category);
	
		return $postFormHtml;
	}

	public function getBoardFooter(bool $isThread = false): string {
		$footerHtml = generateFooterHtml($this->templateEngine, $this->moduleEngine, $isThread);

		return $footerHtml;
	}

	public function incrementBoardPostNumber(): void {
		$this->boardPostNumbers->incrementBoardPostNumber($this->getBoardUID());
	}

	public function incrementBoardPostNumberMultiple(int $count): void {
		$this->boardPostNumbers->incrementBoardPostNumberMultiple($this->getBoardUID(), $count);
	}

	public function getLastPostNoFromBoard(): int {
		return $this->boardPostNumbers->getLastPostNoFromBoard($this->getBoardUID());
	}
}
