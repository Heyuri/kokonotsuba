<?php
// komeo 2023

namespace Kokonotsuba\Modules\janitor;

use Kokonotsuba\action_log\actionType;
use Kokonotsuba\ban\banImagePicker;
use Kokonotsuba\error\BoardException;
use Kokonotsuba\module_classes\abstractModuleAdmin;
use Kokonotsuba\module_classes\traits\BanCheckpointTrait;
use Kokonotsuba\module_classes\traits\listeners\PostControlHooksTrait;
use Kokonotsuba\post\Post;
use Kokonotsuba\userRole;

use function Kokonotsuba\libraries\_T;
use function Kokonotsuba\libraries\generateModerateButton;
use function Puchiko\strings\newLinesToBreakLines;
use function Kokonotsuba\libraries\searchBoardArrayForBoard;
use function Puchiko\request\redirect;
use function Puchiko\strings\sanitizeStr;

class moduleAdmin extends abstractModuleAdmin {
	use BanCheckpointTrait;
	use PostControlHooksTrait;
	public function getRequiredRole(): userRole {
		return userRole::LEV_JANITOR;
	}

	public function getName(): string {
		return 'Janitor tools';
	}

	public function getVersion(): string  {
		return 'Koko 2025';
	}
	
	public function initialize(): void {
		$this->registerPostControlPair('renderWarnButton');
		$this->registerSimplePostWidget(
			fn(Post $post) => $this->generateWarnUrl($post->getUid()),
			'warn',
			'Warn'
		);
		$this->registerAdminHeaderHook('onGenerateModuleHeader');
	}

	private function renderWarnButton(string &$modfunc, Post &$post, bool $noScript = false): void {
		$janitorWarnUrl = $this->generateWarnUrl($post->getUid());
		
		$modfunc .= generateModerateButton(
			$janitorWarnUrl,
			'W',
			'Warn the user who made this post',
			'adminWarnFunction',
			$noScript
		);
	}

	private function generateWarnUrl(int $postUid): string {
		// get the warn url + post uid paramter
		$warnUrl = $this->getModulePageURL(
			[
				'postUid' => $postUid
			],
			false,
			true
		);

		// return url
		return $warnUrl;
	}

	private function onGenerateModuleHeader(string &$moduleHeader): void {
		// get warn template
		$warnTemplate = $this->generateWarnJsTemplate();

		// append warn template to header
		$moduleHeader .= $warnTemplate;

		// get public message template
		$warnMessageTemplate = $this->generatePublicWarnTemplate();

		// append pub warn message to header
		$moduleHeader .= $warnMessageTemplate;

		// add warn js
		$this->includeScript('warn.js', $moduleHeader);
	}

	private function generatePublicWarnTemplate(): string {
		// get empty public warn
		$warnHtml = $this->getPublicWarnMessageHtml();
		
		// create template
		$warnMessageTemplate = $this->generateTemplate('publicMessage', $warnHtml);

		// return warn message template
		return $warnMessageTemplate;
	}

	private function generateWarnJsTemplate(): string {
		// warn template placeholders
		$templateValues = $this->getWarnTemplateValues();

		// generate an empty warn form (parse block)
		$warnFormHtml = $this->moduleContext->adminPageRenderer->ParseBlock('JANITOR_WARN_FORM', $templateValues);

		// generate template
		// wraps content in HTML <template> tags
		$warnTemplate = $this->generateTemplate('warnFormTemplate', $warnFormHtml);

		// return the HTML template
		return $warnTemplate;
	}
	
	private function getWarnTemplateValues(): array {
		// return shared warn template values
		return [
			'{$REASON_DEFAULT}'	=> sanitizeStr(_T('ban_no_reason')),
			'{$FORM_ACTION}'	=> sanitizeStr($this->getModulePageURL()),
			'{$FORM_HEADING}'	=> sanitizeStr(_T('warn_form_heading')),
			'{$LABEL_POST}'		=> sanitizeStr(_T('warn_form_label_post')),
			'{$LABEL_REASON}'	=> sanitizeStr(_T('warn_form_label_reason')),
			'{$LABEL_PUBLIC}'	=> sanitizeStr(_T('warn_form_label_public')),
			'{$DESC_PUBLIC}'	=> sanitizeStr(_T('warn_form_desc_public')),
			'{$SUBMIT_TEXT}'	=> sanitizeStr(_T('warn_form_submit')),
		];
	}

	public function ModulePage() {
		$postUid = $this->moduleContext->request->getParameter('postUid', null, 0);
		$postNumber = $this->moduleContext->postRepository->resolvePostNumberFromUID($postUid);

		if (!$this->moduleContext->request->isPost()) {
			// get shared template values for this module
			$templateValues = $this->getWarnTemplateValues();

			// merge template values specific to this page into the array
			$templateValues = array_merge($templateValues, 
				[
					'{$POST_NUMBER}'	=> htmlspecialchars($postNumber),
					'{$POST_UID}'		=> htmlspecialchars($postUid),
				]
			);

			$janitorWarnFormHtml = $this->moduleContext->adminPageRenderer->ParseBlock('JANITOR_WARN_FORM', $templateValues);
			echo $this->moduleContext->adminPageRenderer->ParsePage('GLOBAL_ADMIN_PAGE_CONTENT', ['{$PAGE_CONTENT}' => $janitorWarnFormHtml], true);
			return;
		}

		$post = $this->moduleContext->postRepository->getPostByUid($postUid, true);
		if (!$post) {
			throw new BoardException('ERROR: That post does not exist.');
			return;
		}

		$ip = $post->getIp();
		$reason = str_replace(",", "&#44;", preg_replace("/[\r\n]/", '', newLinesToBreakLines($this->moduleContext->request->getParameter('msg', 'POST', ''))));
		if (!$reason) $reason = 'No reason given.';

		// The public notice rides on the ban, the way adminBan's does: the post's comment is the
		// poster's text and nothing else, so withdrawing the warning withdraws the notice too.
		$publicReason = !empty($this->moduleContext->request->getParameter('public', 'POST'))
			? $this->getPublicWarnMessageHtml($reason)
			: '';

		$board = searchBoardArrayForBoard($post->getBoardUID());

		// A warning is a ban that blocks nothing: it is shown once and gets out of the way.
		$accountId = (int) ($this->moduleContext->currentUserId ?? 0);

		$this->getBanService()->fileBan(
			$ip,
			$board->getBoardUID(),
			[],
			null,
			$reason,
			$accountId > 0 ? $accountId : null,
			$post->getUid(),
			false,
			true,
			false,
			$publicReason
		);

		$this->moduleContext->actionLoggerService->logAction('Warned ' . $ip . ' for post No. ' . $postNumber, $board->getBoardUID(), actionType::BAN_ISSUE);

		$board->rebuildBoard();
		redirect($board->getBoardURL());
	}

	/** The image comes from static/image/ban/ at random, same as a ban's own notice. */
	private function getPublicWarnMessageHtml(string $reason = ''): string {
		$image = (new banImagePicker(
			(string) $this->getConfig('STATIC_PATH'),
			(string) $this->getConfig('STATIC_URL')
		))->random();

		$url = $image->url;
		$dimensions = $image->dimensionAttributes();

		return "<p class=\"warning\">(<span class=\"reasonText\">$reason</span>) "
			. "<img class=\"banIcon icon\" alt=\"banhammer\" src=\"$url\" $dimensions></p>";
	}
}
