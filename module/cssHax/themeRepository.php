<?php

namespace Kokonotsuba\Modules\cssHax;

use Kokonotsuba\database\databaseConnection as DatabasedatabaseConnection;

class themeRepository {
	public function __construct(
		private DatabasedatabaseConnection $databaseConnection,
		private string $threadThemeTable,
	) {}

	public function themeExists(string $threadUid): bool {
		// query to check if a row with the specified thread uid exists
		$query = "SELECT 1 FROM {$this->threadThemeTable} WHERE thread_uid = :thread_uid";

		// define param
		$params = [':thread_uid' => $threadUid];

		// fetch query value and return
		return $this->databaseConnection->fetchValue($query, $params);
	}

	public function addTheme(
		string $threadUid,
		?string $backgroundHexColor,
		?string $replyBackgroundHexColor,
		?string $textHexColor,
		?string $backgroundImageUrl,
		?string $rawStyling,
		int $addedBy
	): void {
		// query to insert a new row to the theme table
		$query = "INSERT INTO {$this->threadThemeTable} 
				(thread_uid, background_hex_color, reply_background_hex_color, text_hex_color, background_image_url, raw_styling, added_by)
				VALUES(:thread_uid, :background_hex_color, :reply_background_hex_color, :text_hex_color, :background_image_url, :raw_styling, :added_by)";

		// build parameters containing the theme data to be inserted
		$params = [
			':thread_uid' => $threadUid,
			':background_hex_color' => $backgroundHexColor,
			':reply_background_hex_color' => $replyBackgroundHexColor,
			':text_hex_color' => $textHexColor,
			':background_image_url' => $backgroundImageUrl,
			':raw_styling' => $rawStyling,
			':added_by' => $addedBy
		];

		// execute query and add it to the database
		$this->databaseConnection->execute($query, $params);
	}

	public function deleteTheme(string $threadUid): void {
		// query to delete the theme assoiated with the thread
		$query = "DELETE FROM {$this->threadThemeTable} WHERE thread_uid = :thread_uid";

		// define param
		$params = [':thread_uid' => $threadUid];
	
		// execute query and delete row
		$this->databaseConnection->execute($query, $params);
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
		$fields = [];
		$params = [
			':thread_uid' => $threadUid,
		];

		if ($backgroundHexColor !== null) {
			$fields[] = 'background_hex_color = :background_hex_color';
			$params[':background_hex_color'] = $backgroundHexColor;
		}

		if ($replyBackgroundHexColor !== null) {
			$fields[] = 'reply_background_hex_color = :reply_background_hex_color';
			$params[':reply_background_hex_color'] = $replyBackgroundHexColor;
		}

		if ($textHexColor !== null) {
			$fields[] = 'text_hex_color = :text_hex_color';
			$params[':text_hex_color'] = $textHexColor;
		}

		if ($backgroundImageUrl !== null) {
			$fields[] = 'background_image_url = :background_image_url';
			$params[':background_image_url'] = $backgroundImageUrl;
		}

		if ($audio !== null) {
			$fields[] = 'audio = :audio';
			$params[':audio'] = $audio;
		}		

		if ($rawStyling !== null) {
			$fields[] = 'raw_styling = :raw_styling';
			$params[':raw_styling'] = $rawStyling;
		}

		// Nothing to update
		if (empty($fields)) {
			return;
		}

		$query = sprintf(
			"UPDATE %s SET %s WHERE thread_uid = :thread_uid",
			$this->threadThemeTable,
			implode(', ', $fields)
		);

		$this->databaseConnection->execute($query, $params);
	}

}