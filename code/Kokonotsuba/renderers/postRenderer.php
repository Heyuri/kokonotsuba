<?php
/*
* Post renderer for Kokonotsuba!
* Handles post html output
*/

namespace Kokonotsuba\renderers;

use Kokonotsuba\renderers\attachmentRenderer;
use Kokonotsuba\renderers\postDataPreparer;
use Kokonotsuba\renderers\postElementGenerator;
use Kokonotsuba\renderers\postTemplateBinder;
use Kokonotsuba\renderers\postWidget;
use Kokonotsuba\interfaces\IBoard;
use Kokonotsuba\module_classes\moduleEngine;
use Kokonotsuba\post\Post;
use Kokonotsuba\request\request;
use Kokonotsuba\template\templateEngine;

use function Kokonotsuba\libraries\_T;
use function Kokonotsuba\libraries\html\generateQuoteLinkHtml;
use function Kokonotsuba\libraries\html\quote_unkfunc;
use function Kokonotsuba\libraries\html\generatePostNameHtml;
use function Kokonotsuba\libraries\html\getPageForPostPosition;
use function Puchiko\strings\sanitizeStr;

class postRenderer {
	private postDataPreparer $postDataPreparer;
	private attachmentRenderer $attachmentRenderer;
	private postTemplateBinder $postTemplateBinder;
	private postElementGenerator $postElementGenerator;
	private postWidget $postWidget;

	public function __construct(
		private readonly IBoard $board, 
		private readonly array $config, 
		private readonly moduleEngine $moduleEngine, 
		private readonly templateEngine $templateEngine,
		private array $quoteLinksFromBoard,
		private readonly request $request) {
			// initialize post data preperation class
			$this->postDataPreparer = new postDataPreparer($board);

			// initialize attachment rendering class
			$this->attachmentRenderer = new attachmentRenderer($board, $moduleEngine, $templateEngine);

			// intialize post template binding class
			$this->postTemplateBinder = new postTemplateBinder($board, $config);

			// intialize post element generator class
			$this->postElementGenerator = new postElementGenerator($board);
			
			// initialize post widget generator class
			$this->postWidget = new postWidget($moduleEngine);
		}

	public function setQuoteLinks(array $quoteLinks): void {
		// update the quote links property
		$this->quoteLinksFromBoard = $quoteLinks;
	}

	public function render(
		Post $post,
		array &$templateValues,
		int $threadResno,
		bool $killSensor,
		array $threadPosts,
		bool $adminMode,
		string $postFormExtra,
		string $warnHidePost,
		int $replyCount,
		bool $threadMode = true,
		string $crossLink = '',
		bool $renderAsOp = false
	) {
		// Prepare post data
		$data = $this->postDataPreparer->preparePostData($post);

		// Define if it's the thread's OP or a reply
		$isThreadOp = $data->isOp();
		$isThreadReply = !$isThreadOp;
		$shouldRenderReply = $isThreadReply && !$renderAsOp;

		// get replies per page value
		$repliesPerPage = $this->board->getConfigValue('REPLIES_PER_PAGE', 200);

		// Prepare post content (quote links, category, attachments)
		$contentData = $this->preparePostContent($data, $threadResno, $repliesPerPage, $replyCount, $crossLink, $adminMode);

		// Generate warning messages
		$warnings = $this->prepareWarnings($killSensor, $isThreadOp, $post);

		$templateValues['{$POSTINFO_EXTRA}'] = '';

		// Admin controls and widgets
		$postFormExtra .= $this->generateAdminControls($post, $adminMode, $isThreadOp);
		$widgetDataHtml = $this->generateAdminWidgets($post, $threadPosts, $adminMode, $isThreadOp);

		// Generate post metadata (name HTML, buttons, URLs, attributes, first attachment)
		$metadata = $this->generatePostMetadata($data, $threadResno, $replyCount, $repliesPerPage, $threadMode, $crossLink, $contentData['isDeleted']);

		// Bind core template values
		$templateValues['{$POST_URL}'] = $metadata['postUrl'];
		$templateValues['{$DATA_ATTRIBUTES}'] = $metadata['dataAttributes'];

		// Bind OP or reply template values
		$this->bindPostTemplateValues(
			$templateValues, $data, $metadata, $contentData,
			$warnings, $shouldRenderReply, $isThreadOp, $renderAsOp,
			$crossLink, $threadResno, $postFormExtra, $replyCount,
			$warnHidePost
		);

		// Dispatch module events and finalize post menu
		$this->dispatchPostModuleEvents($templateValues, $data, $post, $threadPosts, $adminMode, $isThreadOp, $isThreadReply);
		$this->finalizePostWidgets($templateValues, $post, $threadPosts, $isThreadOp, $isThreadReply, $widgetDataHtml);
		
		// Return rendered HTML
		if ($shouldRenderReply) {
			return $this->templateEngine->ParseBlock('REPLY', $templateValues);
		} else {
			return $this->templateEngine->ParseBlock('OP', $templateValues);
		}
	}

