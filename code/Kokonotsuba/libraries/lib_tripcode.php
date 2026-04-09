<?php

namespace Kokonotsuba\libraries;

function generateTripcode(string &$tripcode, string &$secure_tripcode, string $tripSalt = ''): void {
	if ($tripcode) {
		$tripcode = mb_convert_encoding($tripcode, 'Shift_JIS', 'UTF-8');
		$salt = strtr(preg_replace('/[^\.-z]/', '.', substr($tripcode . 'H.', 1, 2)), ':;<=>?@[\\]^_`', 'ABCDEFGabcdef');
		$tripcode = substr(crypt($tripcode, $salt), -10);
	}

	if ($secure_tripcode) {
		$sha = str_rot13(base64_encode(pack("H*", sha1($secure_tripcode . $tripSalt))));
		$secure_tripcode = substr($sha, 0, 10);
	}
}
