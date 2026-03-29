<?php

namespace Kokonotsuba\Modules\cssHax;

use Kokonotsuba\error\BoardException;
use function Kokonotsuba\libraries\_T;

/** Service for managing per-thread visual themes. */
class themeService {
	public function __construct(
		private themeRepository $themeRepository
	){}

	/**
	 * Add a new theme for a thread. Throws if a theme already exists.
	 *
	 * @param string      $threadUid               Thread UID.
	 * @param string|null $backgroundHexColor      Background colour hex.
	 * @param string|null $replyBackgroundHexColor Reply area background colour hex.
	 * @param string|null $textHexColor            Text colour hex.
	 * @param string|null $backgroundImageUrl      Background image URL.
	 * @param string|null $audio                   Audio URL.
	 * @param string|null $rawStyling              Raw CSS override.
	 * @param int         $addedBy                 Account ID of the adding staff member.
	 * @return void
	 * @throws BoardException If a theme already exists for the thread.
	 */
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

	/**
	 * Edit an existing theme's non-null fields.
	 *
	 * @param string      $threadUid               Thread UID.
	 * @param string|null $backgroundHexColor      New background colour, or null to leave unchanged.
	 * @param string|null $replyBackgroundHexColor New reply background colour, or null.
	 * @param string|null $textHexColor            New text colour, or null.
	 * @param string|null $backgroundImageUrl      New background image URL, or null.
	 * @param string|null $audio                   New audio URL, or null.
	 * @param string|null $rawStyling              New raw CSS, or null.
	 * @return void
	 */
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

	/**
	 * Remove the theme for the given thread.
	 *
	 * @param string $threadUid Thread UID.
	 * @return void
	 */
	public function deleteTheme(string $threadUid): void {
		// just delete it, no further validation needed
		$this->themeRepository->deleteTheme($threadUid);
	}

	/**
	 * Check whether a theme exists for the given thread.
	 *
	 * @param string $threadUid Thread UID.
	 * @return bool True if a theme exists.
	 */
	public function themeExists(string $threadUid): bool {
		// check database if there's a theme for the specified thread uid
		return $this->themeRepository->themeExists($threadUid);
	}
}