	private function preparePostContent($data, int $threadResno, int $repliesPerPage, int $replyCount, string $crossLink, bool $adminMode): array {
		// Apply quote and quote link processing
		$this->applyCommentQuoteLinks($data, $threadResno, $repliesPerPage, $replyCount);

		// Process category links
		$categoryHTML = $this->postElementGenerator->processCategoryLinks($data->getCategory(), $crossLink);

		// this post is deleted
		$isDeleted = $data->getOpenFlag() && !$data->isFileOnlyDeleted() && $adminMode;

		// process attachment data
		$attachmentsHtml = $this->processAttachments(
			$data->getAttachments(),
			$isDeleted,
			$adminMode
		);

		return [
			'postPositionEnabled' => $this->config['RENDER_REPLY_NUMBER'],
			'categoryHTML' => $categoryHTML,
			'isDeleted' => $isDeleted,
			'attachmentsHtml' => $attachmentsHtml,
		];
	}

	private function bindPostTemplateValues(
		array &$templateValues,
		$data,
		array $metadata,
		array $contentData,
		array $warnings,
		bool $shouldRenderReply,
		bool $isThreadOp,
		bool $renderAsOp,
		string $crossLink,
		int $threadResno,
		string $postFormExtra,
		int $replyCount,
		string $warnHidePost
	): void {
		$shouldRenderOp = $isThreadOp || $renderAsOp;

		if ($shouldRenderReply) {
			$templateValues = $this->postTemplateBinder->renderReplyPost(
				$data, 
				$crossLink, 
				$contentData['postPositionEnabled'], 
				$templateValues, 
				$threadResno,
				$metadata['nameHtml'], 
				$contentData['categoryHTML'],
				$metadata['quoteButton'], 
				$contentData['attachmentsHtml'], 
				$warnings['warnBeKill'], 
				$postFormExtra, 
			);
		} elseif ($shouldRenderOp) {
			$templateValues = $this->postTemplateBinder->renderOpPost(
				$data, 
				$metadata['firstAttachment'],
				$crossLink,
				$templateValues, 
				$metadata['nameHtml'], 
				$contentData['categoryHTML'], 
				$metadata['quoteButton'], 
				$contentData['attachmentsHtml'],
				$metadata['firstAttachmentUrl'],
				$metadata['replyButton'], 
				$metadata['recentRepliesButton'],
				$postFormExtra, 
				$replyCount, 
				$warnings['warnOld'], 
				$warnings['warnBeKill'], 
				'', 
				$warnHidePost,
			);
		}
	}

	private function applyCommentQuoteLinks($data, int $threadResno, int $repliesPerPage, int $replyCount): void {
		$data->setComment(generateQuoteLinkHtml(
			$this->quoteLinksFromBoard, $data, $threadResno,
			$this->board->getConfigValue('USE_QUOTESYSTEM'),
			$this->board, $repliesPerPage, $replyCount
		));
		$data->setComment(quote_unkfunc($data->getComment()));
	}

