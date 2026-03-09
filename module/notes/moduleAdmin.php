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
use function Kokonotsuba\libraries\validatePostInput;
use function Puchiko\json\isJavascriptRequest;
use function Puchiko\json\sendAjaxAndDetach;
use function Puchiko\request\isPostRequest;
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
		$this->notePolicy = new notePolicy($this->noteService);
	}
	
	private function onGenerateModuleHeader(string &$moduleHeader): void {
		// include the note js for the mod tool
		$this->includeScript('notes.js', $moduleHeader);

		// append the note form template to the header
		$noteCreateFormTemplate = $this->generateNoteFormHtml(0, 0);
		$moduleHeader .= $this->generateTemplate('noteCreateFormTemplate', $noteCreateFormTemplate);
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

	private function renderNote(string $noteText, string $accountName, string $noteTimestamp): string {
		// sanitize the note
		$sanitizedNote = sanitizeStr($noteText);

		// convert new lines to break lines
		$sanitizedNote = nl2br($sanitizedNote, false);

		// generate the string
		$noteHtml = $this->moduleContext->adminPageRenderer->ParseBlock('NOTE_ENTRY_HTML', 
			[
				'{$NOTE_TEXT}' => $sanitizedNote,
				'{$ACCOUNT_NAME}' => $accountName,
				'{$NOTE_TIMESTAMP}' => $noteTimestamp,
				'{$NOTE_TITLE_TEXT}' => _T('note_title_text'),
				'{$CAN_DELETE_NOTE}' => $this->notePolicy->canDeleteNote($noteId),
				'{$CAN_EDIT_NOTE}' => $this->notePolicy->canEditNote($noteId),
				'{$NOTE_EDIT_URL}' => $this->getModulePageURL(['action' => 'deleteNote', 'noteId' => $noteId]),
				'{$NOTE_DELETION_URL}' => $this->getModulePageURL(['action' => 'editNote', 'noteId' => $noteId]),
			]);

		// return the generate message
		return $noteHtml;
	}

	private function renderStaffNotesOnPost(array &$templateValues, array &$post, bool $adminMode): void {
		// only run the method on the live frontend
		if(!$adminMode) {
			return;
		}

		// select staff notes on the post	
		$staffNotesList = $post['staff_notes'] ?? null;

		// dont bother rendering if theres no notes on the post
		if(empty($staffNotesList)) {
			return;
		}

		// initialize the variable we'll be using to store generated note html
		$staffNotesHtml = '';

		// reindex the array
		$staffNotesList = array_values($staffNotesList);

		// get the last index of the list
		$lastIndex = count($staffNotesList) - 1;

		// loop through and generate the notes html and add it to main variable
		foreach ($staffNotesList as $index => $note) {
			$text = $note['note_text'] ?? '';
			$addedBy = $note['note_added_by_username'] ?? '';
			$timestamp = $note['note_submitted'] ?? '';

			$staffNotesHtml .= $this->renderNote($text, $addedBy, $timestamp);

			// Add separator only if not last item
			if ($index !== $lastIndex) {
				$staffNotesHtml .= '<div class="noteSeparator">---</div>';
			}
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
		bool $isDeletion = false,
		bool $isEdit = false
	): void {
		// handle final redirect
		// ===== AJAX handling updated to use helper =====
		if(isJavascriptRequest()) {
			// send json first
			sendAjaxAndDetach([
				'note' => $note,
                'added_by' => $addedBy,
                'added_at' => $addedAt,
				'post_uid' => $postUid,
				'is_deletion' => $isDeletion,
				'is_edit' => $isEdit,
			]);
			exit;
		}
		// ===== end AJAX handling =====

		redirect($redirectUrl);
	}

	private function handleAddNoteRequest(int $postUid): void {
		// get note content from request
		$noteText = $_POST['note'] ?? '';

		// add the note to the post
		$this->noteService->addNote(
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
			date('Y-m-d H:i:s'));
	}

	private function handleActionRoute(string $action, int $postUid, ?int $noteId = null): void {
		if($action === 'addNote') {
			// add a note to the post
			$this->handleAddNoteRequest($postUid);
		}
		elseif ($action === 'deleteNote') {
			// delete the note from the post
			$this->handleDeleteNoteRequest($postUid, $noteId);
		} 
		elseif($action === 'editNote') {
			// edit the note from the post
			$this->handleEditNoteRequest($postUid, $noteId);
		}
		else {
			// invalid action
			throw new BoardException(_T('invalid_action'));
		}
	}

	private function handleNoteRequest(int $postUid): void {
		// get action
		$action = $_POST['action'] ?? null;

		// handle the request routes
		$this->handleActionRoute($action, $postUid);
	}

	private function generateNoteFormHtml(int $postUid, int $postNumber): string {
		// build template values for the note form
		$templateValues = [
			'{$POST_UID}' => $postUid,
			'{$POST_NUMBER}' => $postNumber,
			'{$MODULE_URL}' => sanitizeStr($this->getModulePageURL([], false)),
			'{$NOTE_VISIBILITY_DESCRIPTION}' => _T('note_visibility_description')
		];

		// Parse block and return
		return $this->moduleContext->adminPageRenderer->ParseBlock('NOTE_CREATE_FORM', $templateValues);
	}

	private function renderNoteForm(int $postUid): void {
		// fetch post number
		$postNumber = $this->moduleContext->postRepository->resolvePostNumberFromUID($postUid);

		// render the note form
		$noteForm = $this->generateNoteFormHtml($postUid, $postNumber);

		// render the page
		$htmlOutput = $this->moduleContext->adminPageRenderer->ParsePage(
			'GLOBAL_ADMIN_PAGE_CONTENT', 
			['{$PAGE_CONTENT}' => $noteForm], true);

		// output the form
		echo $htmlOutput;
	}

	public function ModulePage() {
		// get post uid from request
		$postUid = $_REQUEST['postUid'] ?? null;
		
		// validate post uid
		validatePostInput($postUid);

		// handle the main note requests
		if(isPostRequest()) {
			$this->handleNoteRequest($postUid);
		}
		// otherwise just render the form
		else {
			$this->renderNoteForm($postUid);
		}
	}
}