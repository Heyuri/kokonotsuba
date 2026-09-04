<?php

namespace Kokonotsuba\Modules\edit;

use Kokonotsuba\board\board;
use Kokonotsuba\error\BoardException;
use Kokonotsuba\file\postFileUploadController;
use Kokonotsuba\module_classes\moduleContext;
use Kokonotsuba\post\helper\thumbnailCreator;
use Kokonotsuba\post\Post;

use function Kokonotsuba\libraries\_T;
use function Kokonotsuba\libraries\getAttachmentUrl;
use function Kokonotsuba\libraries\getThumbnailFromFile;
use function Kokonotsuba\libraries\getUserFileFromRequest;
use function Kokonotsuba\libraries\loadUploadData;
use function Kokonotsuba\libraries\scaleThumbnail;
use function Kokonotsuba\libraries\searchBoardArrayForBoard;
use function Puchiko\strings\sanitizeStr;

/**
 * The attachment half of an edit: dropping files off a post and putting new ones on.
 *
 * Both editors run on this, so a reader and a moderator drop a file the same way. Dropping one
 * is the file-only deletion the checkbox at the bottom of the board already does - the file goes
 * to purgatory and the deleted-posts viewer can bring it back - rather than a purge, so an edit
 * is never how an attachment leaves the install for good. Adding one runs the post form's own
 * upload path, so an edit cannot slip past the size, extension and MIME checks a post is held to.
 *
 * Nothing is written until the whole edit has been checked: plan() reads the request and refuses
 * anything it does not like, commit() carries out what plan() agreed to.
 */
final class editAttachments {
	/** Form field naming the files to drop. */
	public const REMOVE_FIELD = 'removeAttachments';

	/** File input new attachments arrive on, named as the post form names it. */
	public const UPLOAD_FIELD = 'upfile';

	public function __construct(private readonly moduleContext $moduleContext) {}

	/** The board the post is on, which is not always the one this request is served from. */
	private function boardOf(Post $post): board {
		return searchBoardArrayForBoard($post->getBoardUID()) ?? $this->moduleContext->board;
	}

	/** Whether this post's board takes attachments at all. */
	public function enabledFor(Post $post): bool {
		return !$this->boardOf($post)->getConfigValue('TEXTBOARD_ONLY', false);
	}

	/** How many attachments one post may carry. */
	public function uploadLimit(Post $post): int {
		return max(1, (int)$this->boardOf($post)->getConfigValue('ATTACHMENT_UPLOAD_LIMIT', 1));
	}

	/** The files still on the post, keyed by file id. */
	public function current(Post $post): array {
		return array_filter(
			$post->getAttachments(),
			static fn(?array $attachment) => $attachment && empty($attachment['isDeleted'])
		);
	}

	/**
	 * What the edit window needs to draw the attachment list it clones a blank one of.
	 *
	 * @return array<string, mixed>
	 */
	public function payload(Post $post): array {
		$files = [];

		foreach ($this->current($post) as $attachment) {
			$files[] = [
				'fileId' => (int)$attachment['fileId'],
				'name' => $this->displayName($attachment),
				'url' => getAttachmentUrl($attachment),
				'thumbnailUrl' => getAttachmentUrl($attachment, true),
			];
		}

		return [
			'attachments' => $files,
			'attachmentLimit' => $this->uploadLimit($post),
			'canEditAttachments' => $this->enabledFor($post),
		];
	}

	/** The attachment list as the plain form page shows it, one removable entry per file. */
	public function renderList(Post $post): string {
		$html = '';

		foreach ($this->current($post) as $attachment) {
			$thumbnailUrl = getAttachmentUrl($attachment, true);

			$html .= '<label class="editAttachmentEntry">'
				. '<input type="checkbox" name="' . self::REMOVE_FIELD . '[]" value="' . (int)$attachment['fileId'] . '">'
				. ($thumbnailUrl ? '<img class="editAttachmentThumb" src="' . sanitizeStr($thumbnailUrl) . '" alt="">' : '')
				. '<span class="editAttachmentName">' . sanitizeStr($this->displayName($attachment)) . '</span>'
				. '</label>';
		}

		return $html;
	}

	/**
	 * Read the request and work out what the edit does to the post's files.
	 *
	 * Everything that can be refused is refused here, before the post itself is touched: a file
	 * that is not this post's, more files than the board allows, an upload the post form would
	 * have turned away, and an OP left with nothing on a board that requires one.
	 *
	 * @return array{remove: array[], uploads: array[], remaining: int} remaining counts the files
	 *         the post ends up with.
	 */
	public function plan(Post $post): array {
		$current = $this->current($post);

		if (!$this->enabledFor($post)) {
			return ['remove' => [], 'uploads' => [], 'remaining' => count($current)];
		}

		$remove = $this->resolveRemovals($post, $current);
		$kept = count($current) - count($remove);

		$uploads = $this->collectUploads($post, $this->uploadLimit($post) - $kept);
		$remaining = $kept + count($uploads);

		// A thread that has to open with a file may not be edited down to none.
		if ($post->isOp()
			&& $remaining === 0
			&& count($current) > 0
			&& $this->boardOf($post)->getConfigValue('THREAD_ATTACHMENT_REQUIRED', true)) {
			throw new BoardException(_T('edit_attachment_op_required'), 400);
		}

		return ['remove' => $remove, 'uploads' => $uploads, 'remaining' => $remaining];
	}

	/** Whether a plan actually does anything. */
	public function planChangesAnything(array $plan): bool {
		return $plan['remove'] !== [] || $plan['uploads'] !== [];
	}

