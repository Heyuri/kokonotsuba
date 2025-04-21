<?php

class fileProcessor {
	private readonly board $board;
	private readonly array $config;
	private readonly postValidator $postValidator;
	private readonly globalHTML $globalHTML;
	private readonly thumbnailCreator $thumbnailCreator;
	private readonly mixed $FileIO;

	private string $upfile = '';
	private string $upfile_path = '';
	private string $upfile_name = '';
	private int $upfile_status = 0;

	private string $ext = '';
	private string $fname = '';
	private string $tmpfile = '';
	private string $dest = '';
	private string $imgsize = '';
	private string $md5chksum = '';
	private string $mimeType = '';

	private int $imgW = 0;
	private int $imgH = 0;
	private int $thumbW = 0;
	private int $thumbH = 0;

	public function __construct(
		board $board,
		array $config,
		postValidator $postValidator,
		globalHTML $globalHTML,
		thumbnailCreator $thumbnailCreator,
		mixed $FileIO
	) {
		$this->board = $board;
		$this->config = $config;
		$this->postValidator = $postValidator;
		$this->globalHTML = $globalHTML;
		$this->thumbnailCreator = $thumbnailCreator;
		$this->FileIO = $FileIO;
	}

	public function process(string $thread_uid, string $tim): array {
		[$upfile, $upfilePath, $upfileName, $uploadStatus] = loadUploadData();
		$this->postValidator->validateFileUploadStatus($thread_uid, $uploadStatus);

		$this->upfile = $upfile;
		$this->upfile_path = $upfilePath;
		$this->upfile_name = $upfileName;
		$this->upfile_status = $uploadStatus;

		if ($upfile && (is_uploaded_file($upfile) || is_file($upfile))) {
			$this->moveFileToStorage($tim);
			$this->removeExifIfJpeg();
			$this->checkForUploadMismatch();
			$this->analyzeFileType();
			$this->createThumbnail($thread_uid, $tim);
			_T('regist_uploaded', sanitizeStr($upfileName));

			if (file_exists($this->dest)) {
				$this->FileIO->uploadImage($tim . $this->ext, $this->dest, filesize($this->dest), $this->board);
			}
		}

		$file = new file(
			$this->ext,
			$this->fname,
			$this->imgsize,
			$this->imgW,
			$this->imgH,
			$this->md5chksum,
			$this->dest,
			$this->mimeType
		);

		$thumbnail = new thumbnail($this->thumbW, $this->thumbH);

		return [$file, $thumbnail];
	}

	private function moveFileToStorage(int $tim): void {
		$this->dest = $this->board->getBoardStoragePath() . $tim . '.tmp';
		move_uploaded_file($this->upfile, $this->dest) or copy($this->upfile, $this->dest);
		chmod($this->dest, 0666);

		if (!is_file($this->dest)) {
			$this->globalHTML->error(_T('regist_upload_filenotfound'), $this->dest);
		}
	}

	private function removeExifIfJpeg(): void {
		if (!function_exists('exif_read_data') || !function_exists('exif_imagetype')) return;

		if (exif_imagetype($this->dest) === IMAGETYPE_JPEG) {
			$exif = @exif_read_data($this->dest);
			if ($exif !== false) {
				$image = imagecreatefromjpeg($this->dest);
				imagejpeg($image, $this->dest, 100);
				imagedestroy($image);
			}
		}
	}

	private function checkForUploadMismatch(): void {
		if (!isset($_FILES['upfile'])) return;

		$totalSize = $_SERVER['CONTENT_LENGTH'];
		$headerSize = 0;
		$boundary = explode('=', $_SERVER['CONTENT_TYPE'])[1];
		$upfilePath = $this->upfile_path ?: $this->upfile_name;

		foreach ($_POST as $name => $value) {
			$headerSize += strlen("--{$boundary}\r\n")
				+ strlen("Content-Disposition: form-data; name=\"{$name}\"\r\n\r\n{$value}\r\n");
		}

		$headerSize += strlen("--{$boundary}\r\n")
			+ strlen("Content-Disposition: form-data; name=\"upfile\"; filename=\"{$upfilePath}\"\r\n")
			+ strlen("Content-Type: " . $_FILES['upfile']['type'] . "\r\n\r\n")
			+ strlen("\r\n--{$boundary}--\r\n")
			+ $_FILES['upfile']['size'];

		if (($totalSize - $headerSize) > $this->config['HTTP_UPLOAD_DIFF']) {
			if ($this->config['KILL_INCOMPLETE_UPLOAD']) {
				unlink($this->dest);
				die(_T('regist_upload_killincomp'));
			}
		}
	}

