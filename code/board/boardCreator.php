<?php
/*
* Board creation object for Kokonotsuba!
* Handles board creationss
*/


class boardCreator {
	public function __construct(
		private readonly boardService $boardService
	) {}

	public function createNewBoard(string $boardTitle, string $boardSubTitle, string $boardIdentifier, bool $boardListed, string $boardPath): board|null {
		$templateConfig = getTemplateConfigArray();
		$backendDirectory = getBackendDir();

		$inputFields = [
			'boardTitle' => $boardTitle,
			'boardSubTitle' => $boardSubTitle,
			'boardIdentifier' => $boardIdentifier,
			'boardListed' => $boardListed,
			'boardPath' => $boardPath
		];
		
		
		$newBoard = $this->boardService->createBoard($inputFields, $templateConfig, $backendDirectory, \Kokonotsuba\Root\Constants\userRole::LEV_ADMIN, getRoleLevelFromSession());

		return $newBoard;
	}
}
