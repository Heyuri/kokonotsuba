<?php

namespace Kokonotsuba\Modules\imageServer;

use Kokonotsuba\post\attachment\attachment;
use Kokonotsuba\error\BoardException;
use Kokonotsuba\module_classes\abstractModuleMain;
use Kokonotsuba\module_classes\listeners\ImageUrlListenerTrait;

use function Kokonotsuba\libraries\isActiveStaffSession;
use function Kokonotsuba\libraries\serveMedia;

class moduleMain extends abstractModuleMain {
	use ImageUrlListenerTrait;

	public function getName(): string {
		return 'Kokonotsuba File Server';
	}

	public function getVersion(): string {
		return 'Koko 2025';
	}

	public function initialize(): void {
		$this->listenImageUrl('onRenderImageUrl');
	}

	private function onRenderImageUrl(string &$imageURL, int $fileId, bool $isThumb): void {
		// generate image url
		$imageURL = $this->generateImageUrl($fileId, $isThumb);
	}

	private function generateImageUrl(int $fileId, bool $isThumb): string {
		// url parameters
		$parameters = [
			'file' => $fileId,
			'isThumb' => $isThumb
		];

		// get module file url with file paramter
		$fileUrl = $this->getModulePageURL($parameters, false);

		// return url
		return $fileUrl;
	}

	public function ModulePage() {
		// file id of the file row
		$fileId = $this->moduleContext->request->getParameter('file', 'GET');

		// cast to int
		$fileId = (int)$fileId;

		// throw error if no file id is selected
		if($fileId === null || !is_int($fileId)) {
			throw new BoardException("No file id selected");
		}

		// get file info
		$attachment = $this->moduleContext->fileService->getAttachment($fileId);

		// throw error if the file wasn't found
		if($attachment === null) {
			throw new BoardException("File not found!", 404);
		}

		// authentication logic
		$this->authenticateUserForAttachment($attachment);

		// whether we're accessing the thumbnail via GET
		$isThumb = $this->moduleContext->request->getParameter('isThumb', 'GET', false);

		// cast to bool
		$isThumb = (bool)$isThumb;

		// get the raw file path
		$fullPath = $attachment->getPath($isThumb);

		// run it through realpath to prevent path traversal
		$fullPath = realpath($fullPath);

		// serve the media
		serveMedia($fullPath); 
	}

	private function authenticateUserForAttachment(attachment $attachment): void {
		// whether the user is a staff user
		$isStaff = isActiveStaffSession();

		// whether its a hidden attachment
		$isHidden = $attachment->isHidden();

		// throw error if the attachment is hidden and the user isn't staff
		if($isHidden && $isStaff === false) {
			throw new BoardException("You cannot access this attachment!");
		}
	}
}
