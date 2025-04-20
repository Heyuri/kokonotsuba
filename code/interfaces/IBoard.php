<?php

interface IBoard {
	/**
	 * Get the current board's configuration settings.
	 *
	 * @return array Configuration array
	 */
	public function loadBoardConfig(): bool|array;

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
	 * 
	*/
	public function getBoardTitle(): string;

	/**
	 * Get the uid of the board.
	 *
	 * @return int Board uid
	 */
	public function getBoardUID(): int;

	/**
	 * Get the identifier of the board.
	 *
	 * @return string
	 */
	public function getBoardIdentifier(): string;

	/**
	 * Get the identifier of the board.
	 *
	 * @return string
	 */
	public function getBoardCachedPath(): string;

	/**
	 * Rebuild the html of the board.
	 *
	 * @return void
	 */
	public function rebuildBoard(int $resno = 0, mixed $pagenum = -1, bool $single_page = false, int $last = -1, bool $logRebuild = false): void;
	
	/**
	 * Get the last post number of the board.
	 *
	 * @return int
	 */
	public function getLastPostNoFromBoard();
	
	/**
	 * Get url template for a thread
	 *
	 * @return string
	 */
	public function getBoardThreadURL(int $threadNumber): string;

	/**
	 * Get uploaded files directory for a board
	 *
	 * @return string
	 */
	public function getBoardUploadedFilesDirectory(): ?string ;
}
