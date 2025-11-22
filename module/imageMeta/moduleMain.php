<?php
/* imageMeta module : image reverse searching and exif information (Pre-Alpha)
 * $Id$
 * exif.php from http://www.rjk-hosting.co.uk/programs/prog.php?id=4
 */

namespace Kokonotsuba\Modules\imageMeta;

require __DIR__ . '/exif.php';

use Kokonotsuba\ModuleClasses\abstractModuleMain;
use RuntimeException;

class moduleMain extends abstractModuleMain {
	private $enable_exif, $enable_imgops, $enable_iqdb, $enable_swfchan = false; // Initialize options, actually defined in config files
	private $myPage;

	public function initialize(): void {
		$this->enable_exif = $this->getConfig('ModuleSettings.EXIF_DATA_VIEWER');
		$this->enable_imgops = $this->getConfig('ModuleSettings.IMG_OPS');
		$this->enable_iqdb = $this->getConfig('ModuleSettings.IQDB');
		$this->enable_swfchan = $this->getConfig('ModuleSettings.SWFCHAN');

		// Listen to posts rendering
		$this->moduleContext->moduleEngine->addListener('Attachment', function(
			string &$attachmentProperties, 
			string &$attachmentImage, 
			string &$attachmentUrl, 
			array &$attachment
		) {
			$this->onRenderAttachment($attachmentProperties, $attachment);
		});
	}

	public function getName(): string {
		return 'image meta info and reverse searcher';
	}

	public function getVersion(): string {
		return 'Koko BBS Release 1';
	}

	/**
	 * Render the attachment with EXIF and reverse search links.
	 */
	public function onRenderAttachment(string &$attachmentProperties, array &$attachment): void {
		// Prepare HTML to append to the attachment properies
		$sauceHtml = '';

		$fileExtension = strtolower($attachment['fileExtension']);
		$isSwf = $fileExtension === 'swf';
		static $nonReverseSearchableExtensions = ['swf', 'mp4', 'webm'];
		$isNotAReverseSearchableImage = in_array($fileExtension, $nonReverseSearchableExtensions);

		// Check if the image exists, skip if not
		if (!attachmentFileExists($attachment)) {
			return;
		}

		// EXIF link
		if ($this->enable_exif) {
			// exif parameters
			$queryParameters = http_build_query(
				[
					'postUid' => $attachment['postUid'],
					'fileId' => $attachment['fileId']
				]
			);

			// append to html
			$sauceHtml .= '<span class="exifLink imageOptions">[<a href="' . $this->myPage . $queryParameters . '">EXIF</a>]</span> ';
		}

		// ImgOps reverse search
		if ($this->enable_imgops && !$isNotAReverseSearchableImage) {
			$sauceHtml .= '<span class="imgopsLink imageOptions">[<a href="http://imgops.com/' . getAttachmentUrl($attachment) . '" target="_blank">ImgOps</a>]</span> ';
		}

		// IQDB search
		if ($this->enable_iqdb && !$isNotAReverseSearchableImage) {
			$sauceHtml .= '<span class="iqdbLink imageOptions">[<a href="http://iqdb.org/?url=' . getAttachmentUrl($attachment) . '" target="_blank">iqdb</a>]</span> ';
		}

		// SWFChan archive
		if ($this->enable_swfchan && $isSwf) {
			$rawByteFileSize = $attachment['fileSize'];
			$sauceHtml .= '<span class="swfchanLink imageOptions">[<a href="http://eye.swfchan.com/search/?q=>' . $rawByteFileSize . '" target="_blank">swfchan</a>]</span> ';
		}

		// Append link to the attachment properties
		$attachmentProperties .= $sauceHtml;
	}

	/**
	 * Module page for displaying EXIF data of attachments.
	 * Handles multiple attachments by reading requested file.
	 */
	public function ModulePage() {
		echo $this->moduleContext->board->getBoardHead('Image meta');

		echo '[<a href="' . $this->getConfig('STATIC_INDEX_FILE') . '">Return</a>]';
		echo '<ul class="exifInfoList">';

		// get post uid from request
		$postUid = $_GET['postUid'] ?? null;

		// validate post uid
		if(!$postUid || $postUid <= 0) {
			throw new RuntimeException;
		}

		// get file id from request
		$fileId = $_GET['fileId'] ?? null;

		// validate file id
		if(!$fileId || $fileId <= 0) {
			throw new RuntimeException;
		}

		// fetch post associated with uid
		$post = $this->moduleContext->postRepository->getPostByUid($postUid);

		// throw runtime exception if post doesn't exist
		if(!$post) {
			throw new RuntimeException;
		}

		// get board
		$board = searchBoardArrayForBoard($post['boardUID']);

		// get attachments
		$attachments = $post['attachments'] ?? null;

		// throw runtime exception if no attachments
		if(!$attachments) {
			throw new RuntimeException;
		} 

		// get attachment
		$attachment = $post['attachments'][$fileId] ?? null;

		// throw runtime if attachment not found
		if(!$attachment) {
			throw new RuntimeException;
		}

		if (attachmentFileExists($attachment)) {
			$pfile = $board->getBoardUploadedFilesDirectory() . $board->getConfigValue('IMG_DIR') . $attachment['storedFileName'] . '.' . $attachment['fileExtension'];

			if (function_exists("exif_read_data")) {
				echo "<li>DEBUG: Using exif_read_data()</li>";
				$exif_data = exif_read_data($pfile, 0, true);

				if (is_array($exif_data) && count($exif_data)) {
					echo '<li>Image contains EXIF data:</li>';
					echo '</ul><table class="exif postlists"><tbody>';
					foreach ($exif_data as $key => $section) {
						foreach ($section as $name => $value) {
							echo "<tr><th>$key.$name</th><td>$value</td></tr>";
						}
					}
					echo '</tbody></table><p>';
				} else {
					echo '<li>No EXIF data found.</li>';
				}
			} else {
				echo "<li>DEBUG: Using built-in exif library</li>";
				$exif = new exif($pfile);
				if (count($exif->exif_data)) {
					echo '<li>Image contains EXIF data:</li>';
					echo '</ul><table border="1"><tbody>';
					foreach ($exif->exif_data as $key => $value) {
						echo "<tr><th>$key</th><td>$value</td></tr>";
					}
					echo '</tbody></table><p>';
				} else {
					echo '<li>No EXIF data found.</li>';
				}
			}
		} else {
			echo '<li><strong class="error">File Not Found!</strong></li>';
		}

		if (isset($_SERVER['HTTP_REFERER'])) {
			echo '[<a href="' . $_SERVER['HTTP_REFERER'] . '" onclick="event.preventDefault();history.go(-1);">Back</a>]';
		}

		echo $this->moduleContext->board->getBoardFooter();
	}
}
