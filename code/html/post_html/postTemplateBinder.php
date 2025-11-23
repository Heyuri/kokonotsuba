<?php

class postTemplateBinder {
    public function __construct(
        private board $board,
        private array $config
    ) {}

    /**
	 * Renders a reply post by merging template values with reply-specific data.
	 * 
	 * Prepares the data structure for a non-OP post (a reply) using the reply template binding function.
	 * Adds name, quote, image, and comment information to the template.
	 */
	public function renderReplyPost(array $data,
		string $crossLink,
		bool $postPositionEnabled,
		array $templateValues,
		int $threadResno,
		string $nameHtml,
		string $categoryHTML,
		string $quoteButton,
		?string $attachmentHtml,
		string $warnBeKill,
		string $postFormExtra
	): array {
		return array_merge(
			$templateValues,
			bindReplyValuesToTemplate(
				$this->board,
				$this->config,
				$crossLink,
				$data['post_uid'],
				$data['no'],
				$threadResno,
				$postPositionEnabled,
				$data['post_position'],
				$data['sub'],
				$nameHtml,
				$data['now'],
				$categoryHTML,
				$quoteButton,
				$attachmentHtml, 
				$warnBeKill,
				$data['com'],
				$postFormExtra
			)
		);
	}

	/**
	 * Renders an original post (OP) by merging template values with thread-starting post data.
	 * 
	 * This handles the layout and logic specific to OPs including reply count, warnings,
	 * thread navigation, and extended image information.
	 */
	public function renderOpPost(array $data,
		?array $fileData,
		string $crossLink,
		array $templateValues,
		string $nameHtml,
		string $categoryHTML,
		string $quoteButton,
		?string $attachmentHtml,
		?string $attachmentUrl,
		string $replyButton,
		string $postFormExtra,
		int $replyCount,
		string $warnOld,
		string $warnBeKill,
		string $warnEndReply,
		string $warnHidePost,
	): array {
		return array_merge(
			$templateValues,
			bindOPValuesToTemplate(
				$this->board,
				$this->config,
				$crossLink,
				$data['post_uid'],
				$data['no'],
				$data['sub'],
				$nameHtml,
				$data['now'],
				$categoryHTML,
				$quoteButton,
				$attachmentHtml,
				$attachmentUrl,
				$replyButton,
				$fileData['fileName'] ?? '',
				$fileData['fileExtension'] ?? '',
				isset($fileData['fileSize']) ? formatFileSize($fileData['fileSize']) : '',
				$fileData['fileWidth'] ?? 0,
				$fileData['fileHeight'] ?? 0,
				$replyCount,
				$warnOld,
				$warnBeKill,
				$warnEndReply,
				$warnHidePost,
				$data['com'],
				$postFormExtra
			)
		);
	}
}