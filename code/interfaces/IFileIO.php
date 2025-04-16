<?php

/**
 * IFileIO
 */
interface IFileIO {
	/**
	 * Perform initialization. Usually done once during installation.
	 */
	function init();

	/**
	 * Check if an image file exists.
	 *
	 * @param string $imgname Image filename
	 * @return bool Whether it exists
	 */
	function imageExists($imgname, $board);

	/**
	 * Delete an image.
	 *
	 * @param string $imgname Image filename
	 */
	function deleteImage($imgname, $board);

	/**
	 * Upload an image.
	 *
	 * @param string $imgname Image filename
	 * @param string $imgpath Image file path
	 * @param int    $imgsize Image file size (in bytes)
	 */
	function uploadImage($imgname, $imgpath, $imgsize, $board);

	/**
	 * Get image file size.
	 *
	 * @param string $imgname Image filename
	 * @return mixed File size (in bytes) or 0 on failure
	 */
	function getImageFilesize($imgname, $board);

	/**
	 * Get image URL for use in <img> tags.
	 *
	 * @param string $imgname Image filename
	 * @return string Image URL
	 */
	function getImageURL($imgname, $board);

	/**
	 * Get thumbnail filename.
	 *
	 * @param string $thumbPattern Thumbnail filename format
	 * @return string Thumbnail filename
	 */
	function resolveThumbName($thumbPattern, $board);

	/**
	 * Return current total storage size (in KB)
	 *
	 * @return int Current total storage size
	 */
	function getCurrentStorageSize($board);
}