	private function prepareWarnings(bool $killSensor, bool $isThreadOp, Post $post): array {
		$warnBeKill = '';
		if ($this->config['STORAGE_LIMIT'] && $killSensor) {
			$warnBeKill = '<div class="warning">'._T('warn_sizelimit').'</div>';
		}

		$warnOld = '';
		if ($isThreadOp) {
			$maxAgeLimit = $this->config['MAX_AGE_TIME'];
			$postUnixTimestamp = is_numeric($post->getRoot()) ? $post->getRoot() : strtotime($post->getRoot());
			if ($maxAgeLimit && $this->request->getRequestTime() - $postUnixTimestamp > ($maxAgeLimit * 60 * 60)) {
				$warnOld = "<div class='warning'>"._T('warn_oldthread')."</div>";
			}
		}

		return ['warnBeKill' => $warnBeKill, 'warnOld' => $warnOld];
	}

	private function generatePostMetadata($data, int $threadResno, int $replyCount, int $repliesPerPage, bool $threadMode, string $crossLink, bool $isDeleted): array {
		$nameHtml = generatePostNameHtml(
			$this->moduleEngine,
			$data->getName(),
			$data->getTripcode(),
			$data->getSecureTripcode(),
			$data->getCapcode(),
			$data->getEmail(),
			$this->config['NOTICE_SAGE']
		);

		$totalThreadPages = getPageForPostPosition($replyCount, $repliesPerPage);
		$lastPage = $totalThreadPages;

		$quoteButton = $this->postElementGenerator->generateQuoteButton($threadResno, $data->getNumber(), $lastPage, $crossLink);
		$replyButton = $threadMode ? $this->postElementGenerator->generateReplyButton($crossLink, $threadResno, $lastPage) : '';
		$recentRepliesButton = $threadMode ? $this->postElementGenerator->generateRecentRepliesButton($crossLink, $threadResno, $replyCount) : '';

		$page = getPageForPostPosition($data->getPostPosition(), $repliesPerPage);
		$postUrl = $this->board->getBoardThreadURL($threadResno, $data->getNumber(), false, $page, $crossLink);

		$dataAttributes = 'data-post-email="' . sanitizeStr($data->getEmail()) . '" data-post-user-name="' . sanitizeStr($data->getName()) . '" data-post-number="' . $data->getNumber() . '" data-post-uid="' . sanitizeStr($data->getUid()) . '"';

		$attachments = $data->getAttachments();
		$firstAttachmentArrKey = array_key_first($attachments);
		$firstAttachment = $attachments[$firstAttachmentArrKey] ?? [];
		$firstAttachmentUrl = $this->attachmentRenderer->generateImageUrl($firstAttachment, false, $isDeleted);

		return [
			'nameHtml' => $nameHtml,
			'quoteButton' => $quoteButton,
			'replyButton' => $replyButton,
			'recentRepliesButton' => $recentRepliesButton,
			'postUrl' => $postUrl,
			'dataAttributes' => $dataAttributes,
			'firstAttachment' => $firstAttachment,
			'firstAttachmentUrl' => $firstAttachmentUrl,
		];
	}

	private function dispatchPostModuleEvents(array &$templateValues, $data, Post $post, array $threadPosts, bool $adminMode, bool $isThreadOp, bool $isThreadReply): void {
		$board = $this->board;
		$this->moduleEngine->dispatch('Post', [
			&$templateValues, &$data, &$threadPosts, &$board, &$adminMode,
		]);

		$this->moduleEngine->dispatch('PostComment', [
			&$templateValues['{$COM}'], &$data
		]);

		$templateValues['{$BELOW_COMMENT}'] = '';
		$this->moduleEngine->dispatch('BelowComment', [
			&$templateValues['{$BELOW_COMMENT}'], &$data, &$threadPosts, &$adminMode
		]);

		$templateValues['{$MODULE_ATTACHMENT_CSS_CLASSES}'] = '';
		$templateValues['{$MODULE_POST_CSS_CLASSES}'] = '';

		$attachmentCss = &$templateValues['{$MODULE_ATTACHMENT_CSS_CLASSES}'];
		$this->moduleEngine->dispatch('AttachmentCssClass', [
			&$attachmentCss, &$post, &$adminMode
		]);

		$postCss = &$templateValues['{$MODULE_POST_CSS_CLASSES}'];
		$this->moduleEngine->dispatch('PostCssClass', [
			&$postCss, &$post
		]);

		if ($isThreadReply) {
			$this->moduleEngine->dispatch('ThreadReply', [&$templateValues, &$post, &$threadPosts]);
		} elseif ($isThreadOp) {
			$this->moduleEngine->dispatch('OpeningPost', [&$templateValues, &$post, &$threadPosts]);
		}
	}

