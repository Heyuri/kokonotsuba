<?php

namespace Kokonotsuba\Modules\note;

use Kokonotsuba\error\BoardException;
use Kokonotsuba\module_classes\abstractModuleAdmin;
use Kokonotsuba\userRole;

use function Kokonotsuba\libraries\_T;
use function Kokonotsuba\libraries\validatePostInput;
use function Puchiko\json\isJavascriptRequest;
use function Puchiko\json\sendAjaxAndDetach;
use function Puchiko\request\isPostRequest;
use function Puchiko\request\redirect;

class moduleAdmin extends abstractModuleAdmin {
	public function getRequiredRole(): userRole {
		return $this->getConfig('CAN_LEAVE_NOTE', userRole::LEV_JANITOR);
	}

	public function getName(): string {
		return 'Note module';
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
			'ModerateThreadWidget',
			function(array &$widgetArray, array &$post) {
				$this->onRenderThreadWidget($widgetArray, $post);
			}
		);
	}
	
	private function onGenerateModuleHeader(string &$moduleHeader): void {
		// include the note js for the mod tool
		$this->includeScript('note.js', $moduleHeader);

		// append the note form template to the header
		$noteFormTemplate = $this->generateNoteFormHtml(0, );
		$moduleHeader .= $noteFormTemplate;
	}

	private function onRenderThreadWidget(array &$widgetArray, array &$post): void {
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

	private function handleResponse(
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

		redirect('back');
	}

	private function handleAddNoteRequest(int $postUid): void {
		// get note content from request
		$noteContent = $_POST['note'] ?? '';

		// add the note to the post
		$this->moduleContext->noteService->addNote(
			$postUid, 
			$noteContent, 
			$this->moduleContext->currentUserId
		);

		// handle response
		$this->handleResponse(
			$postUid, 
			$noteContent, 
			$this->moduleContext->currentUserId, 
			date('Y-m-d H:i:s'));
	}

	private function handleActionRoute(string $action, int $postUid, ?int $noteId = null): void {
		if($action === 'add_note') {
			// add a note to the post
			$this->handleAddNoteRequest($postUid);
		}
		elseif ($action === 'delete_note') {
			// delete the note from the post
			$this->handleDeleteNoteRequest($postUid, $noteId);
		} 
		elseif($action === 'edit_note') {
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
	}

	private function renderNoteForm(int $postUid): void {
		// fetch post number
		$postNumber = $this->moduleContext->postRepository->resolvePostNumberFromUID($postUid);

		// render the note form
		$htmlOutput = $this->generateNoteFormHtml($postUid, $postNumber);

		// output the form
		echo $htmlOutput;
	}

	public function ModulePage() {
		// get post uid from request
		$postUid = $_GET['postUid'] ?? null;
		
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