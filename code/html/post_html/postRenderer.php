<?php
/*
* Post renderer for Kokonotsuba!
* Handles post html output
*/

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
		private array $quoteLinksFromBoard) {
			// initialize post data preperation class
			$this->postDataPreparer = new postDataPreparer($board);

			// initialize attachment rendering class
			$this->attachmentRenderer = new attachmentRenderer($board, $moduleEngine);

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
		array $post,
		array &$templateValues,
		int $threadResno,
		bool $killSensor,
		array $threadPosts,
		bool $adminMode,
		string $postFormExtra,
		string $warnBeKill,
		string $warnOld,
		string $warnHidePost,
		string $warnEndReply,
		int $replyCount,
		bool $threadMode = true,
		string $crossLink = '',
		bool $renderAsOp = false
	) {
		// Prepare post data
		$data = $this->postDataPreparer->preparePostData($post);

		// Define if it's the thread's OP or a reply
		$isThreadOp = $data['is_op'] ? true : false;
		$isThreadReply = !$isThreadOp;  // Inverse of $isThreadOp
		
		// Apply quote and quote link
		$data['com'] = generateQuoteLinkHtml($this->quoteLinksFromBoard, $data, $threadResno, $this->board->getConfigValue('USE_QUOTESYSTEM'), $this->board);
		$data['com'] = quote_unkfunc($data['com']);

		// Post position config
		$postPositionEnabled = $this->config['RENDER_REPLY_NUMBER'];
	
		// Process category links
		$categoryHTML = $this->postElementGenerator->processCategoryLinks($data['category'], $crossLink);

		// this post is deleted
		$isDeleted = $data['open_flag'] && !$data['file_only_deleted'] && $adminMode;

		// process attachment data
		$attachmentsHtml = $this->processAttachments(
			$data['attachments'],
			$isDeleted,
			$adminMode
		);

		// File size warning (if necessary)
		$warnBeKill = '';
		if ($this->config['STORAGE_LIMIT'] && $killSensor) {
			$warnBeKill = '<div class="warning">'._T('warn_sizelimit').'</div>';
		}

		$templateValues['{$POSTINFO_EXTRA}'] = '';

		// Admin controls hook (if admin mode is on)
		$postFormExtra .= $this->generateAdminControls($post, $adminMode, $isThreadOp);

		// staff widgets
		$widgetDataHtml = $this->generateAdminWidgets($post, $threadPosts, $adminMode, $isThreadOp);

		// handle thread max-age message
		if ($isThreadOp) {
			$maxAgeLimit = $this->config['MAX_AGE_TIME'];
			$postUnixTimestamp = is_numeric($post['root']) ? $post['root'] : strtotime($post['root']);
			if ($maxAgeLimit && $_SERVER['REQUEST_TIME'] - $postUnixTimestamp > ($maxAgeLimit * 60 * 60)) {
				$warnOld .= "<div class='warning'>"._T('warn_oldthread')."</div>";
			}
		}

		// Handle name/trip/capcode HTML generation
		$nameHtml = generatePostNameHtml(
			$this->moduleEngine,
			$data['name'],
			$data['tripcode'],
			$data['secure_tripcode'],
			$data['capcode'],
			$data['email'],
			$this->config['NOTICE_SAGE']
		);

		// Generate the quote and reply buttons
		$quoteButton = $this->postElementGenerator->generateQuoteButton($threadResno, $data['no']);
		$replyButton = $threadMode ? $this->postElementGenerator->generateReplyButton($crossLink, $threadResno) : '';

		// get first attachment array key
		$firstAttachmentArrKey = array_key_first($data['attachments']);

		// get the first attachment
		// This is needed for the flash board template which uses various 
		$firstAttachment = $data['attachments'][$firstAttachmentArrKey] ?? [];

		// generate first attachment URL
		$firstAttachmentUrl = $this->attachmentRenderer->generateImageUrl($firstAttachment, false, $isDeleted);

		// Variables to used for the condition for whether to use OP/Reply template block
		$shouldRenderReply = $isThreadReply && !$renderAsOp;
		$shouldRenderOp = $isThreadOp || $renderAsOp;

		// Bind the template values based on whether it's a reply or OP
		if ($shouldRenderReply) {
			$templateValues = $this->postTemplateBinder->renderReplyPost(
				$data, 
				$crossLink, 
				$postPositionEnabled, 
				$templateValues, 
				$threadResno,
				$nameHtml, 
				$categoryHTML,
				$quoteButton, 
				$attachmentsHtml, 
				$warnBeKill, 
				$postFormExtra, 
			);
		} 
		// render the thread using the OP template block if its a thread OP.
		//
		// also optionally pass the $renderAsOp flag to force it to use the OP template block -
		// - in order to various HTML problems that can occur across various themes. Which is useful 
		// - when we're not rendering in a thread format.
		elseif ($shouldRenderOp) {
			$templateValues = $this->postTemplateBinder->renderOpPost(
				$data, 
				$firstAttachment,
				$crossLink,
				$templateValues, 
				$nameHtml, 
				$categoryHTML, 
				$quoteButton, 
				$attachmentsHtml,
				$firstAttachmentUrl,
				$replyButton, 
				$postFormExtra, 
				$replyCount, 
				$warnOld, 
				$warnBeKill, 
				$warnEndReply, 
				$warnHidePost,
			);
		}

		// Dispatch Post event, which is a hook point that will affect every post
		$board = $this->board;
		$this->moduleEngine->dispatch('Post', [
			&$templateValues,
			&$post,
			&$threadPosts,
			&$board,
			&$adminMode,
		]);


		// CSS hook point placeholders
		$templateValues['{$MODULE_ATTACHMENT_CSS_CLASSES}'] = '';
		$templateValues['{$MODULE_POST_CSS_CLASSES}'] = '';

		// attachment CSS reference
		$attachmentCss = &$templateValues['{$MODULE_ATTACHMENT_CSS_CLASSES}'];

		// Dispatch attachment css event
		$this->moduleEngine->dispatch('AttachmentCssClass', [
			&$attachmentCss,
			&$post,
			&$adminMode
		]);

		// post CSS reference
		$postCss = &$templateValues['{$MODULE_POST_CSS_CLASSES}'];

		// Dispatch post css event
		$this->moduleEngine->dispatch('PostCssClass', [
			&$postCss,
			&$post
		]);

		// Dispatch reply hook + widget
		if ($isThreadReply) {
			// thread reply hook dispatch
			$this->moduleEngine->dispatch('ThreadReply', [&$templateValues, &$post, &$threadPosts]);
		
			// run reply widget
			$this->postWidget->addThreadReplyWidget($widgetDataHtml, $post);
		} 
		// dispatch opening post hook point and widget
		elseif ($isThreadOp) {
			$this->moduleEngine->dispatch('OpeningPost', [&$templateValues, &$post, &$threadPosts]);

			// run opening post widget
			$this->postWidget->addOpeningPostWidget($widgetDataHtml, $post, $threadPosts);
		}

		// run post widget
		$this->postWidget->addPostWidget($widgetDataHtml, $post);

		// then, generate the full widget html
		$templateValues['{$POST_MENU}'] = $this->postWidget->generatePostMenuHtml($widgetDataHtml);

		// Return HTML based on render intent (allow forcing OP layout via $renderAsOp)
		if ($isThreadReply && !$renderAsOp) {
			return $this->templateEngine->ParseBlock('REPLY', $templateValues);
		} 
		// Render as OP
		else {
			return $this->templateEngine->ParseBlock('OP', $templateValues);
		}
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
		foreach ($attachments as $index=>$att) {
			// generateAttachmentHtml()
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

	private function generateAdminControls(array $post, bool $adminMode, bool $isThreadOp): string {
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

	private function generateAdminWidgets(array $post, array $threadPosts, bool $adminMode, bool $isThreadOp): string {
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
