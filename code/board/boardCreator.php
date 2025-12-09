<?php
/*
* Board creation object for Kokonotsuba!
* Handles board creationss
*/

use Kokonotsuba\Root\Constants\userRole;

class boardCreator {
	public function __construct(
		private readonly boardService $boardService
	) {}

	public function createNewBoard(string $boardTitle, string $boardSubTitle, string $boardIdentifier, bool $boardListed, string $boardPath, userRole $currentRoleLevel): board|null {
		$templateConfig = getTemplateConfigArray();
		$backendDirectory = getBackendDir();

		$inputFields = [
			'boardTitle' => $boardTitle,
			'boardSubTitle' => $boardSubTitle,
			'boardIdentifier' => $boardIdentifier,
			'boardListed' => $boardListed,
			'boardPath' => $boardPath
		];
		
		
		$newBoard = $this->boardService->createBoard($inputFields, $templateConfig, $backendDirectory, \Kokonotsuba\Root\Constants\userRole::LEV_ADMIN, $currentRoleLevel);

		return $newBoard;
	}
}