	/**
	 * Carry out a plan.
	 *
	 * @param array    $plan      What plan() agreed to.
	 * @param int|null $accountId Staff account behind the edit, null when a reader made it.
	 */
	public function commit(Post $post, array $plan, ?int $accountId): void {
		if ($plan['remove']) {
			$this->moduleContext->deletedPostsService->deleteFilesFromPosts(array_values($plan['remove']), $accountId);
		}

		foreach ($plan['uploads'] as $upload) {
			$upload['controller']->savePostThumbnailToBoard();
			$upload['controller']->savePostFileToBoard();

			$this->moduleContext->fileService->addFile(
				$post->getUid(),
				$upload['fileName'],
				$upload['storedFileName'],
				$upload['ext'],
				$upload['md5'],
				$upload['imgW'],
				$upload['imgH'],
				$upload['thumbWidth'],
				$upload['thumbHeight'],
				$upload['fileSize'],
				$upload['mimeType'],
				false
			);
		}
	}

	/**
	 * The attachments the form asked to drop, as their rows.
	 *
	 * Only ids the post actually carries are honoured, so a hand-written id cannot reach another
	 * post's files.
	 *
	 * @return array[] Attachment rows, keyed by file id.
	 */
	private function resolveRemovals(Post $post, array $current): array {
		$requested = $this->moduleContext->request->getParameter(self::REMOVE_FIELD, 'POST', []);

		if (!is_array($requested)) {
			$requested = [$requested];
		}

		$remove = [];

		foreach ($requested as $fileId) {
			$fileId = (int)$fileId;

			foreach ($current as $attachment) {
				if ((int)$attachment['fileId'] === $fileId) {
					$remove[$fileId] = $attachment;
				}
			}
		}

		return $remove;
	}

	/**
	 * Validate the uploaded files and get them ready to save.
	 *
	 * Nothing is written to disk here: a file that fails validation must not leave a half-added
	 * attachment behind, so saving waits until the whole edit is agreed.
	 *
	 * @param int $slots How many more files the post has room for.
	 * @return array[] One entry per accepted upload.
	 */
	private function collectUploads(Post $post, int $slots): array {
		$upload = $this->moduleContext->request->getFile(self::UPLOAD_FIELD);

		if (!isset($upload['tmp_name']) || !is_array($upload['tmp_name'])) {
			return [];
		}

		$offered = count(array_filter($upload['tmp_name']));

		if ($offered === 0) {
			return [];
		}

		if ($slots <= 0 || $offered > $slots) {
			throw new BoardException(_T('edit_attachment_toomany', $this->uploadLimit($post)), 400);
		}

		$board = $this->boardOf($post);
		$config = $this->uploadConfigFor($board);
		$uploadDirectory = $board->getBoardUploadedFilesDirectory() . $board->getConfigValue('IMG_DIR');

		$thumbnailCreator = new thumbnailCreator(
			$board->getConfigValue('USE_THUMB'),
			$board->getConfigValue('THUMB_SETTING'),
			$board->getBoardUploadedFilesDirectory() . $board->getConfigValue('THUMB_DIR')
		);

		$uploads = [];

		for ($index = 0; $index < count($upload['tmp_name']); $index++) {
			[$temporaryName, $fileName, $status] = loadUploadData(self::UPLOAD_FIELD, $index, $this->moduleContext->request);

			if ($status === UPLOAD_ERR_NO_FILE || !$temporaryName) {
				continue;
			}

			$fileFromUpload = getUserFileFromRequest($temporaryName, $fileName, $status, $index, $this->moduleContext->request);
			$file = $fileFromUpload->getFile();

			$thumbnail = scaleThumbnail(
				getThumbnailFromFile($file),
				!$post->isOp(),
				$config['MAX_RW'],
				$config['MAX_RH'],
				$config['MAX_W'],
				$config['MAX_H']
			);

			$controller = new postFileUploadController(
				$config,
				$fileFromUpload,
				$thumbnailCreator,
				$thumbnail,
				$uploadDirectory,
				$offered
			);

			$controller->validateFile();

			// The stored name is the upload's own millisecond timestamp, so a file added by an
			// edit can never land on the name of one the post was made with.
			$storedFileName = (string)$file->getTimeInMilliseconds();
			if ($offered > 1) {
				$storedFileName .= '_' . $index;
			}

			$uploads[] = [
				'controller' => $controller,
				'storedFileName' => $storedFileName,
				'fileName' => $file->getFileName(),
				'ext' => $file->getExtention(),
				'md5' => $file->getMd5Chksum(),
				'imgW' => $file->getImageWidth(),
				'imgH' => $file->getImageHeight(),
				'thumbWidth' => $thumbnail->getThumbnailWidth(),
				'thumbHeight' => $thumbnail->getThumbnailHeight(),
				'fileSize' => $file->getFileSize(),
				'mimeType' => $file->getMimeType(),
			];
		}

		return $uploads;
	}

	/**
	 * The upload rules as the post's own board writes them.
	 *
	 * Staff edit posts from lists covering every board, so the request's board is not necessarily
	 * the one whose size and file-type limits apply. Everything else falls back to the config
	 * this request was served with.
	 */
	private function uploadConfigFor(board $board): array {
		$config = $this->moduleContext->config;

		foreach (['THUMB_SETTING', 'VIDEO_EXT', 'ALLOW_UPLOAD_EXT', 'MAX_KB', 'MAX_W', 'MAX_H', 'MAX_RW', 'MAX_RH'] as $key) {
			$config[$key] = $board->getConfigValue($key, $config[$key] ?? null);
		}

		return $config;
	}

	/** What a file is called in the list: the name it is stored under, as staff see it elsewhere. */
	private function displayName(array $attachment): string {
		return $attachment['storedFileName'] . '.' . $attachment['fileExtension'];
	}
}
