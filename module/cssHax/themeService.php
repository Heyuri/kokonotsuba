<?php

namespace Kokonotsuba\Modules\cssHax;

use Kokonotsuba\error\BoardException;
use function Kokonotsuba\libraries\_T;

class themeService {
	public function __construct(
		private themeRepository $themeRepository
	){}

	public function addTheme(
		string $threadUid,
		?string $backgroundHexColor,
		?string $replyBackgroundHexColor,
		?string $textHexColor,
		?string $backgroundImageUrl,
		?string $audio,
		?string $rawStyling,
		int $addedBy
	): void {
		// check if an entry already exists
		if($this->themeRepository->themeExists($threadUid)) {
			throw new BoardException(_T('theme_exists'));
		}

		// add it to the database
		$this->themeRepository->addTheme(
			$threadUid,
			$backgroundHexColor,
			$replyBackgroundHexColor,
			$textHexColor,
			$backgroundImageUrl,
			$audio,
			$rawStyling,
			$addedBy
		);
	}

	public function editTheme(
		string $threadUid,
		?string $backgroundHexColor,
		?string $replyBackgroundHexColor,
		?string $textHexColor,
		?string $backgroundImageUrl,
		?string $audio,
		?string $rawStyling
	): void {
		// query database with changes
		$this->themeRepository->editTheme(
			$threadUid, 
			$backgroundHexColor, 
			$replyBackgroundHexColor,
			$textHexColor,
			$backgroundImageUrl, 
			$audio,
			$rawStyling
		);
	}

	public function deleteTheme(string $threadUid): void {
		// just delete it, no further validation needed
		$this->themeRepository->deleteTheme($threadUid);
	}

	public function themeExists(string $threadUid): bool {
		// check database if there's a theme for the specified thread uid
		return $this->themeRepository->themeExists($threadUid);
	}
}