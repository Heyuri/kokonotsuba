<?php
namespace Kokonotsuba\Modules\cssHax;

use Kokonotsuba\board\board;
use Kokonotsuba\error\BoardException;
use Kokonotsuba\database\databaseConnection;
use Kokonotsuba\module_classes\abstractModuleAdmin;
use Kokonotsuba\userRole;
use function Kokonotsuba\libraries\_T;
use function Kokonotsuba\libraries\searchBoardArrayForBoard;
use function Puchiko\request\redirect;
use function Puchiko\request\isPostRequest;
use function Puchiko\request\isGetRequest;
use function Puchiko\strings\sanitizeStr;

require __DIR__  . '/themeRepository.php';
require __DIR__  . '/themeService.php';

class moduleAdmin extends abstractModuleAdmin {
	private string $moduleUrl;
	private themeService $themeService;

	public function getRequiredRole(): userRole {
		return $this->getConfig('AuthLevels.CAN_MANAGE_CSS_HAX', userRole::LEV_MODERATOR);
	}

	public function getName(): string {
		return 'CSS Hax manager';
	}

	public function getVersion(): string {
		return 'Koko 2025';
	}

	public function initialize(): void {
		// get module url
		$this->moduleUrl = $this->getModulePageURL([], false, true);

		// get database table for themes
		$threadThemeTable = getDatabaseSettings()['THREAD_THEMES_TABLE'];

		// get db connection
		$databaseConnection = databaseConnection::getInstance();

		// init repo
		$themeRepository = new themeRepository($databaseConnection, $threadThemeTable);

		// initialize service for managing themes
		$this->themeService = new themeService($themeRepository);

		$this->moduleContext->moduleEngine->addRoleProtectedListener(
			$this->getRequiredRole(),
			'ModerateThreadWidget',
			function(array &$widgetArray, array &$post) {
				$this->onRenderThreadWidget($widgetArray, $post);
			}
		);
		
		/*$this->moduleContext->moduleEngine->addRoleProtectedListener(
			$this->getRequiredRole(),
			'ModuleAdminHeader',
			function(&$moduleHeader) {
				$this->onGenerateModuleHeader($moduleHeader);
			}
		);*/
	}

	private function onRenderThreadWidget(array &$widgetArray, array &$post): void {
		// generate css hax url
		$cssHaxUrl = $this->getModulePageURL(['thread_uid' => $post['thread_uid']], false, true);

		// build the widget entry
		$cssHaxWidget = $this->buildWidgetEntry($cssHaxUrl, 'cssHax', 'Css hax', '');

		// add the widget to the array
		$widgetArray[] = $cssHaxWidget;
	}

	private function onGenerateModuleHeader(string &$moduleHeader): void {
		// get base module url
		$moduleUrl = $this->moduleUrl;

		// get theme template
		$themeTemplate = $this->generateThemeJsTemplate($moduleUrl);

		// append theme template to header
		$moduleHeader .= $themeTemplate;

		// add css hax mod js
		$this->includeScript('cssHax.js', $moduleHeader);
	}

	private function generateThemeJsTemplate(string $moduleUrl): string {
		// generate an empty theme form (parse block)
		$themeFormHtml = $this->moduleContext->adminPageRenderer->ParseBlock('THEME_FORM', ['{$MODULE_URL}' => $moduleUrl]);

		// generate template
		// wraps content in HTML <template> tags
		$themeTemplate = $this->generateTemplate('themeFormTemplate', $themeFormHtml);

		// return the HTML template
		return $themeTemplate;
	}

	private function redirectToThread(array $threadData, board $board): void {
		// fetch thread number
		$threadNumber = $threadData['post_op_number'];

		// return if thread number isn't found
		if(!$threadNumber) {
			return;
		}

		// now build the thread url
		$threadUrl = $board->getBoardThreadURL($threadNumber);

		// redirect!
		redirect($threadUrl);
	}

	private function rebuildAndRedirect(string $threadUid): void {
		// fetch thread data 
		$threadData = $this->moduleContext->threadRepository->getThreadByUid($threadUid) ?? null;

		// dont bother if the thread op is null
		if(!$threadData) {
			return;	
		}
		
		// get board uid
		$boardUid = $threadData['boardUID'] ?? null;

		// return if board uid isn't found
		if(!$boardUid) {
			return;
		}

		// extract the board
		$board = searchBoardArrayForBoard($boardUid);

		// rebuild the board
		$board->rebuildBoard();

		// handle redirect
		$this->redirectToThread($threadData, $board);
	}

	private function validateThread(?string $threadUid): void {
		// make sure the thread uid isn't falsey
		if(!$threadUid) {
			throw new BoardException(_T('thread_not_found'));
		}

		// also make sure the thread exists
		else if(!$this->moduleContext->threadRepository->isThread($threadUid))  {
			throw new BoardException(_T('thread_not_found'));
		}

		// otherwise we're good to go
	}

	private function handleThemeCreation(): void {
		// get the thread uid from POST
		$threadUid = $_POST['thread_uid'] ?? null;

		// validate thread uid
		$this->validateThread($threadUid);

		// collect fields
		$backgroundHexColor = $_POST['backgroundHexColor'] ?? null;
		$replyBackgroundHexColor = $_POST['replyBackgroundHexColor'] ?? null;
		$textHexColor = $_POST['textHexColor'] ?? null;
		$backgroundImageUrl = $_POST['backgroundImageUrl'] ?? null;
		$audio = $_POST['audio'] ?? null;
		$rawStyling = $_POST['rawStyling'] ?? null;

		// define who added it (current staff id)
		$addedBy = $this->moduleContext->currentUserId;

		// now add a new entry
		$this->themeService->addTheme(
			$threadUid,
			$backgroundHexColor,
			$replyBackgroundHexColor,
			$textHexColor,
			$backgroundImageUrl,
			$audio,
			$rawStyling,
			$addedBy
		);

		// rebuild & redirect
		$this->rebuildAndRedirect($threadUid);
	}

