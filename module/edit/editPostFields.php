<?php

namespace Kokonotsuba\Modules\edit;

use Kokonotsuba\post\textFormat;

/**
 * Conversions between a stored post comment and the text the edit form shows.
 *
 * A plain-text comment already is the text to edit, so it round-trips untouched - markers left
 * by a dice roll included, which is what keeps a roll intact through an edit.
 *
 * A comment stored before the plain-text switch is HTML: whatever the poster typed was escaped
 * at post time, line breaks became <br>, and modules may have left markup of their own in it.
 * Only the line breaks are translated there, so entities and markup survive the round trip byte
 * for byte and a moderator editing one line cannot silently mangle the rest.
 */
final class editPostFields {
	/** Matches a break tag in any of the spellings that have been stored over the years. */
	private const BREAK_PATTERN = '#<br\s*/?>#i';

	/**
	 * Turn a stored comment into the text shown in the edit textarea.
	 *
	 * @param string     $comment Comment as stored.
	 * @param textFormat $format  The post's stored text format.
	 * @return string Text for the textarea.
	 */
	public static function commentToEditableText(string $comment, textFormat $format): string {
		if (!$format->commentIsHtml()) {
			return $comment;
		}

		return (string)preg_replace(self::BREAK_PATTERN, "\n", $comment);
	}

	/**
	 * Turn edited textarea text back into a stored comment.
	 *
	 * @param string     $text   Text as typed into the edit form.
	 * @param textFormat $format The post's stored text format.
	 * @return string Comment ready for the database.
	 */
	public static function editableTextToComment(string $text, textFormat $format): string {
		// Normalize the line endings the browser sent before anything counts them.
		$text = str_replace(["\r\n", "\r"], "\n", $text);

		if (!$format->commentIsHtml()) {
			return $text;
		}

		// Newlines become <br> and are then dropped, which is how a comment of this vintage was
		// stored, so an edited one stays indistinguishable from its neighbours.
		return str_replace("\n", '', nl2br($text, false));
	}
}
