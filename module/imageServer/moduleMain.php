<?php

namespace Kokonotsuba\Modules\imageServer;

use attachment;
use BoardException;
use Kokonotsuba\ModuleClasses\abstractModuleMain;

class moduleMain extends abstractModuleMain {

	public function getName(): string {
		return 'Kokonotsuba File Server';
	}

	public function getVersion(): string {
		return 'Koko 2025';
	}

	public function initialize(): void {
		$this->moduleContext->moduleEngine->addListener('ImageUrl', function (string &$imageURL, int $fileId, bool $isThumb) {
			$this->onRenderImageUrl($imageURL, $fileId, $isThumb);
		});
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
		$fileId = (int)$_GET['file'] ?? null;

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

		// get the raw file path
		$fullPath = $attachment->getPath();

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
