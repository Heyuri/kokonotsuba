<?php

namespace Kokonotsuba\Modules\antiSpam;

require_once __DIR__ . '/antiSpamRepository.php';
require_once __DIR__ . '/antiSpamService.php';
require_once __DIR__ . '/antiSpamLib.php';

use BoardException;
use Kokonotsuba\ModuleClasses\abstractModuleAdmin;
use Kokonotsuba\Root\Constants\userRole;

class moduleAdmin extends abstractModuleAdmin {
	private antiSpamService $antiSpamService;
	private string $moduleUrl;

	public function getRequiredRole(): userRole {
		return $this->getConfig('AuthLevels.CAN_MANAGE_ANTI_SPAM_SYSTEM', userRole::LEV_MODERATOR);
	}

	public function getName(): string {
		return 'Anti-spam management system';
	}

	public function getVersion(): string {
		return 'NEW YEARZ';
	}

	public function initialize(): void {
		$this->moduleUrl = $this->getModulePageURL([], false);

		$this->moduleContext->moduleEngine->addRoleProtectedListener(
			$this->getRequiredRole(),
			'ManagePostsControls',
			function(string &$modControlSection, array &$post) {
				$this->renderFilterPostButton($modControlSection, $post);
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
			'LinksAboveBar',
			function(string &$linkHtml) {
				$this->onRenderLinksAboveBar($linkHtml);
			}
		);

		// set antispam service instance
		$this->antiSpamService = getAntiSpamService();
	}
	
	public function renderFilterPostButton(string &$modfunc, array $post): void {
		$filterPostUrl = $this->generateFilterPostUrl($post['post_uid']);

		$modfunc .= '<span class="adminFunctions adminFilterPostFunction">[<a href="' . htmlspecialchars($filterPostUrl) . '" title="Filter post content">FP</a>]</span>';
	}

	private function onRenderPostWidget(array &$widgetArray, array &$post): void {
		// generate filer post url
		$filterPostUrl = $this->generateFilterPostUrl($post['post_uid']);

		// build the widget entry
		$filterPostWidget = $this->buildWidgetEntry($filterPostUrl, 'filterPost', 'Filter post', '');

		// add the widget to the array
		$widgetArray[] = $filterPostWidget;
	}

	private function generateFilterPostUrl(int $postUid): string {
		// generate and return url for 'newEntryForm' page
		return $this->getModulePageURL(['viewPage' => 'newEntryForm', 'post_uid' => $postUid], false, true);
	}

	private function onRenderLinksAboveBar(string &$linkHtml): void {
		$linkHtml .= '<li class="adminNavLink"><a href="' . htmlspecialchars($this->moduleUrl) . '">Manage anti-spam system</a></li>';
	}

	public function ModulePage(): void {
		// handle POST requests.
		// these requests change something in the database, either deleting, creating, or modifying entries
		if(isPostRequest()) {
			$this->handleRequests();
		}
		// handle GET requests
		// these are typically pages for viewing entries, or forms
		elseif (isGetRequest()) {
			$this->handlePages();
		}
	}

	private function handleRequests(): void {
		// get the action parameter
		$action = $_POST['action'] ?? '';

		// add an entry
		if($action === 'addEntry') {
			$this->handleNewEntry();
		}
		// delete entries
		elseif($action === 'delete') {
			$this->handleDeletions();
		}
		// handle modifications
		elseif($action === 'update') {
			$this->handleModification();
		}

		// invalid action
		else {
			throw new BoardException("Invalid action.");
		}
	}

	private function handleNewEntry(): void {
		// normalize and validate input
		$fields = $this->normalizeSpamRuleInput($_POST);

		// require a non-empty pattern
		if ($fields['pattern'] === '') {
			redirect($this->moduleUrl);
		}

		// get the current staff's user id
		$createdBy = getIdFromSession();

		$this->moduleContext->transactionManager->run(function() use ($fields, $createdBy) {
			// add the entry
			$this->antiSpamService->addEntry(
				$fields['pattern'],
				$fields['matchType'],
				$fields['applySubject'],
				$fields['applyComment'],
				$fields['applyName'],
				$fields['applyEmail'],
				$fields['caseSensitive'],
				$fields['userMessage'],
				$fields['description'],
				$fields['action'],
				$fields['maxDistance'],
				$createdBy
			);
		});

		// then redirect
		redirect($this->moduleUrl);
	}

	private function handleDeletions(): void {
		// get all the IDs from the requests for entries that are to be deleted
		$entryIDs = $_POST['entryIDs'] ?? null;
		
		// database transaction to prevent potential race conditions
		$this->moduleContext->transactionManager->run(function() use($entryIDs) {
			// just redirect back to the index if none were selected
			if(empty($entryIDs)) {
				redirect($this->moduleUrl);
			}

			// delete the entries
			$this->antiSpamService->deleteEntries($entryIDs);
		});

		// redirect to index
		redirect($this->moduleUrl);
	}

	private function handleModification(): void {
		// get the target ID
		$entryId = $_POST['entryId'] ?? null;

		// just redirect if its null
		if (empty($entryId)) {
			redirect($this->moduleUrl);
		}

		// make sure its an integer
		$entryId = (int)$entryId;

		// normalize and validate input for update
		$fields = $this->normalizeSpamRuleInput($_POST, true);

		$this->moduleContext->transactionManager->run(function() use ($entryId, $fields) {
			// modify entry
			$this->antiSpamService->modifyEntry($entryId, $fields);
		});

		// redirect to the entry
		redirect($this->getEntryUrl($entryId));
	}

	private function normalizeSpamRuleInput(array $post, bool $isUpdate = false): array {
		$fields = [];

		// pattern
		if (!$isUpdate || array_key_exists('pattern', $post)) {
			$fields['pattern'] = isset($post['pattern'])
				? trim((string)$post['pattern'])
				: '';
		}

		// match type
		if (!$isUpdate || array_key_exists('matchType', $post)) {
			$allowedMatchTypes = ['contains', 'exact', 'fuzzy', 'regex'];
			$fields['matchType'] = in_array($post['matchType'] ?? '', $allowedMatchTypes, true)
				? $post['matchType']
				: 'contains';
		}

		// max distance
		if (!$isUpdate || array_key_exists('maxDistance', $post)) {
			if (array_key_exists('maxDistance', $post)) {
				$fields['maxDistance'] = $post['maxDistance'] === ''
					? null
					: max(0, (int)$post['maxDistance']);

				// prevent values greater than 5, as those are prone to false positives
				$fields['maxDistance'] = min(4, $fields['maxDistance']);
			} else {
				$fields['maxDistance'] = null;
			}
		}

		// match fields checkboxes
		if (!$isUpdate || array_key_exists('matchField', $post)) {
			$matchField = $post['matchField'] ?? [];

			if (!is_array($matchField)) {
				$matchField = [];
			}

			$fields['applySubject'] = in_array('subject', $matchField, true) ? 1 : 0;
			$fields['applyComment'] = in_array('comment', $matchField, true) ? 1 : 0;
			$fields['applyName']    = in_array('name', $matchField, true) ? 1 : 0;
			$fields['applyEmail']   = in_array('email', $matchField, true) ? 1 : 0;
		}

		// case sensitivity
		$fields['caseSensitive'] = !empty($post['matchCase']) ? 1 : 0;

		// enabled/disable
		$fields['isActive'] = !empty($post['isActive']) ? 1 : 0;

		// action
		if (!$isUpdate || array_key_exists('spamAction', $post)) {
			$allowedActions = ['reject', 'mute', 'ban'];
			$fields['action'] = in_array($post['spamAction'] ?? '', $allowedActions, true)
				? $post['spamAction']
				: 'reject';
		}

		// description
		if (!$isUpdate || array_key_exists('description', $post)) {
			$fields['description'] = array_key_exists('description', $post) && trim($post['description']) !== ''
				? trim($post['description'])
				: null;
		}

		// user message
		if (!$isUpdate || array_key_exists('userMessage', $post)) {
			$fields['userMessage'] = array_key_exists('userMessage', $post) && trim($post['userMessage']) !== ''
				? trim($post['userMessage'])
				: null;
		}

		return $fields;
	}

	private function handlePages(): void {
		// get the page view
		$viewPage = $_GET['viewPage'] ?? '';
		
		// view the entry creation form
		if($viewPage === 'newEntryForm') {
			$this->drawNewEntryForm();
		}
		// handle viewing a specific entry
		elseif($viewPage === 'view') {
			$this->drawView();
		}

		// handle index
		else {
			$this->drawIndex();
		}
	}

	private function drawNewEntryForm(): void {
		// initialize pattern value as an empty string so we can set it if we need to
		$patternValue = '';
		
		// get the target post uid from request we so can optionally auto-fill the pattern field
		$postUid = $_GET['post_uid'] ?? null;

		// if the post uid is set then try to get the comment and set the pattern value to it
		if(!empty($postUid)) {
			// get the post
			$post = $this->moduleContext->postRepository->getPostByUid($postUid, true);

			// set the pattern value to the comment so it shows up the comment in the form
			$patternValue = $post['com'] ?? '';

			// replace breaklines with new lines so line breaks are preserved
			$patternValue = str_replace(
				['<br>', '<br/>', '<br />'],
				"\n",
				$patternValue
			);

			// strip it of tags
			$patternValue = strip_tags($patternValue);

			// decode html elements just in case
			$patternValue = html_entity_decode($patternValue);
		}

		// generate the form
		$form = $this->moduleContext->adminPageRenderer->ParseBlock('NEW_ENTRY_FORM', 
			[
				'{$MODULE_URL}' => htmlspecialchars($this->moduleUrl),
				'{$PATTERN_VALUE}' => $patternValue
			]
		);

		// then render the page
		$this->renderPage($form);
	}

	private function drawView(): void {
		// get the id
		$id = $_GET['id'] ?? null;

		// fetch entry from database
		$entry = $this->antiSpamService->getEntry($id);

		// get template values
		$templateValues = $this->generateTemplateRowValues($entry);

		// add module url
		$templateValues['{$MODULE_URL}'] = htmlspecialchars($this->moduleUrl);

		// add the edit form-specific templates
		$templateValues = array_merge(
			$templateValues, 
			$this->generateEditFormTemplateValues($entry)
		);

		// generate the table html
		$entryHtml = $this->moduleContext->adminPageRenderer->ParseBlock('ANTI_SPAM_ENTRY', $templateValues);

		// render the page
		$this->renderPage($entryHtml);
	}

	private function generateEditFormTemplateValues(array $entry): array {
		$values = [];

		// spam_string_rules.match_type
		$values['{$CONTAINS_SELECTED}'] = ($entry['match_type'] === 'contains');
		$values['{$EXACT_SELECTED}']    = ($entry['match_type'] === 'exact');
		$values['{$FUZZY_SELECTED}']    = ($entry['match_type'] === 'fuzzy');
		$values['{$REGEX_SELECTED}']    = ($entry['match_type'] === 'regex');

		// spam_string_rules.apply_subject
		$values['{$SUBJECT_SELECTED}'] = !empty($entry['apply_subject']);

		// spam_string_rules.apply_comment
		$values['{$COMMENT_SELECTED}'] = !empty($entry['apply_comment']);

		// spam_string_rules.apply_name
		$values['{$NAME_SELECTED}'] = !empty($entry['apply_name']);

		// spam_string_rules.apply_email
		$values['{$EMAIL_SELECTED}'] = !empty($entry['apply_email']);

		// spam_string_rules.case_sensitive
		$values['{$CASE_SENSITIVE}'] = !empty($entry['case_sensitive']);

		// spam_string_rules.action
		$values['{$REJECT_SELECTED}'] = ($entry['action'] === 'reject');
		$values['{$MUTE_SELECTED}']   = ($entry['action'] === 'mute');
		$values['{$BAN_SELECTED}']    = ($entry['action'] === 'ban');

		return $values;
	}

	private function drawIndex(): void {
		// get the entries per page config value
		// we'll just use the action log config value since they're similar html-wise
		$entriesPerPage = $this->getConfig('ACTIONLOG_MAX_PER_PAGE');

		// get current page
		$page = $_GET['page'] ?? 0;

		// fetch anti-spam rule entries from database
		$entries = $this->antiSpamService->getEntries($entriesPerPage, $page);

		// get template values
		$templateValues = $this->generateIndexTemplateValues($entries);

		// generate the table html
		$indexHtml = $this->moduleContext->adminPageRenderer->ParseBlock('ANTI_SPAM_INDEX', $templateValues);

		// get the total number of spam rulesets
		$totalEntries = $this->antiSpamService->getTotalEntries();

		// generate pager
		$pagerHtml = drawPager($entriesPerPage, $totalEntries, $this->moduleUrl);

		// render the page
		$this->renderPage($indexHtml, $pagerHtml);
	}

	private function generateIndexTemplateValues(array $entries): array {
		// init template array for loop
		$templateRows = [];

		// loop through and form template rows
		foreach($entries as $e) {
			// assemble template row
			$templateRows[] = $this->generateTemplateRowValues($e);
		}

		// return formed template values
		return [
			'{$ROWS}' => $templateRows,
			'{$MODULE_URL}' => htmlspecialchars($this->moduleUrl),
		];
	}

	private function generateTemplateRowValues(array $entry): array {
		// generate url for viewing the whole entry
		$entryUrl = $this->getEntryUrl($entry['id']);
		
		// generate fields list
		$fieldsList = $this->generateAppliedFields($entry);

		// assemble values
		return
			[
				'{$ID}' => htmlspecialchars($entry['id']),
				'{$PATTERN}' => htmlspecialchars($entry['pattern']),
				'{$IS_ACTIVE}' => htmlspecialchars($entry['is_active']),
				'{$DESCRIPTION}' => htmlspecialchars($entry['description']),
				'{$ACTION}' => htmlspecialchars($entry['action']),
				'{$MATCH_TYPE}' => htmlspecialchars($entry['match_type']),
				'{$CREATED_BY}' => htmlspecialchars($entry['created_by_username']),
				'{$CREATED_AT}' => htmlspecialchars($entry['created_at']),
				'{$APPLIED_FIELDS}' => htmlspecialchars($fieldsList),
				'{$CASE_SENSITIVE}' => htmlspecialchars($entry['case_sensitive']),
				'{$USER_MESSAGE}' => htmlspecialchars($entry['user_message']),
				'{$MAX_DISTANCE}' => htmlspecialchars($entry['max_distance']),
				'{$VIEW_ENTRY_URL}' => htmlspecialchars($entryUrl)
			];
	}

	private function getEntryUrl(int $entryId): string {
		return $this->getModulePageURL([
			'viewPage' => 'view',
			'id' => $entryId
		], false);
	}

	private function generateAppliedFields(array $entry): string {
		$fields = [];

		// Map rule flags to human-readable field names
		$map = [
			'apply_subject' => 'subject',
			'apply_comment' => 'comment',
			'apply_name' => 'name',
			'apply_email' => 'email'
		];

		// Collect enabled fields
		foreach($map as $flag => $label){
			if(!empty($entry[$flag])){
				$fields[] = $label;
			}
		}

		// Return comma-separated list (or empty string if none apply)
		return implode(', ', $fields);
	}

	private function renderPage(string $pageContentHtml, string $pagerHtml = ''): void {
		// assign placeholder values
		$pageContent = [
			'{$PAGE_CONTENT}' => $pageContentHtml,
			'{$PAGER}' => $pagerHtml
		];

		// parse the page
		$pageHtml = $this->moduleContext->adminPageRenderer->ParsePage('GLOBAL_ADMIN_PAGE_CONTENT', $pageContent, true);
		
		// echo output to browser
		echo $pageHtml;
	}
}