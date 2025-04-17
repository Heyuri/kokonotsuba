<?php
// handles file uploading for new posts

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
	private string $md5chksum = '';
	private int $imgW = 0;
	private int $imgH = 0;
	private string $imgsize = '';
	private int $thumbW = 0;
	private int $thumbH = 0;
	private string $ext = '';
	private string $fname = '';
	private string $tmpfile = '';
	private string $dest = '';

	public function __construct(board $board, array $config, postValidator $postValidator, globalHTML $globalHTML, thumbnailCreator $thumbnailCreator, mixed $FileIO) {
		$this->board = $board;
		$this->config = $config;
		$this->postValidator = $postValidator;
		$this->globalHTML = $globalHTML;
		$this->thumbnailCreator = $thumbnailCreator;
		$this->FileIO = $FileIO;
	}

	public function process(string $thread_uid, string $tim): array {
		[$this->upfile, $this->upfile_path, $this->upfile_name, $this->upfile_status] = loadUploadData();
		$this->postValidator->validateFileUploadStatus($thread_uid, $this->upfile_status);

		if($this->upfile && (is_uploaded_file($this->upfile) || is_file($this->upfile))) {
			$this->saveUploadedFile($tim);
			$this->removeExif();
			$this->validateUploadIntegrity();
			$this->checkFileType();
			$this->generateThumbnail($thread_uid, $tim);
			_T('regist_uploaded', sanitizeStr($this->upfile_name));
		
			if (file_exists($this->dest)) {
				$this->FileIO->uploadImage($tim . $this->ext, $$this->dest, filesize($this->dest), $this->board);
			}
		}

		$file = new file($this->ext, $this->fname, $this->imgsize, $this->imgW, $this->imgH);
		$thumbnail = new thumbnail($this->thumbW, $this->thumbH);
		
		// return file data
		return [$file, $thumbnail];
	}


	private function saveUploadedFile(int $tim): void {
		$this->dest = $this->board->getBoardStoragePath() . $tim . '.tmp';
		move_uploaded_file($this->upfile, $this->dest) or copy($this->upfile, $this->dest);
		chmod($this->dest, 0666);

		if(!is_file($this->dest)) {
			$this->globalHTML->error(_T('regist_upload_filenotfound'), $this->dest);
		}
	}

	private function removeExif(): void {
		if(function_exists('exif_read_data') && function_exists('exif_imagetype')) {
			$imageType = exif_imagetype($this->dest);

			if($imageType == IMAGETYPE_JPEG) {
				$exif = @exif_read_data($this->dest);
				if($exif !== false) {
					$image = imagecreatefromjpeg($this->dest);
					imagejpeg($image, $this->dest, 100);
					imagedestroy($image);
				}
			}
		}
	}

	private function validateUploadIntegrity() {
		if(isset($_FILES['upfile'])) {
			$upsizeTTL = $_SERVER['CONTENT_LENGTH'];
			$upsizeHDR = 0;
			$tmp_upfile_path = $this->upfile_name;
			if($this->upfile_path) $tmp_upfile_path = $this->upfile_path;

			list(,$boundary) = explode('=', $_SERVER['CONTENT_TYPE']);
			foreach($_POST as $header => $value) {
				$upsizeHDR += strlen('--'.$boundary."\r\n")
					+ strlen('Content-Disposition: form-data; name="'.$header.'"'."\r\n\r\n".($value)."\r\n");
			}

			$upsizeHDR += strlen('--'.$boundary."\r\n")
				+ strlen('Content-Disposition: form-data; name="upfile"; filename="'.$tmp_upfile_path."\"\r\n".'Content-Type: '.$_FILES['upfile']['type']."\r\n\r\n")
				+ strlen("\r\n--".$boundary."--\r\n")
				+ $_FILES['upfile']['size'];

			if(($upsizeTTL - $upsizeHDR) > $this->config['HTTP_UPLOAD_DIFF']) {
				if($this->config['KILL_INCOMPLETE_UPLOAD']) {
					unlink($this->dest);
					die(_T('regist_upload_killincomp'));
				}
			}
		}
	}

	private function checkFileType(): void {
		$size = getimagesize($this->dest);
		$imgsize = filesize($this->dest);

		if($imgsize > $this->config['MAX_KB'] * 1024) {
			$this->globalHTML->error(_T('regist_upload_exceedcustom'));
		}

		$this->imgsize = ($imgsize >= 1024) ? (int)($imgsize / 1024).' KB' : $imgsize.' B';

		setlocale(LC_ALL, 'en_US.UTF-8');
		$this->fname = sanitizeStr(pathinfo($this->upfile_name, PATHINFO_FILENAME));
		$this->ext = '.'.strtolower(pathinfo($this->upfile_name, PATHINFO_EXTENSION));

		if(is_array($size)) {
			switch($size[2]) {
				case IMAGETYPE_GIF: $this->ext = '.gif'; break;
				case IMAGETYPE_JPEG:
				case IMAGETYPE_JPEG2000: $this->ext = '.jpg'; break;
				case IMAGETYPE_PNG: $this->ext = '.png'; break;
				case IMAGETYPE_SWF:
				case IMAGETYPE_SWC:
					$this->ext = '.swf';
					$size = getswfsize($this->dest);
					if(!($size[0] && $size[1])) {
						$size[0] = $this->config['MAX_W'];
						$size[1] = $this->config['MAX_H'];
					}
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
			$size = array(0, 0, 0);
			$video_exts = explode('|', strtolower($this->config['VIDEO_EXT']));
			if(array_search(substr($this->ext, 1), $video_exts) !== false) {
				$this->tmpfile = tempnam(sys_get_temp_dir(), "thumbnail_");
				rename($this->tmpfile, $this->tmpfile.".jpg");
				$this->tmpfile .= ".jpg";

				exec("ffmpeg -y -i ".$this->dest." -ss 00:00:1 -vframes 1 ".$this->tmpfile." 2>&1");

				$size = getimagesize($this->tmpfile);
				$this->imgsize = filesize($this->dest);
				$this->imgsize = ($this->imgsize >= 1024) ? (int)($this->imgsize / 1024).' KB' : $this->imgsize.' B';
			}
		}

		$this->imgW = $size[0];
		$this->imgH = $size[1];

		$allow_exts = explode('|', strtolower($this->config['ALLOW_UPLOAD_EXT']));
		if(array_search(substr($this->ext, 1), $allow_exts) === false) {
			$this->globalHTML->error(_T('regist_upload_notsupport'), $this->dest);
		}

		$this->md5chksum = md5_file($this->dest);
		if(array_search($this->md5chksum, $this->config['BAD_FILEMD5']) !== false) {
			$this->globalHTML->error(_T('regist_upload_blocked'), $this->dest);
		}
	}

	private function generateThumbnail(string $thread_uid, string $tim): void {
		$this->thumbW = $this->imgW;
		$this->thumbH = $this->imgH;

		$MAXW = $thread_uid ? $this->config['MAX_RW'] : $this->config['MAX_W'];
		$MAXH = $thread_uid ? $this->config['MAX_RH'] : $this->config['MAX_H'];

		if($this->thumbW > $MAXW || $this->thumbH > $MAXH) {
			$W2 = $MAXW / $this->thumbW;
			$H2 = $MAXH / $this->thumbH;
			$key = ($W2 < $H2) ? $W2 : $H2;
			$this->thumbW = ceil($this->thumbW * $key);
			$this->thumbH = ceil($this->thumbH * $key);
		}

		if($this->ext == '.swf') {
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
