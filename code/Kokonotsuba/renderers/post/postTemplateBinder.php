<?php

namespace Kokonotsuba\renderers\post;

use Kokonotsuba\interfaces\IBoard;
use Kokonotsuba\module_classes\moduleEngine;
use Kokonotsuba\post\Post;
use Kokonotsuba\renderers\attachmentRenderer;
use Kokonotsuba\renderers\commentFormatter;

use function Kokonotsuba\libraries\_T;
use function Kokonotsuba\libraries\visibleAttachments;
use function Kokonotsuba\libraries\html\generatePostNameHtml;
use function Puchiko\strings\formatFileSize;
use function Puchiko\strings\sanitizeStr;

/**
 * Binds a post's own values into the OP or REPLY template block.
 *
 * Everything drawn from the post row itself is decided here: name, subject, category, tag,
 * links, attachments and warnings. What modules add on top comes later, through postModuleHooks.
 */
final class postTemplateBinder {
	public function __construct(
		private readonly IBoard $board,
		private readonly array $config,
		private readonly moduleEngine $moduleEngine,
		private readonly attachmentRenderer $attachmentRenderer,
		private readonly postElementGenerator $elements,
		private readonly postWarnings $warnings,
	) {}

	/**
	 * Merge the post's values over the caller's. Keys bound here win over whatever came in.
	 *
	 * @param string $postInfoExtra Html appended to the post info line (staff controls).
	 * @param string $warnHidePost  The omitted-replies notice, on the OP only.
	 */
	public function bind(array $templateValues, postRenderContext $ctx, string $postInfoExtra, string $warnHidePost): array {
		$values = $this->commonValues($ctx, $postInfoExtra);

		$values += $ctx->usesOpBlock()
			? $this->opValues($ctx, $warnHidePost)
			: $this->replyValues($ctx);

		return array_merge($templateValues, $values);
	}

	/** Placeholders both blocks share. */
	private function commonValues(postRenderContext $ctx, string $postInfoExtra): array {
		$post = $ctx->post;
		$tag = postTag::resolve($this->config, $post->getTag(), $ctx->isOp);

		return [
			'{$BOARD_URL}' => $ctx->crossLink,
			'{$BOARD_UID}' => $this->board->getBoardUID(),
			'{$BOARD_IDENTIFIER}' => $this->board->getBoardIdentifier(),
			'{$LIVE_INDEX_FILE}' => $this->config['LIVE_INDEX_FILE'],
			'{$POST_UID}' => $post->getUid(),
			'{$NO}' => $post->getNumber(),
			'{$POST_URL}' => $this->board->getBoardThreadURL($ctx->threadResno, $post->getNumber(), false, $ctx->page(), $ctx->crossLink),
			'{$DATA_ATTRIBUTES}' => $this->dataAttributes($post),
			'{$SUB}' => commentFormatter::fieldToHtml($post->getSubject(), $post->getTextFormat()),
			'{$NAME}' => $this->nameHtml($post),
			'{$NAME_TEXT}' => _T('post_name'),
			'{$NOW}' => $post->getTimestamp(),
			'{$CATEGORY}' => $this->elements->processCategoryLinks($post->getCategory(), $ctx->crossLink, $post->getTextFormat()),
			'{$CATEGORY_TEXT}' => _T('post_category'),
			'{$TAG}' => $tag->label,
			'{$TAG_TITLE}' => $tag->title,
			'{$QUOTEBTN}' => $this->elements->generateQuoteButton($ctx->threadResno, $post->getNumber(), $ctx->lastPage(), $ctx->crossLink),
			'{$POST_ATTACHMENTS}' => $this->attachmentRenderer->renderAttachments($this->visibleAttachments($post), $ctx->isDeleted(), $ctx->adminMode),
			'{$COM}' => $post->getComment(),
			'{$WARN_BEKILL}' => $this->warnings->sizeLimit($ctx->killSensor),
			'{$POSTINFO_EXTRA}' => $postInfoExtra,
			// Filled by the thread watcher's OpeningPost listener; blank so the placeholder never shows when it is off.
			'{$WATCH_STAR}' => '',
		];
	}

	private function replyValues(postRenderContext $ctx): array {
		return [
			'{$RESTO}' => $ctx->threadResno,
			'{$POST_POSITION_ENABLED}' => (bool)$this->config['RENDER_REPLY_NUMBER'],
			'{$POST_POSITION}' => $ctx->post->getPostPosition(),
			'{$IS_THREAD}' => false,
		];
	}

	/** The OP block also carries its first file's details and the thread-level links and notices. */
	private function opValues(postRenderContext $ctx, string $warnHidePost): array {
		$post = $ctx->post;
		$attachments = $this->visibleAttachments($post);
		$file = $attachments[array_key_first($attachments)] ?? [];
		$fileName = (string)($file['fileName'] ?? '');

		return [
			'{$RESTO}' => $post->getNumber(),
			'{$REPLYBTN}' => $ctx->threadMode ? $this->elements->generateReplyButton($ctx->crossLink, $ctx->threadResno, $ctx->lastPage()) : '',
			'{$RECENT_REPLIES}' => $ctx->threadMode ? $this->elements->generateRecentRepliesButton($ctx->crossLink, $ctx->threadResno, $ctx->replyCount) : '',
			'{$REPLYNUM}' => $ctx->replyCount,
			'{$FILE_NAME}' => htmlspecialchars($fileName),
			'{$ESCAPED_FILE_NAME}' => str_replace("'", "\\'", htmlspecialchars($fileName)),
			'{$EXTENSION}' => htmlspecialchars((string)($file['fileExtension'] ?? '')),
			'{$FILE_SIZE}' => isset($file['fileSize']) ? formatFileSize($file['fileSize']) : '',
			'{$FILE_WIDTH}' => $file['fileWidth'] ?? 0,
			'{$FILE_HEIGHT}' => $file['fileHeight'] ?? 0,
			'{$FILE_LINK}' => $this->attachmentRenderer->generateImageUrl($file, false, $ctx->isDeleted()),
			'{$WARN_OLD}' => $this->warnings->oldThread($ctx->thread, $ctx->isOp),
			'{$WARN_ENDREPLY}' => '',
			'{$WARN_HIDEPOST}' => $warnHidePost,
			'{$IS_THREAD}' => true,
		];
	}

	private function nameHtml(Post $post): string {
		return generatePostNameHtml(
			$this->moduleEngine,
			$post->getName(),
			$post->getTripcode(),
			$post->getSecureTripcode(),
			$post->getCapcode(),
			$post->getEmail(),
			(bool)$this->config['NOTICE_SAGE'],
			$post->getTextFormat()
		);
	}

	private function dataAttributes(Post $post): string {
		return 'data-post-email="' . sanitizeStr($post->getEmail()) . '"'
			. ' data-post-user-name="' . sanitizeStr($post->getName()) . '"'
			. ' data-post-number="' . $post->getNumber() . '"'
			. ' data-post-uid="' . sanitizeStr($post->getUid()) . '"';
	}

	/** The post's attachments minus any a later upload replaced, per the board's file limit. */
	private function visibleAttachments(Post $post): array {
		return visibleAttachments(
			$post->getAttachments(),
			(int)$this->board->getConfigValue('ATTACHMENT_UPLOAD_LIMIT', 1)
		);
	}
}
