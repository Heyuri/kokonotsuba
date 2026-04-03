<?php

namespace Kokonotsuba\Modules\edit;

use Kokonotsuba\module_classes\abstractModuleAdmin;
use Kokonotsuba\module_classes\traits\AuditableTrait;
use Kokonotsuba\module_classes\traits\listeners\PostControlHooksTrait;
use Kokonotsuba\post\Post;
use Kokonotsuba\userRole;

use const Kokonotsuba\GLOBAL_BOARD_UID;

use function Kokonotsuba\libraries\_T;
use function Kokonotsuba\libraries\rebuildBoardsFromPosts;
use function Kokonotsuba\libraries\validatePostInput;
use function Puchiko\json\sendAjaxAndDetach;
use function Puchiko\request\redirect;
use function Puchiko\strings\sanitizeStr;

class moduleAdmin extends abstractModuleAdmin {
	use AuditableTrait;
	use PostControlHooksTrait;

	public function getRequiredRole(): userRole {
		return $this->getConfig('CAN_EDIT_POST', userRole::LEV_MODERATOR);
	}

	public function getName(): string {
		return 'Mod editing tools';
	}

	public function getVersion(): string {
		return 'Twendy twendy sex';
	}

	public function initialize(): void {
		$this->registerAdminHeaderHook('onGenerateModuleHeader');
		$this->registerPostWidgetHook('onRenderPostWidget');
	}
	
	private function onGenerateModuleHeader(string &$moduleHeader): void {
		// include the post edit js for the mod tool
		//$this->includeScript('postEdit.js', $moduleHeader);

		// Render empty create form
		$postEditFormTemplate = $this->moduleContext->adminPageRenderer->ParseBlock('POST_EDIT_FORM', [
			'{$POST_UID}' => 0,
			'{$POST_NUMBER}' => 0,
			'{$NAME}' => '',
			'{$COMMENT}' => '',
			'{$SUBJECT}' => '',
			'{$EMAIL}' => '',
			'{$FORM_NAME}' => _T('form_name'),
			'{$FORM_EMAIL}' => _T('form_email'),
			'{$FORM_TOPIC}' => _T('form_topic'),
			'{$FORM_COMMENT}' => _T('form_comment'),
			'{$MODULE_URL}' => sanitizeStr($this->getModulePageURL([], false))
		]);

		// append the form template to the module header so it's available on the page (but hidden until triggered)
		$moduleHeader .= $this->generateTemplate('postEditFormTemplate', $postEditFormTemplate);
	}

	private function onRenderPostWidget(array &$widgetArray, Post &$post): void {
		// get post details for widget
		$postUid = $post->getUid();

		// get post number for widget
		$postWidget = $this->buildWidgetEntry(
			$this->getModulePageURL(['postUid' => $postUid], false, true),
			'editPost',
			_T('edit_post'),
			''
		);

		// append post widget to the post widget
		$widgetArray[] = $postWidget;
	}

	private function editPost(
		int $postUid, 
		?string $name, 
		?string $comment, 
		?string $subject, 
		?string $email
	): void {
		// parameters to update in the query
		$updatePostParameters = [
			'name' => $name,
			'com' => $comment,
			'sub' => $subject,
			'email' => $email
		];

		// convert new lines
		if($comment !== null) {
			$updatePostParameters['com'] = nl2br($comment, false);
		}

		// Filter out null values
		$updatePostParameters = array_filter($updatePostParameters, function($v) { return $v !== null; });

		// update the post in database
		$this->moduleContext->postRepository->updatePost($postUid, $updatePostParameters);
	}

	private function sendJson(int $postUid): void {
		// get the updated post details
		$post = $this->moduleContext->postRepository->getPostByUID(
			$postUid, 
			$this->moduleContext->postRenderingPolicy->viewDeleted()
		);

		// send the updated post data back as json
		sendAjaxAndDetach([
			'postUserName' => $post->getName() ?? '',
			'comment' => $post->getComment() ?? '',
			'subject' => $post->getSubject() ?? '',
			'postEmail' => $post->getEmail() ?? '',
			'postUid' => $post->getUid() ?? '',
			'postNumber' => $post->getNumber() ?? ''
		]);
		exit;
	}

