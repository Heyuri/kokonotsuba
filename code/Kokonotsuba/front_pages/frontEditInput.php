<?php

namespace Kokonotsuba\front_pages;

use Kokonotsuba\request\request;

/** Reading the front page editors' form fields. */
final class frontEditInput {
	/** The id being edited (GET or POST), or null for a new one. */
	public static function id(request $request): ?int {
		$value = $request->getParameter('id', 'POST') ?? $request->getParameter('id', 'GET');

		return is_string($value) && ctype_digit($value) && (int)$value > 0 ? (int)$value : null;
	}

	/** A posted text field; anything but a string reads as empty. */
	public static function text(request $request, string $name): string {
		$value = $request->getParameter($name, 'POST', '');

		return is_string($value) ? $value : '';
	}

	public static function checked(request $request, string $name): bool {
		return self::text($request, $name) === '1';
	}
}