	private function finalizePostWidgets(array &$templateValues, Post $post, array $threadPosts, bool $isThreadOp, bool $isThreadReply, string $widgetDataHtml): void {
		if ($isThreadReply) {
			$this->postWidget->addThreadReplyWidget($widgetDataHtml, $post);
		} elseif ($isThreadOp) {
			$this->postWidget->addOpeningPostWidget($widgetDataHtml, $post, $threadPosts);
		}

		$this->postWidget->addPostWidget($widgetDataHtml, $post);
		$templateValues['{$POST_MENU}'] = $this->postWidget->generatePostMenuHtml($widgetDataHtml);
	}

	/**
	 * Process all attachments for a post and return a normalized set of
	 * imageBars, imageHtml, and imageURLs usable by template binders.
	 *
	 * @param array $attachments  List of attachments from post data
	 * @param bool $isDeleted     Whether post is deleted
	 * @param bool $adminMode     Whether viewer is admin
	 *
	 * @return string $postAttachmentsHtml
	 */
	public function processAttachments(
		array $attachments, 
		bool $isDeleted, 
		bool $adminMode
	): string {
		// return empty string of attachments is empty
		if(empty($attachments)) {
			return '';
		}

		// init variable to be used for attachment html in template
		$postAttachmentsHtml = '';

		// if the attachments array length is larger than 1 then there are multiple attachments for this post
		if(count($attachments) > 1) {
			$hasMultipleAttachments = true;
		} 
		// otherwise theres only a single attachment
		else {
			$hasMultipleAttachments = false;
		}

		// Render each attachment individually
		foreach (array_values($attachments) as $index => $att) {
			// generate the attachment html and append it to the attachments html variable
			$postAttachmentsHtml .= $this->attachmentRenderer->generateAttachmentHtml(
				$att,
				$isDeleted,
				$adminMode,
				$index,
				$hasMultipleAttachments
			);
		}

		// return attachments html
		return $postAttachmentsHtml;
	}

	private function generateAdminControls(Post $post, bool $adminMode, bool $isThreadOp): string {
		// if the user is logged in as a mod, then run thread admin controls
		if ($adminMode) {
			$modFunc = '';
			
			if($isThreadOp) {
				$this->moduleEngine->dispatch('ThreadAdminControls', [&$modFunc, &$post]);
			} else {
				$this->moduleEngine->dispatch('ReplyAdminControls', [&$modFunc, &$post]);
			}

			$this->moduleEngine->dispatch('PostAdminControls', [&$modFunc, &$post]);
			
			return $modFunc;
		}
		// otherwise, return empty string
		else {
			return '';
		}
	}

	private function generateAdminWidgets(Post $post, array $threadPosts, bool $adminMode, bool $isThreadOp): string {
		// if the user is logged in as a mod, then run moderator widgets
		if ($adminMode) {
			$widgetDataHtml = '';
			
			if($isThreadOp) {
				$this->postWidget->addThreadModerateWidget($widgetDataHtml, $post, $threadPosts);
			} else {
				$this->postWidget->addReplyModerateWidget($widgetDataHtml, $post);
			}

			$this->postWidget->addPostModerateWidget($widgetDataHtml, $post);

			return $widgetDataHtml;
		}
		// otherwise, return empty string
		else {
			return '';
		}
	}
}
