<?php

class attachmentService {
	public function __construct(
		private readonly attachmentRepository $attachmentRepository, 
	) {}

	/* Check if an attachment is duplicated */
	public function isDuplicateAttachment($board, $md5hash) {
		$results = $this->attachmentRepository->getAttachmentsByMd5($board->getBoardUID(), $md5hash);

		$FileIO = PMCLibrary::getFileIOInstance();
		foreach ($results as $row) {
			$filename = $row['tim'] . $row['ext'];
			if ($FileIO->imageExists($filename, $board)) {
				return true; // Duplicate found
			}
		}
		return false;
	}

	/* Delete old attachments */
	public function delOldAttachments(IBoard $board, int $total_size, int $storage_max, bool $warnOnly = true) {
		$FileIO = PMCLibrary::getFileIOInstance();
		$results = $this->attachmentRepository->getAllAttachments();

		$arr_warn = [];
		$arr_kill = [];

		foreach ($results as $row) {
			$dfile = $row['tim'] . $row['ext'];
			$dthumb = $FileIO->resolveThumbName($row['tim'], $board);

			if ($FileIO->imageExists($dfile, $board)) {
				$total_size -= $FileIO->getImageFilesize($dfile, $board) / 1024;
				$arr_kill[] = $row['no'];
				$arr_warn[$row['no']] = 1;
			}
			if ($dthumb && $FileIO->imageExists($dthumb, $board)) {
				$total_size -= $FileIO->getImageFilesize($dthumb, $board) / 1024;
			}

			if ($total_size < $storage_max) break;
		}

		return $warnOnly ? $arr_warn : $this->removeAttachments($arr_kill);
	}

	/* Delete attachments */
	public function removeAttachments(array $posts, bool $recursion = false) {
		if (empty($posts)) return [];

		$records = $this->attachmentRepository->getAttachmentRecords($posts, $recursion);

		$this->deleteAttachments($records);
	}

	public function removeAttachmentsFromThreads(array $threadUids): void {
		$threadAttachments = $this->attachmentRepository->getAttachmentsFromThreads($threadUids);
			
		$this->deleteAttachments($threadAttachments);
	}


	private function deleteAttachments(array $records): void {
		$FileIO = PMCLibrary::getFileIOInstance();

		foreach ($records as $row) {
			$board = searchBoardArrayForBoard($row['boardUID']);

			$dfile = $row['tim'] . $row['ext'];
			$dthumb = $FileIO->resolveThumbName($row['tim'], $board);
			
			if ($FileIO->imageExists($dfile, $board)) {
				$FileIO->deleteImage($dfile, $board);
			}

			if($dthumb && $FileIO->imageExists($dthumb, $board)) {
				$FileIO->deleteImage($dthumb, $board);
			}
		}
	}
}
