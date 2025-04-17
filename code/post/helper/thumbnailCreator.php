<?php

class thumbnailCreator {
    private readonly board $board;
	private readonly array $config;
	private readonly mixed $FileIO;

	public function __construct(board $board, array $config, mixed $FileIO) {
		$this->board = $board;
        $this->config = $config;
		$this->FileIO = $FileIO;
	}

	public function makeAndUpload(string &$dest, string &$ext, string $tim, ?string $tmpfile, int $imgW, int $imgH, int $W, int $H): void {
		if (!$dest || !is_file($dest)) return;

		$baseDir = $this->board->getBoardUploadedFilesDirectory();
		$destFile = $baseDir . $this->config['IMG_DIR'] . $tim . $ext;
		$thumbFile = $baseDir . $this->config['THUMB_DIR'] . $tim . 's.' . $this->config['THUMB_SETTING']['Format'];

		if ($this->config['USE_THUMB'] !== 0) {
			$thumbType = $this->config['USE_THUMB'] === 1
				? $this->config['THUMB_SETTING']['Method']
				: $this->config['USE_THUMB'];

			require(getBackendCodeDir() . 'thumb/thumb.' . $thumbType . '.php');

			$thObj = !empty($tmpfile)
				? new ThumbWrapper($tmpfile, $imgW, $imgH)
				: new ThumbWrapper($dest, $imgW, $imgH);

			$thObj->setThumbnailConfig($W, $H, $this->config['THUMB_SETTING']);
			$thObj->makeThumbnailtoFile($thumbFile);
			chmod($thumbFile, 0666);
			unset($thObj);
		}

		rename($dest, $destFile);

		if (file_exists($thumbFile)) {
			$this->FileIO->uploadImage($tim . 's.' . $this->config['THUMB_SETTING']['Format'], $thumbFile, filesize($thumbFile), $this->board);
		}
	}
}
