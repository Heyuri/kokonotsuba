<?php

namespace Kokonotsuba\thumb;

/**
 * A thumbnail backend. Backends are picked per source file by thumbnailerFactory,
 * so each one only has to be honest about what it can read.
 */
interface thumbnailerInterface {
	/** Name this backend answers to in THUMB_SETTING.Method. */
	public function getMethodName(): string;

	/** Whether the backend's dependencies are installed on this host. */
	public function isWorking(): bool;

	/** Name and version, for the board status page. */
	public function describe(): string;

	/** Whether this backend can read the given source file. */
	public function supports(string $sourceFile): bool;

	/**
	 * Write a thumbnail of $sourceFile to $destinationFile.
	 *
	 * @return bool True when the file was written.
	 */
	public function makeThumbnail(string $sourceFile, string $destinationFile, thumbnailOptions $options): bool;
}