	private function analyzeFileType(): void {
		$size = getimagesize($this->dest);
		$this->mimeType = $size['mime'] ?? 'application/octet-stream';
		$fileSize = filesize($this->dest);

		if ($fileSize > $this->config['MAX_KB'] * 1024) {
			$this->globalHTML->error(_T('regist_upload_exceedcustom'));
		}

		$this->imgsize = ($fileSize >= 1024) ? (int)($fileSize / 1024) . ' KB' : $fileSize . ' B';

		setlocale(LC_ALL, 'en_US.UTF-8');
		$this->fname = sanitizeStr(pathinfo($this->upfile_name, PATHINFO_FILENAME));
		$this->ext = '.' . strtolower(pathinfo($this->upfile_name, PATHINFO_EXTENSION));

		if (is_array($size)) {
			$this->imgW = $size[0];
			$this->imgH = $size[1];

			switch ($size[2]) {
				case IMAGETYPE_GIF: $this->ext = '.gif'; break;
				case IMAGETYPE_JPEG:
				case IMAGETYPE_JPEG2000: $this->ext = '.jpg'; break;
				case IMAGETYPE_PNG: $this->ext = '.png'; break;
				case IMAGETYPE_SWF:
				case IMAGETYPE_SWC:
					$this->ext = '.swf';
					$swfSize = getswfsize($this->dest);
					$this->imgW = $swfSize[0] ?: $this->config['MAX_W'];
					$this->imgH = $swfSize[1] ?: $this->config['MAX_H'];
					break;
				case IMAGETYPE_PSD: $this->ext = '.psd'; break;
				case IMAGETYPE_BMP: $this->ext = '.bmp'; break;
				case IMAGETYPE_WBMP: $this->ext = '.wbmp'; break;
				case IMAGETYPE_XBM: $this->ext = '.xbm'; break;
				case IMAGETYPE_TIFF_II:
				case IMAGETYPE_TIFF_MM:
				case IMAGETYPE_IFF: $this->ext = '.tiff'; break;
				case IMAGETYPE_JB2: $this->ext = '.jb2'; break;
				case IMAGETYPE_JPC: $this->ext = '.jpc'; break;
				case IMAGETYPE_JP2: $this->ext = '.jp2'; break;
				case IMAGETYPE_JPX: $this->ext = '.jpx'; break;
				case IMAGETYPE_ICO: $this->ext = '.ico'; break;
				case IMAGETYPE_WEBP: $this->ext = '.webp'; break;
			}
		} else {
			$this->imgW = $this->imgH = 0;

			$videoExts = explode('|', strtolower($this->config['VIDEO_EXT']));
			if (in_array(substr($this->ext, 1), $videoExts)) {
				$this->tmpfile = tempnam(sys_get_temp_dir(), 'thumbnail_') . '.jpg';
				exec("ffmpeg -y -i {$this->dest} -ss 00:00:01 -vframes 1 {$this->tmpfile} 2>&1");
				$size = getimagesize($this->tmpfile);

				if (is_array($size)) {
					$this->imgW = $size[0];
					$this->imgH = $size[1];
				}
			}
		}

		$allowed = explode('|', strtolower($this->config['ALLOW_UPLOAD_EXT']));
		if (!in_array(substr($this->ext, 1), $allowed)) {
			$this->globalHTML->error(_T('regist_upload_notsupport'));
		}

		$this->md5chksum = md5_file($this->dest);
		if (in_array($this->md5chksum, $this->config['BAD_FILEMD5'])) {
			$this->globalHTML->error(_T('regist_upload_blocked'));
		}
	}

	private function createThumbnail(string $thread_uid, string $tim): void {
		$this->thumbW = $this->imgW;
		$this->thumbH = $this->imgH;

		$maxW = $thread_uid ? $this->config['MAX_RW'] : $this->config['MAX_W'];
		$maxH = $thread_uid ? $this->config['MAX_RH'] : $this->config['MAX_H'];

		if ($this->thumbW > $maxW || $this->thumbH > $maxH) {
			$scale = min($maxW / $this->thumbW, $maxH / $this->thumbH);
			$this->thumbW = ceil($this->thumbW * $scale);
			$this->thumbH = ceil($this->thumbH * $scale);
		}

		if ($this->ext === '.swf') {
			$this->thumbW = $this->thumbH = 0;
		}

		$this->thumbnailCreator->makeAndUpload(
			$this->dest,
			$this->ext,
			$tim,
			$this->tmpfile,
			$this->imgW,
			$this->imgH,
			$this->thumbW,
			$this->thumbH
		);
	}
}