	private function handleThemeEdit(): void {
		// get the thread uid from POST
		$threadUid = $_POST['thread_uid'] ?? null;

		// validate
		$this->validateThread($threadUid);

		// check if the entry exists
		if(!$this->themeService->themeExists($threadUid)) {
			throw new BoardException(_T('theme_not_found'));
		}

		// collect fields
		$backgroundHexColor = $_POST['backgroundHexColor'] ?? null;
		$replyBackgroundHexColor = $_POST['replyBackgroundHexColor'] ?? null;
		$textHexColor = $_POST['textHexColor'] ?? null;
		$backgroundImageUrl = $_POST['backgroundImageUrl'] ?? null;
		$audio = $_POST['audio'] ?? null;
		$rawStyling = $_POST['rawStyling'] ?? null;

		// Update the entry in the database
		$this->themeService->editTheme(
			$threadUid,
			$backgroundHexColor,
			$replyBackgroundHexColor,
			$textHexColor,
			$backgroundImageUrl,
			$audio,
			$rawStyling
		);

		// rebuild & redirect
		$this->rebuildAndRedirect($threadUid);
	}

	private function handleThemeDeletion(): void {
		// get the thread uid from POST
		$threadUid = $_POST['thread_uid'] ?? null;

		// validate
		$this->validateThread($threadUid);

		// delete the entry holding that thread uid
		$this->themeService->deleteTheme($threadUid);

		// rebuild & redirect
		$this->rebuildAndRedirect($threadUid);
	}

	private function handleThemeRequest(): void {
		// get the action parameter from request
		$action = $_POST['action'] ?? '';

		// handle creation
		if($action === 'create') {
			$this->handleThemeCreation();
		}
		// handle an edit
		else if($action === 'edit') {
			$this->handleThemeEdit();
		}
		// handle deletion
		else if($action === 'delete') {
			$this->handleThemeDeletion();
		}
		// if none of the above apply - this is an invalid request
		else {
			throw new BoardException(_T('invalid_action'));
		}
	}

	private function buildTemplateValues(array $thread, bool $isEdit): array {
		// bind and return template parameters
		return [
			'{$IS_EDIT}'				=> sanitizeStr($isEdit),
			'{$THREAD_UID}'				=> sanitizeStr($thread['thread_uid']),
			'{$BACKGROUND_HEX_COLOR}'	=> sanitizeStr($thread['background_hex_color'] ?? ''),
			'{$REPLY_BACKGROUND_HEX_COLOR}'	=> sanitizeStr($thread['reply_background_hex_color'] ?? ''),
			'{$TEXT_HEX_COLOR}'			=> sanitizeStr($thread['text_hex_color'] ?? ''),
			'{$BACKGROUND_IMAGE_URL}'	=> sanitizeStr($thread['background_image_url'] ?? ''),
			'{$RAW_STYLING}'			=> sanitizeStr($thread['raw_styling'] ?? ''),
			'{$AUDIO}'					=> sanitizeStr($thread['audio'] ?? ''),
			'{$DATE_ADDED}'				=> sanitizeStr($thread['theme_date_added'] ?? ''),
			'{$ADDED_BY}'				=> sanitizeStr($thread['theme_added_by'] ?? ''),
			'{$THREAD_NUMBER}'			=> sanitizeStr($thread['post_op_number'] ?? ''),
			'{$THREAD_UID}'				=> sanitizeStr($thread['thread_uid'] ?? ''),
			'{$MODULE_URL}'				=> sanitizeStr($this->moduleUrl),
		];
	}

	private function renderForm(array $thread): void {
		// if the entry already exists then it means we're editing an existing entry
		$isEdit = $this->themeService->themeExists($thread['thread_uid']);

		// build template values
		$templateValues = $this->buildTemplateValues($thread, $isEdit);

		// generate theme form html
		$formHtml = $this->moduleContext->adminPageRenderer->ParseBlock('THEME_FORM', $templateValues);

		// now render the page
		echo $this->moduleContext->adminPageRenderer->ParsePage('GLOBAL_ADMIN_PAGE_CONTENT', ['{$PAGE_CONTENT}' => $formHtml], true);
	}

	private function handleThemeView(): void {
		// fetch the thread uid from request
		$threadUid = $_GET['thread_uid'] ?? null;

		// if no thread uid was supplied or has a falsey value then throw error
		if(!$threadUid) {
			throw new BoardException(_T('thread_not_found'));
		}

		// fetch the thread from database
		$thread = $this->moduleContext->threadRepository->getThreadByUid($threadUid, true);

		// also throw error if it wasn't found
		if(empty($thread)) {
			throw new BoardException(_T('thread_not_found'));
		}

		// now handle rendering
		$this->renderForm($thread);		
	}

	public function ModulePage(): void {
		// if its a post request (i.e: form submission)
		if(isPostRequest()) {
			// handle delete/add/edit requests
			$this->handleThemeRequest();
		}
		// GET requests: viewing
		else if (isGetRequest()) {
			$this->handleThemeView();
		}
	}
}