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

	public function __construct(
		private readonly IBoard $board, 
		private readonly array $config, 
		private readonly moduleEngine $moduleEngine, 
		private readonly templateEngine $templateEngine,
		private array $quoteLinksFromBoard) {
			// get file IO instance
			$FileIO = PMCLibrary::getFileIOInstance();

			// initialize post data preperation class
			$this->postDataPreparer = new postDataPreparer($board, $FileIO);

			// initialize attachment rendering class
			$this->attachmentRenderer = new attachmentRenderer($FileIO, $board, $moduleEngine);

			// intialize post template binding class
			$this->postTemplateBinder = new postTemplateBinder($board, $config);

			// intialize post element generator class
			$this->postElementGenerator = new postElementGenerator($board);
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

		// get file properties
		$fileData = $data['fileData'];

		// whether theres an attachment
		$hasAttachment = !empty($fileData);

		// this post is deleted
		$isDeleted = $data['open_flag'] && $adminMode;

		// this post's attachment was deleted
		$fileOnlyDeleted = $data['open_flag'] && $data['file_only_deleted'];

		// handle attachment related rendering
		[$imageBar, $imageURL, $imageHtml] = $hasAttachment ? $this->attachmentRenderer->generateAttachmentHtml($fileData, $isDeleted, $fileOnlyDeleted, $adminMode) : ['', '', ''];

		// File size warning (if necessary)
		$warnBeKill = '';
		if ($this->config['STORAGE_LIMIT'] && $killSensor) {
			$warnBeKill = '<div class="warning">'._T('warn_sizelimit').'</div>';
		}

		$templateValues['{$POSTINFO_EXTRA}'] = '';

		// Admin controls hook (if admin mode is on)
		if ($adminMode) {
			$modFunc = '';
			
			if($isThreadOp) {
				$this->moduleEngine->dispatch('ThreadAdminControls', [&$modFunc, &$post]);
			} else {
				$this->moduleEngine->dispatch('ReplyAdminControls', [&$modFunc, &$post]);
			}

			$this->moduleEngine->dispatch('PostAdminControls', [&$modFunc, &$post]);
			
			$postFormExtra .= $modFunc;
		}

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

		// Variables to used for the condition for whether to use OP/Reply template block
		$shouldRenderReply = $isThreadReply && !$renderAsOp;
		$shouldRenderOp = $isThreadOp || $renderAsOp;

		// Bind the template values based on whether it's a reply or OP
		if ($shouldRenderReply) {
			$templateValues = $this->postTemplateBinder->renderReplyPost(
				$data, 
				$postPositionEnabled,
				$templateValues, 
				$threadResno,
				$nameHtml, 
				$categoryHTML,
				$quoteButton, 
				$imageBar, 
				$warnBeKill, 
				$postFormExtra,
				$imageHtml,
				$imageURL
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
				$fileData,
				$templateValues, 
				$nameHtml, 
				$categoryHTML, 
				$quoteButton, 
				$replyButton, 
				$imageBar, 
				$postFormExtra, 
				$replyCount, 
				$warnOld, 
				$warnBeKill, 
				$warnEndReply, 
				$warnHidePost,
				$imageHtml,
				$imageURL
			);
		}

		// Dispatch Post event, which is a hook point that will affect every post
		$board = $this->board;
		$this->moduleEngine->dispatch('Post', [
			&$templateValues,
			&$post,
			&$threadPosts,
			&$board
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

		// Dispatch specific hook and return template
		if ($isThreadReply) {
			$this->moduleEngine->dispatch('ThreadReply', [&$templateValues, $post, $threadPosts, $isThreadReply]);
		} elseif ($isThreadOp) {
			$this->moduleEngine->dispatch('OpeningPost', [&$templateValues, $post, $threadPosts, $isThreadReply]);
		}

		// Return HTML based on render intent (allow forcing OP layout via $renderAsOp)
		if ($isThreadReply && !$renderAsOp) {
			return $this->templateEngine->ParseBlock('REPLY', $templateValues);
		} 
		// Render as OP
		else {
			return $this->templateEngine->ParseBlock('OP', $templateValues);
		}
	}

}
