<?php

class postDataPreparer {
    public function __construct(
        private board $board,
		private mixed $FileIO
    ) {}

	 /**
	 * Sanitize and format post data for rendering using associative arrays.
	 */
	public function preparePostData(array $post): array {
		// Basic fields
		$email = isset($post['email']) ? trim($post['email']) : '';
		$name = $post['name'] ?? '';
		$tripcode = $post['tripcode'] ?? '';
		$secure_tripcode = $post['secure_tripcode'] ?? '';
		$capcode = $post['capcode'] ?? '';
		$now = $post['now'] ?? '';
		$com = $post['com'] ?? '';
		$tim = $post['tim'] ?? '';
		$open_flag = $post['open_flag'] ?? 0;
		$file_only_deleted = $post['file_only_deleted'] ?? 0;
		$status = new FlagHelper($post['status']);
		
	
		// Mailto formatting
		if ($this->board->getConfigValue('CLEAR_SAGE')) {
			$email = preg_replace('/^sage( *)/i', '', $email);
		}
		if ($this->board->getConfigValue('ALLOW_NONAME') == 2 && $email) {
			$now = "<a href=\"mailto:$email\">$now</a>";
		}

		// get the file data
		$fileData = $this->getFilePropertiesFromData($post);

		// Return everything needed
		return [
			'email' => $email,
			'name' => $name,
			'open_flag' => $open_flag,
			'file_only_deleted' => $file_only_deleted,
			'tripcode' => $tripcode,
			'secure_tripcode' => $secure_tripcode,
			'capcode' => $capcode,
			'now' => $now,
			'com' => $com,
			'no' => $post['no'],
			'is_op' => $post['is_op'],
			'post_position' => $post['post_position'],
			'sub' => $post['sub'],
			'status' => $status,
			'tim' => $tim,
			'category' => $post['category'],
			'post_uid' => $post['post_uid'],
			'boardUID' => $post['boardUID'],
			// file
			'fileData' => $fileData
		];
	}

	private function getFilePropertiesFromData(array $data): ?array {
		// thumbnail extension
		$thumbnailExtension = $this->board->getConfigValue('THUMB_SETTING.Format');

		// init post uid
		$postUid = $data['post_uid'];

		// init board uid
		$boardUID = $data['boardUID'];

		// if it has an attachment from the files table then render it accordingly
		if(!empty($data['file_id'])) {
			// md5 file hash
			$fileMd5 = $data['file_md5'];

			// file name on disk
			$storedFileName = $data['stored_filename'];
			
			// file extension
			$fileExtension = $data['file_ext'];

			// file name
			$fileName = $data['file_name'];

			// file size
			$fileSize = (string) $data['file_size'] . ' KB';

			// file width
			$fileWidth = $data['file_width'];

			// file height
			$fileHeight = $data['file_height'];

			// thumbnail width
			$thumbnailWidth = $data['thumb_file_width'] ?? 0;

			// thumbnail height
			$thumbnailHeight = $data['thumb_file_height'] ?? 0;

			// thumbnail file name
			$thumbnailStoredFileName = $storedFileName . 's';

			// Get thumbnail name
			$thumbName = $thumbnailStoredFileName . '.' . $thumbnailExtension;

			// file id
			$fileId = $data['file_id'] ?? 0;

			// is hidden
			$isHidden = $data['main_is_hidden'] ?? false;

			// this is using the new and improved file system
			$isLegacy = false;
		} 
		// Legacy post row file system + values
		else if(!empty($data['ext'])) {
			// md5 file hash
			$fileMd5 = $data['md5chksum'];

			// file name on disk
			$storedFileName = $data['tim'];
			
			// file extension
			$fileExtension = $data['ext'];

			// file name
			$fileName = $data['fname'];

			// file size
			$fileSize = $data['imgsize'];

			// file width
			$fileWidth = $data['imgw'];

			// file height
			$fileHeight = $data['imgh'];

			// thumbnail width
			$thumbnailWidth = $data['tw'] ?? 0;

			// thumbnail height
			$thumbnailHeight = $data['th'] ?? 0;

			// thumbnail file name
			$thumbnailStoredFileName = $data['tim'] . 's';

			// Get thumbnail name
			$thumbName = $this->FileIO->resolveThumbName($storedFileName, $this->board);

			// is hidden
			$isHidden = false;

			// file id
			$fileId = 0;

			// this is using the legacy post file stuff
			$isLegacy = true;
		} 
		// there is no attachments on this post
		else {
			// return null
			return null;
		}

		// then return the file data,
		// either above outcomes will be rendered with the same array keys
		return [
			'storedFileName' => $storedFileName,
			'fileExtension' => $fileExtension,
			'fileName' => $fileName,
			'fileSize' => $fileSize,
			'fileWidth' => $fileWidth,
			'fileHeight' => $fileHeight,
			'fileMd5' => $fileMd5,
			'thumbnailWidth' => $thumbnailWidth,
			'thumbnailHeight' => $thumbnailHeight,
			'thumbnailStoredFileName' => $thumbnailStoredFileName,
			'thumbnailExtension' => $thumbnailExtension,
			'thumbName' => $thumbName,
			'fileId' => $fileId,
			'isHidden' => $isHidden,
			'mimeType' => '',// temp blanl
			'isLegacy' => $isLegacy,
			'postUid' => $postUid,
			'boardUID' => $boardUID,
		];
	}
}