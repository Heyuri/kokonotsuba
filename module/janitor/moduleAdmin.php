<?php
// komeo 2023

namespace Kokonotsuba\Modules\janitor;

use BoardException;
use Kokonotsuba\ModuleClasses\abstractModuleAdmin;
use Kokonotsuba\Root\Constants\userRole;

class moduleAdmin extends abstractModuleAdmin {
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
		$this->moduleContext->moduleEngine->addRoleProtectedListener(
			$this->getRequiredRole(),
			'PostAdminControls',
			function(string &$modControlSection, array &$post) {
				$this->renderWarnButton($modControlSection, $post);
			}
		);

		$this->moduleContext->moduleEngine->addRoleProtectedListener(
			$this->getRequiredRole(),
			'ModeratePostWidget',
			function(array &$widgetArray, array &$post) {
				$this->onRenderPostWidget($widgetArray, $post);
			}
		);

		$this->moduleContext->moduleEngine->addRoleProtectedListener(
			$this->getRequiredRole(),
			'ModuleAdminHeader',
			function(&$moduleHeader) {
				$this->onGenerateModuleHeader($moduleHeader);
			}
		);
	}

	private function renderWarnButton(string &$modfunc, array &$post): void {
		$janitorWarnUrl = $this->generateWarnUrl($post['post_uid']);
		
		$modfunc .= '<span class="adminFunctions adminWarnFunction">[<a href="' . htmlspecialchars($janitorWarnUrl) . '" title="Warn">W</a>]</span>';
	}

	private function onRenderPostWidget(array &$widgetArray, array &$post): void {
		// generate warn url
		$warnUrl = $this->generateWarnUrl($post['post_uid']);

		// build the widget entry for warn
		$warnWidget = $this->buildWidgetEntry(
			$warnUrl, 
			'warn', 
			'Warn', 
			''
		);
		
		// add the widget to the array
		$widgetArray[] = $warnWidget;
	}

	private function generateWarnUrl(int $postUid): string {
		// get the warn url + post uid paramter
		$warnUrl = $this->getModulePageURL(
			[
				'post_uid' => $postUid
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

		// generate warn link tag
		$warnJs = $this->generateScriptHeader('warn.js', true);

		// append js link to module header
		$moduleHeader .= $warnJs;
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
			'{$REASON_DEFAULT}'	=> 'No reason given.',
			'{$FORM_ACTION}'	=> $this->getModulePageURL(),
		];
	}

	public function ModulePage() {
		$post_uid = $_REQUEST['post_uid'] ?? 0;
		$postNumber = $this->moduleContext->postRepository->resolvePostNumberFromUID($post_uid);

		if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
			// get shared template values for this module
			$templateValues = $this->getWarnTemplateValues();

			// merge template values specific to this page into the array
			$templateValues = array_merge($templateValues, 
				[
					'{$POST_NUMBER}'	=> htmlspecialchars($postNumber),
					'{$POST_UID}'		=> htmlspecialchars($post_uid),
				]
			);

			$janitorWarnFormHtml = $this->moduleContext->adminPageRenderer->ParseBlock('JANITOR_WARN_FORM', $templateValues);
			echo $this->moduleContext->adminPageRenderer->ParsePage('GLOBAL_ADMIN_PAGE_CONTENT', ['{$PAGE_CONTENT}' => $janitorWarnFormHtml], true);
			return;
		}

		$post = $this->moduleContext->postRepository->getPostByUid($post_uid);
		if (!$post) {
			throw new BoardException('ERROR: That post does not exist.');
			return;
		}

		$ip = $post['host'];
		$reason = str_replace(",", "&#44;", preg_replace("/[\r\n]/", '', nl2br($_POST['msg'] ?? '')));
		if (!$reason) $reason = 'No reason given.';

		if (!empty($_POST['public'])) {
			$post['com'] .= $this->getPublicWarnMessageHtml($reason); 
			
			// parameters to update in the query
			$updatePostParameters = [
				'com' => $post['com']
			];
			
			$this->moduleContext->postRepository->updatePost($post['post_uid'], $updatePostParameters);
		}

		$board = searchBoardArrayForBoard($post['boardUID']);

		$BANFILE = $board->getBoardStoragePath() . 'bans.log.txt';
		touch($BANFILE);

		$log = array_map('rtrim', file($BANFILE));
		$rtime = $_SERVER['REQUEST_TIME'];
		$log[] = "$ip,$rtime,$rtime,$reason";
		file_put_contents($BANFILE, implode(PHP_EOL, $log) . PHP_EOL);

		$this->moduleContext->actionLoggerService->logAction('Warned ' . $ip . ' for post No. ' . $postNumber, $board->getBoardUID());

		$board->rebuildBoard();
		redirect($board->getBoardURL());
	}

	private function getPublicWarnMessageHtml(string $reason = ''): string {
		// put together public warn message
		$warnHtml = "<p class=\"warning\">(<span class=\"reasonText\">$reason</span>) <img class=\"banIcon icon\" alt=\"banhammer\" src=\"" . $this->getConfig('STATIC_URL') . "/image/hammer.gif\"></p>";
	
		// return message
		return $warnHtml;
	}
}
