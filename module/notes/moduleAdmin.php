<?php

namespace Kokonotsuba\Modules\notes;

require_once __DIR__ . '/noteRepository.php';
require_once __DIR__ . '/noteService.php';
require_once __DIR__ . '/notePolicy.php';

use Kokonotsuba\board\board;
use Kokonotsuba\database\databaseConnection;
use Kokonotsuba\error\BoardException;
use Kokonotsuba\module_classes\abstractModuleAdmin;
use Kokonotsuba\userRole;

use function Kokonotsuba\libraries\_T;
use function Kokonotsuba\libraries\generatePostUrl;
use function Kokonotsuba\libraries\getRoleLevelFromSession;
use function Kokonotsuba\libraries\getUsernameFromSession;
use function Kokonotsuba\libraries\modIdToColorHex;
use function Kokonotsuba\libraries\validatePostInput;
use function Puchiko\json\isJavascriptRequest;
use function Puchiko\json\sendAjaxAndDetach;
use function Puchiko\request\redirect;
use function Puchiko\strings\sanitizeStr;

class moduleAdmin extends abstractModuleAdmin {
	private noteService $noteService;
	private notePolicy $notePolicy;

	public function getRequiredRole(): userRole {
		return $this->getConfig('CAN_LEAVE_NOTE', userRole::LEV_JANITOR);
	}

	public function getName(): string {
		return 'Notes management mod tool';
	}

	public function getVersion(): string {
		return 'Twendy twendy sex';
	}

