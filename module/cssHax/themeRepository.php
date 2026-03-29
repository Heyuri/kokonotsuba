<?php

namespace Kokonotsuba\Modules\cssHax;

use Kokonotsuba\database\baseRepository;
use Kokonotsuba\database\databaseConnection;

/** Repository for per-thread visual theme overrides. */
class themeRepository extends baseRepository {
	public function __construct(
		databaseConnection $databaseConnection,
		string $threadThemeTable,
	) {
		parent::__construct($databaseConnection, $threadThemeTable);
	}

	/**
	 * Check whether a theme entry already exists for the given thread.
	 *
	 * @param string $threadUid Thread UID to check.
	 * @return bool True if a theme exists.
	 */
	public function themeExists(string $threadUid): bool {
		return $this->exists('thread_uid', $threadUid);
	}

	/**
	 * Insert a new theme entry for a thread.
	 *
	 * @param string      $threadUid               Thread UID.
	 * @param string|null $backgroundHexColor      Background colour hex, or null.
	 * @param string|null $replyBackgroundHexColor Reply background colour hex, or null.
	 * @param string|null $textHexColor            Text colour hex, or null.
	 * @param string|null $backgroundImageUrl      Background image URL, or null.
	 * @param string|null $audio                   Audio URL, or null.
	 * @param string|null $rawStyling              Raw CSS, or null.
	 * @param int         $addedBy                 Account ID of the staff member adding the theme.
	 * @return void
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
		$this->insert([
			'thread_uid' => $threadUid,
			'background_hex_color' => $backgroundHexColor,
			'reply_background_hex_color' => $replyBackgroundHexColor,
			'text_hex_color' => $textHexColor,
			'background_image_url' => $backgroundImageUrl,
			'audio' => $audio,
			'raw_styling' => $rawStyling,
			'added_by' => $addedBy,
		]);
	}

	/**
	 * Delete the theme entry for the given thread.
	 *
	 * @param string $threadUid Thread UID.
	 * @return void
	 */
	public function deleteTheme(string $threadUid): void {
		$this->deleteWhere('thread_uid', $threadUid);
	}

	/**
	 * Update non-null fields on an existing theme entry.
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
		$fields = [];

		if ($backgroundHexColor !== null) {
			$fields['background_hex_color'] = $backgroundHexColor;
		}
		if ($replyBackgroundHexColor !== null) {
			$fields['reply_background_hex_color'] = $replyBackgroundHexColor;
		}
		if ($textHexColor !== null) {
			$fields['text_hex_color'] = $textHexColor;
		}
		if ($backgroundImageUrl !== null) {
			$fields['background_image_url'] = $backgroundImageUrl;
		}
		if ($audio !== null) {
			$fields['audio'] = $audio;
		}
		if ($rawStyling !== null) {
			$fields['raw_styling'] = $rawStyling;
		}

		if (!empty($fields)) {
			$this->updateWhere($fields, 'thread_uid', $threadUid);
		}
	}

}