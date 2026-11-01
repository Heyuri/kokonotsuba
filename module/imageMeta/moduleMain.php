<?php
/* imageMeta module : image reverse searching and exif information (Pre-Alpha)
 * $Id$
 * exif.php from http://www.rjk-hosting.co.uk/programs/prog.php?id=4
 */

namespace Kokonotsuba\Modules\imageMeta;

require __DIR__ . '/exif.php';

use Kokonotsuba\error\BoardException;
use Kokonotsuba\module_classes\abstractModuleMain;
use Kokonotsuba\module_classes\traits\listeners\AttachmentWidgetListenerTrait;
use Kokonotsuba\module_classes\traits\listeners\ModuleHeaderListenerTrait;

use function Kokonotsuba\libraries\_T;
use function Kokonotsuba\libraries\attachmentFileExists;
use function Kokonotsuba\libraries\constructAttachment;
use function Kokonotsuba\libraries\getAttachmentUrl;
use function Kokonotsuba\libraries\searchBoardArrayForBoard;
use function Puchiko\strings\sanitizeStr;

class moduleMain extends abstractModuleMain {
	use ModuleHeaderListenerTrait;
	use AttachmentWidgetListenerTrait;

	private $enable_exif, $enable_imgops, $enable_iqdb, $enable_swfchan = false; // Initialize options, actually defined in config files
	private $modulePageUrl;

	public function initialize(): void {
		$this->enable_exif = $this->getModuleConfig('EXIF_DATA_VIEWER');
		$this->enable_imgops = $this->getModuleConfig('IMG_OPS');
		$this->enable_iqdb = $this->getModuleConfig('IQDB');
		$this->enable_swfchan = $this->getModuleConfig('SWFCHAN');

		// Listen to posts rendering
		$this->listenAttachmentWidget('onRenderAttachmentWidget');
		$this->listenModuleHeader('onModuleHeaderStylesheet');
	}

	/** The module's own stylesheet. */
	private function onModuleHeaderStylesheet(string &$moduleHeader): void {
		$this->includeStylesheet('imageMeta.css', $moduleHeader);
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
	public function onRenderAttachmentWidget(array &$widgetArray, array &$attachment): void {
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
			$queryParameters = http_build_query(
				[
					'postUid' => $attachment['postUid'],
					'fileId' => $attachment['fileId']
				]
			);

			$widgetArray[] = $this->buildWidgetEntry($this->modulePageUrl . $queryParameters, 'exif', 'EXIF', '');
		}

		// ImgOps reverse search
		if ($this->enable_imgops && !$isNotAReverseSearchableImage) {
			$widgetArray[] = $this->buildWidgetEntry('http://imgops.com/' . getAttachmentUrl($attachment), 'imgops', 'ImgOps', '', ['target' => '_blank']);
		}

		// IQDB search
		if ($this->enable_iqdb && !$isNotAReverseSearchableImage) {
			$widgetArray[] = $this->buildWidgetEntry('http://iqdb.org/?url=' . getAttachmentUrl($attachment), 'iqdb', 'iqdb', '', ['target' => '_blank']);
		}

		// SWFChan archive
		if ($this->enable_swfchan && $isSwf) {
			$constructedAttachment = constructAttachment(
				$attachment['fileId'],
				$attachment['postUid'],
				$attachment['boardUID'],
				$attachment['fileName'],
				$attachment['storedFileName'],
				$attachment['fileExtension'],
				$attachment['fileMd5'],
				$attachment['fileWidth'],
				$attachment['fileHeight'],
				$attachment['thumbWidth'],
				$attachment['thumbHeight'],
				$attachment['fileSize'],
				$attachment['mimeType'],
				(bool)$attachment['isHidden'],
				$attachment['isDeleted'],
				$attachment['timestampAdded']
			);

			$attachmentPath = $constructedAttachment->getPath();
			$rawByteFileSize = filesize($attachmentPath);

			$widgetArray[] = $this->buildWidgetEntry('http://eye.swfchan.com/search/?q=>' . $rawByteFileSize, 'swfchan', 'swfchan', '', ['target' => '_blank']);
		}
	}

	/**
	 * Module page for displaying EXIF data of attachments.
	 * Handles multiple attachments by reading requested file.
	 */
	public function ModulePage() {
		// look everything up before drawing, so a bad link gets an error page instead of half a page
		$postUid = (int)$this->moduleContext->request->getParameter('postUid', 'GET');
		$fileId = (int)$this->moduleContext->request->getParameter('fileId', 'GET');
		$post = $postUid > 0 ? $this->moduleContext->postRepository->getPostByUid($postUid) : false;
		if (!$post) {
			throw new BoardException(_T('post_not_found'), 404);
		}

		$attachment = $fileId > 0 ? $post->getAttachmentById($fileId) : null;
		if (!$attachment) {
			throw new BoardException(_T('attachment_not_found'), 404);
		}

		$board = searchBoardArrayForBoard($post->getBoardUID());

		echo $this->moduleContext->board->getBoardHead('Image meta');

		echo '[<a href="' . sanitizeStr((string)$this->getConfig('STATIC_INDEX_FILE')) . '">Return</a>]';
		echo '<ul class="exifInfoList">';

		if (attachmentFileExists($attachment)) {
			$pfile = $board->getBoardUploadedFilesDirectory() . $board->getConfigValue('IMG_DIR') . $attachment['storedFileName'] . '.' . $attachment['fileExtension'];

			if (function_exists("exif_read_data")) {
				echo "<li>DEBUG: Using exif_read_data()</li>";
				$exif_data = exif_read_data($pfile, 0, true);

				if (is_array($exif_data) && count($exif_data)) {
					echo '<li>Image contains EXIF data:</li>';
					echo '</ul><div class="tableViewportWrapper"><table class="exif postlists"><tbody>';
					foreach ($exif_data as $key => $section) {
						foreach ($section as $name => $value) {
							$safeKey   = sanitizeStr((string)$key);
							$safeName  = sanitizeStr((string)$name);
							$safeValue = sanitizeStr(is_scalar($value) ? (string)$value : print_r($value, true));
							echo "<tr><th>{$safeKey}.{$safeName}</th><td>{$safeValue}</td></tr>";
						}
					}
					echo '</tbody></table></div><p>';
				} else {
					echo '<li>No EXIF data found.</li>';
				}
			} else {
				echo "<li>DEBUG: Using built-in exif library</li>";
				$exif = new exif($pfile);
				if (count($exif->exif_data)) {
					echo '<li>Image contains EXIF data:</li>';
					echo '</ul><div class="tableViewportWrapper"><table border="1"><tbody>';
					foreach ($exif->exif_data as $key => $value) {
						$safeKey   = sanitizeStr((string)$key);
						$safeValue = sanitizeStr(is_scalar($value) ? (string)$value : print_r($value, true));
						echo "<tr><th>{$safeKey}</th><td>{$safeValue}</td></tr>";
					}
					echo '</tbody></table></div><p>';
				} else {
					echo '<li>No EXIF data found.</li>';
				}
			}
		} else {
			echo '<li><strong class="error">File Not Found!</strong></li>';
		}

		$referer = $this->moduleContext->request->getReferer();
		if ($referer !== '') {
			echo '[<a href="' . sanitizeStr($referer) . '" onclick="event.preventDefault();history.go(-1);">Back</a>]';
		}

		echo $this->moduleContext->board->getBoardFooter();
	}
}