	public function initialize(): void {
		$this->moduleContext->moduleEngine->addRoleProtectedListener(
			$this->getRequiredRole(),
			'ModuleAdminHeader',
			function(&$moduleHeader) {
				$this->onGenerateModuleHeader($moduleHeader);
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
			'Post',
			function(array &$arrLabels, array &$post, array &$threadPosts, board &$board, bool &$adminMode) {
				$this->renderStaffNotesOnPost($arrLabels, $post, $adminMode);
			}
		);

		// fetch database settings
		$databaseSettings = getDatabaseSettings();

		// init dependencies and set note service
		$noteRepository = new noteRepository(databaseConnection::getInstance(),$databaseSettings['NOTE_TABLE']);
		$this->noteService = new noteService($noteRepository, $this->moduleContext->transactionManager);
		$this->notePolicy = new notePolicy(
			$this->getConfig('AuthLevels', []), 
			getRoleLevelFromSession(), 
			$this->moduleContext->currentUserId
		);

		// set the note service
		$this->notePolicy->setNoteService($this->noteService);
	}
	
	private function onGenerateModuleHeader(string &$moduleHeader): void {
		// include the note js for the mod tool
		$this->includeScript('notes.js', $moduleHeader);

		// Render empty create form
		$noteCreateFormTemplate = $this->moduleContext->adminPageRenderer->ParseBlock('NOTE_CREATE_FORM', [
			'{$POST_UID}' => 0,
			'{$POST_NUMBER}' => 0,
			'{$MODULE_URL}' => sanitizeStr($this->getModulePageURL([], false)),
			'{$NOTE_VISIBILITY_DESCRIPTION}' => _T('note_visibility_description')
		]);
		$moduleHeader .= $this->generateTemplate('noteCreateFormTemplate', $noteCreateFormTemplate);

		// Render empty edit form
		$noteEditFormTemplate = $this->moduleContext->adminPageRenderer->ParseBlock('NOTE_EDIT_FORM', [
			'{$NOTE_ID}' => 0,
			'{$POST_UID}' => 0,
			'{$POST_NUMBER}' => 0,
			'{$MODULE_URL}' => sanitizeStr($this->getModulePageURL([], false)),
			'{$NOTE_TEXT}' => '',
			'{$NOTE_VISIBILITY_DESCRIPTION}' => _T('note_visibility_description')
		]);
		$moduleHeader .= $this->generateTemplate('noteEditFormTemplate', $noteEditFormTemplate);

		// Render empty note entry template
		$noteEntryTemplate = $this->generateTemplate('noteEntryTemplate', $this->generateNoteEntryHtml());
		$moduleHeader .= $noteEntryTemplate;
	}

	private function onRenderPostWidget(array &$widgetArray, array &$post): void {
		// get post details for widget
		$postUid = $post['post_uid'];

		// get post number for widget
		$noteWidget = $this->buildWidgetEntry(
			$this->getModulePageURL(['postUid' => $postUid], false, true),
			'leaveNote',
			_T('leave_note'),
			''
		);

		// append note widget to the thread widget array so it shows up in the thread controls
		$widgetArray[] = $noteWidget;
	}
	
	private function generateNoteEntryHtml(
		?int $postUid = 0,
		?int $noteId = 0,
		?string $noteText = '',
		?string $accountName = '',
		?int $accountId = 0,
		?string $noteTimestamp = ''
	): string {
		// build template values for the note entry
		return $this->moduleContext->adminPageRenderer->ParseBlock('NOTE_ENTRY_HTML', 
			[
				'{$NOTE_ID}' => $noteId,
				'{$NOTE_TEXT}' => $noteText,
				'{$ACCOUNT_NAME}' => $accountName,
				'{$NOTE_TIMESTAMP}' => $noteTimestamp,
				'{$NOTE_TITLE_TEXT}' => _T('note_title_text'),
				// assume its true for the template purposes
				'{$CAN_MODIFY_NOTE}' => $noteId ? $this->notePolicy->canModifyNote($noteId) : true,
				'{$NOTE_DELETION_URL}' => $this->getModulePageURL(['action' => 'deleteNote', 'noteId' => $noteId, 'postUid' => $postUid]),
				'{$NOTE_EDIT_URL}' => $this->getModulePageURL(['modPage' => 'editNoteForm', 'noteId' => $noteId, 'postUid' => $postUid]),
				'{$EDIT_NOTE_TITLE}' => _T('edit_note'),
				'{$DELETE_NOTE_TITLE}' => _T('delete_note'),
				'{$MOD_COLOR}' => modIdToColorHex($accountId),
			]);
	}

	private function renderNote(
		int $postUid, 
		int $noteId, 
		string $noteText, 
		string $accountName, 
		int $accountId,
		string $noteTimestamp
	): string {
		// sanitize the note
		$sanitizedNote = sanitizeStr($noteText);

		// convert new lines to break lines
		$sanitizedNote = nl2br($sanitizedNote, false);

		// generate the string
		$noteHtml = $this->generateNoteEntryHtml(
			$postUid,
			$noteId,
			$sanitizedNote,
			$accountName,
			$accountId,
			$noteTimestamp
		);

		// return the generate message
		return $noteHtml;
	}

	private function renderStaffNotesOnPost(array &$templateValues, array &$post, bool $adminMode): void {
		// only run the method on the live frontend
		if(!$adminMode) {
			return;
		}

		// select staff notes on the post	
		$staffNotesList = $post['staff_notes'] ?? [];

		// initialize the variable we'll be using to store generated note html
		$staffNotesHtml = '';

		// reindex the array
		$staffNotesList = array_values($staffNotesList);

		// loop through and generate the notes html and add it to main variable
		foreach ($staffNotesList as $note) {
			$text = $note['note_text'] ?? '';
			$addedBy = $note['note_added_by_username'] ?? '';
			$addedById = $note['note_added_by'] ?? 0;
			$timestamp = $note['note_submitted'] ?? '';
			$noteId = $note['id'] ?? 0;

			// render the note and append it to the main notes html variable	
			$staffNotesHtml .= $this->renderNote($post['post_uid'], $noteId, $text, $addedBy, $addedById, $timestamp);
		}

		// now append the notes wrapped in a <div> to the post comment (which is passed by reference)
		if($templateValues['{$COM}']) {
			$templateValues['{$COM}'] .= '<div class="staffNotesContainer">' . $staffNotesHtml . '</div>';
		}
	}

	private function handleResponse(
		string $redirectUrl,
		int $postUid, 
		?string $note = null, 
		?int $addedBy = null, 
		?string $addedAt = null,
		?int $noteId = null,
		bool $isDeletion = false,
		bool $isEdit = false
	): void {
		// handle final redirect
		// ===== AJAX handling updated to use helper =====
		if(isJavascriptRequest()) {
			// send json first
			sendAjaxAndDetach([
				'note' => $note,
                'added_by' => getUsernameFromSession(),
				'note_id' => $noteId,
                'added_at' => $addedAt,
				'post_uid' => $postUid,
				'post_number' => $postUid ? $this->moduleContext->postRepository->resolvePostNumberFromUID($postUid) : null,
				'is_deletion' => $isDeletion,
				'is_edit' => $isEdit,
				'is_add' => !$isDeletion && !$isEdit,
				'mod_color' => $addedBy ? modIdToColorHex($addedBy) : null,
				'deletion_url' => $isDeletion ? null : $this->getModulePageURL(['action' => 'deleteNote', 'noteId' => $noteId, 'postUid' => $postUid], false),
				'edit_url' => $isDeletion ? null : $this->getModulePageURL(['modPage' => 'editNoteForm', 'noteId' => $noteId, 'postUid' => $postUid], false),
			]);
			exit;
		}
		// ===== end AJAX handling =====

		redirect($redirectUrl);
	}

	private function handleAddNoteRequest(int $postUid): void {
		// get note content from request
		$noteText = $_POST['note'] ?? '';

		// add the note to the post and get the note ID of the newly inserted note
		$noteId = $this->noteService->addNote(
			$postUid, 
			$noteText, 
			$this->moduleContext->currentUserId
		);

		// generate redirect url (just redirect to the post)
		$redirectUrl = generatePostUrl($postUid, $this->moduleContext->postRepository);		

		// handle response
		$this->handleResponse(
			$redirectUrl,
			$postUid, 
			$noteText, 
			$this->moduleContext->currentUserId,
			date('Y-m-d H:i:s'),
			$noteId
		);
	}

	private function handleDeleteNoteRequest(int $postUid, int $noteId): void {
		// check if the user can modify the note before attempting deletion
		if (!$this->notePolicy->canModifyNote($noteId)) {
			throw new BoardException(_T('note_no_permission'));
		}

		// delete the note
		$this->noteService->deleteNote($noteId);

		// generate redirect url (just redirect to the post)
		$redirectUrl = generatePostUrl($postUid, $this->moduleContext->postRepository);		
		
		// handle response
		$this->handleResponse(
			$redirectUrl,
			$postUid, 
			null, 
			null, 
			null, 
			$noteId,
			true // is deletion
		);
	}

	private function handleEditNoteRequest(int $postUid, int $noteId): void {
		// check if the user can modify the note before attempting deletion
		if (!$this->notePolicy->canModifyNote($noteId)) {
			throw new BoardException(_T('note_no_permission'));
		}

		// get new note content from request
		$newNoteText = $_POST['noteText'] ?? '';

		// edit the note
		$this->noteService->editNote($noteId, $newNoteText);

		// generate redirect url (just redirect to the post)
		$redirectUrl = generatePostUrl($postUid, $this->moduleContext->postRepository);		
		
		// handle response
		$this->handleResponse(
			$redirectUrl,
			$postUid, 
			$newNoteText, 
			$this->moduleContext->currentUserId, 
			date('Y-m-d H:i:s'),
			$noteId,
			false,
			true // is edit
		);
	}

	private function handleActionRoute(string $action, int $postUid, ?int $noteId = null): void {
		if($action === 'addNote') {
			// add a note to the post
			$this->handleAddNoteRequest($postUid);
		}
		elseif ($action === 'deleteNote' && $noteId !== null) {
			// delete the note from the post
			$this->handleDeleteNoteRequest($postUid, $noteId);
		} 
		elseif($action === 'editNote' && $noteId !== null) {
			// edit the note from the post
			$this->handleEditNoteRequest($postUid, $noteId);
		}
		else {
			// invalid action
			throw new BoardException(_T('invalid_action'));
		}
	}

	private function handleNoteRequest(int $postUid, ?int $noteId = null): void {
		// get action
		$action = $_REQUEST['action'] ?? null;

		// handle the request routes
		$this->handleActionRoute($action, $postUid, $noteId);
	}

	private function buildNoteTemplateValues(array $extra = []): array {
		// Always require postUid and postNumber in $extra
		$defaults = [
			'{$POST_UID}' => $extra['postUid'] ?? 0,
			'{$POST_NUMBER}' => $extra['postNumber'] ?? 0,
			'{$MODULE_URL}' => sanitizeStr($this->getModulePageURL([], false)),
		];
		return array_merge($defaults, $extra['template'] ?? []);
	}

	private function renderNoteFormBlock(string $blockName, array $templateValues): void {
		// render the form block with the provided template values
		$form = $this->moduleContext->adminPageRenderer->ParseBlock($blockName, $templateValues);
		
		// wrap the form in the global admin page template
		$htmlOutput = $this->moduleContext->adminPageRenderer->ParsePage(
			'GLOBAL_ADMIN_PAGE_CONTENT',
			['{$PAGE_CONTENT}' => $form],
			true
		);

		// output the form
		echo $htmlOutput;
	}

	private function renderEditNoteForm(int $postUid, int $noteId): void {
		// fetch the note details
		$note = $this->noteService->getNoteById($noteId);
		
		// if the note doesn't exist, throw an error
		if (!$note) {
			throw new BoardException(_T('note_not_found'));
		}

		// fetch the post number for the template
		$postNumber = $this->moduleContext->postRepository->resolvePostNumberFromUID($postUid);
		
		// build template values and render the edit form
		$templateValues = $this->buildNoteTemplateValues([
			'postUid' => $postUid,
			'postNumber' => $postNumber,
			'template' => [
				'{$NOTE_ID}' => $noteId,
				'{$NOTE_TEXT}' => htmlspecialchars($note['note_text'] ?? '', ENT_QUOTES),
				'{$NOTE_VISIBILITY_DESCRIPTION}' => _T('note_visibility_description')
			]
		]);

		// render the edit note form
		$this->renderNoteFormBlock('NOTE_EDIT_FORM', $templateValues);
	}

	private function renderNoteForm(int $postUid): void {
		$postNumber = $this->moduleContext->postRepository->resolvePostNumberFromUID($postUid);
		$templateValues = $this->buildNoteTemplateValues([
			'postUid' => $postUid,
			'postNumber' => $postNumber,
			'template' => [
				'{$NOTE_VISIBILITY_DESCRIPTION}' => _T('note_visibility_description')
			]
		]);
		$this->renderNoteFormBlock('NOTE_CREATE_FORM', $templateValues);
	}

	private function handleModPages(int $postUid, ?int $noteId = null): void {
		// get mod page
		$modPage = $_REQUEST['modPage'] ?? null;

		if($modPage === 'editNoteForm') {
			// render the edit note form
			$this->renderEditNoteForm($postUid, $noteId);
		}
		else {
			// render the default note form (which is the create note form)
			$this->renderNoteForm($postUid);
		}
	}

	public function ModulePage() {
		// get post uid from request
		$postUid = $_REQUEST['postUid'] ?? null;

		// get the note id from the request (only applicable for edit and delete actions)
		$noteId = $_REQUEST['noteId'] ?? null;
		
		// validate post uid
		validatePostInput($postUid);

		// handle the main note requests
		if(isset($_REQUEST['action'])) {
			$this->handleNoteRequest($postUid, $noteId);
		}
		// otherwise just render the form
		else {
			$this->handleModPages($postUid, $noteId);
		}
	}
}