	private function redirect(int $postNumber): void {
		// then redirect to the post
		if($postNumber) {
			redirect($this->moduleContext->board->getBoardThreadURL($postNumber));
		} else {
			// fallback redirect to board if post number isn't available for some reason
			redirect($this->moduleContext->board->getBoardURL());
		}
	}

	private function handleEditRequest(int $postUid): void {
		// variable to hold post number for redirect after edit
		$postNumber = null;

		// board uid
		$boardUid = null;

		// wrap in transaction to ensure data integrity
		$this->moduleContext->transactionManager->run(function() use ($postUid, &$postNumber, &$boardUid) {
			// get post details for widget
			$post = $this->moduleContext->postRepository->getPostByUID(
				$postUid, 
				$this->moduleContext->postRenderingPolicy->viewDeleted()
			);

			// check if post exists
			validatePostInput($post, false);

			// store post number for redirect after edit
			$postNumber = $post->getNumber() ?? null;

			// get board uid
			$boardUid = $post->getBoardUID() ?? null;

			// get the parameters
			$name = $this->moduleContext->request->getParameter('postUserName', 'POST');
			$comment = $this->moduleContext->request->getParameter('comment', 'POST');
			$subject = $this->moduleContext->request->getParameter('subject', 'POST');
			$email = $this->moduleContext->request->getParameter('postEmail', 'POST');
			
			// handle the edit
			$this->editPost($postUid, $name, $comment, $subject, $email);
		});

		// rebuild the board html of the post
		rebuildBoardsFromPosts([$postUid], $this->moduleContext->postService);

		// log the edit action
		$this->logAction("Edited post No.{$postNumber}", $boardUid ?? GLOBAL_BOARD_UID);

		// send json data back if it's a js request
		if($this->moduleContext->request->isAjax()) {
			$this->sendJson($postUid);
		}
		// otherwise redirect back to the post
		else if($postNumber) {
			// redirect back to the post after edit
			$this->redirect($postNumber);
		}
	}

	private function handleEditPage(int $postUid): void {
		// get post details for widget
		$post = $this->moduleContext->postRepository->getPostByUID(
			$postUid, 
			$this->moduleContext->postRenderingPolicy->viewDeleted()
		);

		// check if post exists
		validatePostInput($post, false);

		// page content
		$pageContent = $this->moduleContext->adminPageRenderer->ParseBlock('POST_EDIT_FORM',[
			'{$POST_UID}' => $post->getUid(),
			'{$POST_NUMBER}' => $post->getNumber(),
			'{$NAME}' => sanitizeStr($post->getName() ?? ''),
			'{$COMMENT}' => sanitizeStr($post->getComment() ?? ''),
			'{$SUBJECT}' => sanitizeStr($post->getSubject() ?? ''),
			'{$EMAIL}' => sanitizeStr($post->getEmail() ?? ''),
			'{$FORM_NAME}' => _T('form_name'),
			'{$FORM_EMAIL}' => _T('form_email'),
			'{$FORM_TOPIC}' => _T('form_topic'),
			'{$FORM_COMMENT}' => _T('form_comment'),
			'{$MODULE_URL}' => sanitizeStr($this->getModulePageURL([], false)) 
		]);

		// render the edit form with post details
		echo $this->moduleContext->adminPageRenderer->ParsePage('GLOBAL_ADMIN_PAGE_CONTENT', ['{$PAGE_CONTENT}' => $pageContent], true);
	}

	public function ModulePage() {
		// get post uid from request
		$postUid = $this->moduleContext->request->getParameter('postUid');
		
		// validate post uid
		validatePostInput($postUid);

		// handle the main edit requests
		if($this->moduleContext->request->isPost()) {
			$this->handleEditRequest($postUid);
		}
		// otherwise just render the form
		else {
			$this->handleEditPage($postUid);
		}
	}
}