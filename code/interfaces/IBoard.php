<?php
/*
* Board interface for Kokonotsuba!
* Provide an interface for the board class
*/

interface IBoard {
	/**
	 * Load the board's configuration file.
	 *
	 * @return array Configuration array
	 */
	public function loadBoardConfig(): array;

	/**
	 * Get the template engine used by this board.
	 *
	 * @return templateEngine Template engine instance
	 */
	public function getBoardTemplateEngine(): templateEngine;

	/**
	 * Get the title of the board.
	 *
	 * @return string Board title
	 */
	public function getBoardTitle(): string;

	/**
	 * Get the subtitle of the board.
	 *
	 * @return string Board subtitle
	 */
	public function getBoardSubTitle(): string;

	/**
	 * Get the UID of the board.
	 *
	 * @return int Board UID
	 */
	public function getBoardUID(): int;

	/**
	 * Get the identifier of the board (e.g. short code like "b").
	 *
	 * @return string Board identifier
	 */
	public function getBoardIdentifier(): string;

	/**
	 * Get the storage directory name of the board.
	 *
	 * @return string Directory name
	 */
	public function getBoardStorageDirName(): string;

	/**
	 * Get the board's config filename.
	 *
	 * @return string Config filename
	 */
	public function getConfigFileName(): string;

	/**
	 * Get the full path to the config file.
	 *
	 * @return string Full config path
	 */
	public function getFullConfigPath(): string;

	/**
	 * Get the date the board was added.
	 *
	 * @return string ISO-formatted date
	 */
	public function getDateAdded(): string;

	/**
	 * Get whether the board is listed.
	 *
	 * @return bool True if listed, false otherwise
	 */
	public function getBoardListed(): bool;

	/**
	 * Get the absolute filesystem path for board file storage.
	 *
	 * @return string Local board storage path
	 */
	public function getBoardStoragePath(): string;

	/**
	 * Get the cached local path for the board.
	 *
	 * @return string Cached path string
	 */
	public function getBoardCachedPath(): string;

	/**
	 * Update the board path cache with the current directory.
	 *
	 * @return void
	 */
	public function updateBoardPathCache(): void;

	/**
	 * Get the full local directory path for CDN-based uploads.
	 *
	 * @return string|null CDN directory path or null
	 */
	public function getBoardCdnDir(): ?string;

	/**
	 * Get the full URL for CDN-based uploads.
	 *
	 * @return string|null CDN URL or null
	 */
	public function getBoardCdnUrl(): ?string;

	/**
	 * Get the full local upload directory path.
	 *
	 * @return string Local upload directory path
	 */
	public function getBoardLocalUploadDir(): string;

	/**
	 * Get the full URL to the local upload directory.
	 *
	 * @return string|null Local upload URL or null
	 */
	public function getBoardLocalUploadURL(): ?string;

	/**
	 * Get the correct upload directory path depending on CDN usage.
	 *
	 * @return string|null Upload directory
	 */
	public function getBoardUploadedFilesDirectory(): ?string;

	/**
	 * Get the correct upload URL depending on CDN usage.
	 *
	 * @return string|null Upload URL
	 */
	public function getBoardUploadedFilesURL(): ?string;

	/**
	 * Get the public-facing board URL.
	 *
	 * @return string|null Full board URL
	 */
	public function getBoardURL(): ?string;

	/**
	 * Get the root website URL for this board.
	 *
	 * @return string|null Root URL
	 */
	public function getBoardRootURL(): ?string;

	/**
	 * Render the specified thread as HTML.
	 *
	 * @param int $res Thread number
	 * @return void
	 */
	public function drawThread(int $res): void;

	/**
	 * Render the specified board page as HTML.
	 *
	 * @param int $pageNumber Page number
	 * @return void
	 */
	public function drawPage(int $pageNumber): void;

	/**
	 * Rebuild all HTML pages of the board.
	 *
	 * @param bool $logRebuild Whether to log this action
	 * @return void
	 */
	public function rebuildBoard(bool $logRebuild = false): void;

	/**
	 * Rebuild a specific board page as HTML.
	 *
	 * @param int $pageNumber Page to rebuild
	 * @param bool $logRebuild Whether to log this action
	 * @return void
	 */
	public function rebuildBoardPage(int $pageNumber, bool $logRebuild = false): void;

	/**
	 * Get the full URL to a thread (optionally with reply anchor).
	 *
	 * @param int $threadNumber Thread number
	 * @param int $replyNumber Optional reply number
	 * @param bool $isQuoteRedirect To render the target post id with a board_uid + post_number or just the post number (for quote js)
	 * @return string Thread URL
	 */
	public function getBoardThreadURL(int $threadNumber, int $replyNumber = 0, bool $isQuoteRedirect = false): string;

	/**
	 * Get the last post number issued on the board.
	 *
	 * @return int Last post number
	 */
	public function getLastPostNoFromBoard(): int;

	/**
	 * Insert one new post number row into the board's counter table.
	 *
	 * @return void
	 */
	public function incrementBoardPostNumber(): void;

	/**
	 * Insert multiple new post number rows into the board's counter table.
	 *
	 * @param int $count Number of post numbers to reserve
	 * @return void
	 */
	public function incrementBoardPostNumberMultiple(int $count): void;

	public function getConfigValue(string $key, $default = null, bool $throwOnMissing = false): mixed;

}
