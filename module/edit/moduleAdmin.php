<?php

namespace Kokonotsuba\Modules\edit;

require_once __DIR__ . '/postRevisionService.php';

use Kokonotsuba\action_log\actionType;
use Kokonotsuba\database\databaseConnection;
use Kokonotsuba\error\BoardException;
use Kokonotsuba\module_classes\abstractModuleAdmin;
use Kokonotsuba\module_classes\traits\AuditableTrait;
use Kokonotsuba\post\Post;
use Kokonotsuba\userRole;

use const Kokonotsuba\GLOBAL_BOARD_UID;

use function Kokonotsuba\libraries\_T;
use function Kokonotsuba\libraries\getCsrfHiddenInput;
use function Kokonotsuba\libraries\getRoleLevelFromSession;
use function Kokonotsuba\libraries\rebuildBoardsFromPosts;
use function Kokonotsuba\libraries\requirePostWithCsrf;
use function Kokonotsuba\libraries\validatePostInput;
use function Puchiko\request\redirect;
use function Puchiko\strings\sanitizeStr;

class moduleAdmin extends abstractModuleAdmin {
	use AuditableTrait;

	/** GET pageName that draws a post's edit history. */
	private const REVISIONS_PAGE = 'revisions';

	private postRevisionService $revisions;

	/**
	 * The staff half of the edit module: a post's edit history. Editing itself, for staff and
	 * readers alike, is moduleMain's.
	 *
	 * Reading the history and restoring from it are separate capabilities, so the gate here is
	 * the lower of them and every path below asks for the one it needs.
	 */
	public function getRequiredRole(): userRole {
		$viewRole = $this->getViewRevisionsRole();
		$restoreRole = $this->getRestoreRevisionsRole();

		return $viewRole->isLessThan($restoreRole) ? $viewRole : $restoreRole;
	}

	private function getViewRevisionsRole(): userRole {
		return $this->getConfig('AuthLevels.CAN_VIEW_POST_REVISIONS', userRole::LEV_JANITOR);
	}

	private function getRestoreRevisionsRole(): userRole {
		return $this->getConfig('AuthLevels.CAN_RESTORE_POST_REVISIONS', userRole::LEV_MODERATOR);
	}

	private function holdsRole(userRole $role): bool {
		return !getRoleLevelFromSession()->isLessThan($role);
	}

	/** Refuse a request from someone the module let in for the other half of it. */
	private function assertRole(userRole $role): void {
		if (!$this->holdsRole($role)) {
			throw new BoardException(_T('post_revision_no_permission'), 403);
		}
	}

	public function getName(): string {
		return 'Mod editing tools';
	}

	public function getVersion(): string {
		return 'Twendy twendy sex';
	}

	public function initialize(): void {
		$this->revisions = new postRevisionService(new postRevisionRepository(
			databaseConnection::getInstance(),
			$this->moduleContext->getTableName('POST_EDIT_REVISION_TABLE'),
			$this->moduleContext->getTableName('ACCOUNT_TABLE')
		));

		$this->moduleContext->moduleEngine->addRoleProtectedListener(
			$this->getViewRevisionsRole(),
			'ModeratePostWidget',
			function(array &$widgetArray, Post &$post) {
				$widgetArray[] = $this->buildWidgetEntry(
					$this->getRevisionsUrl($post->getUid(), false),
					'viewPostRevisions',
					_T('view_post_revisions'),
					''
				);
			}
		);
	}

	/** This module's history page for a post. */
	private function getRevisionsUrl(int $postUid, bool $forHtml = true): string {
		return $this->getModulePageURL(['postUid' => $postUid, 'pageName' => self::REVISIONS_PAGE], $forHtml, true);
	}

	/** Fetch a post, or throw if the uid does not name one. */
	private function getPost(int $postUid): Post {
		$post = $this->moduleContext->postRepository->getPostByUID(
			$postUid,
			$this->moduleContext->postRenderingPolicy->viewDeleted()
		);

		validatePostInput($post, false);

		return $post;
	}

	// ─── Edit history ─────────────────────────────────────────────

	/**
	 * A post's edit history, newest first.
	 *
	 * Each entry is the post as it stood before one edit, so restoring the newest undoes the last
	 * edit. A restore is an edit of its own and records its own revision, which is what keeps the
	 * history a record rather than a trap.
	 */
	private function drawRevisionsPage(int $postUid): void {
		$this->assertRole($this->getViewRevisionsRole());

		$post = $this->getPost($postUid);
		$canRestore = $this->holdsRole($this->getRestoreRevisionsRole());

		$listHtml = '';
		foreach ($this->revisions->getRevisionsForPost($postUid) as $revision) {
			$listHtml .= $this->renderRevision($revision, $canRestore);
		}

		$pageContent = $this->moduleContext->adminPageRenderer->ParseBlock('POST_REVISIONS', [
			'{$PAGE_TITLE}' => sanitizeStr(_T('post_revisions_title', (string)$post->getNumber())),
			'{$POST_UID}' => $postUid,
			'{$MODULE_URL}' => sanitizeStr($this->getModulePageURL([], false)),
			'{$CSRF_TOKEN}' => getCsrfHiddenInput(),
			'{$REVISION_LIST}' => $listHtml,
			'{$NO_REVISIONS_TEXT}' => sanitizeStr(_T('post_revisions_none')),
		]);

		echo $this->moduleContext->adminPageRenderer->ParsePage('GLOBAL_ADMIN_PAGE_CONTENT', ['{$PAGE_CONTENT}' => $pageContent], true);
	}

	/** One revision, with the values it holds and the button that puts them back. */
	private function renderRevision(array $revision, bool $canRestore): string {
		$editor = $revision['edited_by_username'] ?? null;

		return $this->moduleContext->adminPageRenderer->ParseBlock('POST_REVISION_ENTRY', [
			'{$REVISION_ID}' => (int)$revision['id'],
			'{$REVISION_HEADING}' => sanitizeStr(_T(
				'post_revision_heading',
				strip_tags($this->moduleContext->postDateFormatter->formatFromDateString((string)($revision['edited_at'] ?? '')))
			)),
			'{$REVISION_BY}' => sanitizeStr(_T('post_revision_by', $editor ?: _T('post_revision_by_poster'))),
			'{$CAN_RESTORE}' => $canRestore,
			'{$RESTORE_LABEL}' => sanitizeStr(_T('post_revision_restore')),
			'{$RESTORE_TITLE}' => sanitizeStr(_T('post_revision_restore_title')),
			'{$REVISION_FIELDS}' => $this->renderRevisionFields($revision),
		]);
	}

	/** The revision's stored values, one labelled row each. */
	private function renderRevisionFields(array $revision): string {
		$labels = [
			'name' => 'form_name',
			'email' => 'form_email',
			'sub' => 'form_topic',
			'com' => 'form_comment',
			'tag' => 'form_tag',
		];

		$html = '';

		foreach ($labels as $field => $labelKey) {
			$value = (string)($revision[$field] ?? '');

			$html .= $this->moduleContext->adminPageRenderer->ParseBlock('POST_REVISION_FIELD', [
				'{$FIELD_LABEL}' => sanitizeStr(_T($labelKey)),
				'{$FIELD_VALUE}' => $value === ''
					? '<i>' . sanitizeStr(_T('post_revision_empty')) . '</i>'
					: nl2br(sanitizeStr($value)),
			]);
		}

		return $html;
	}

	/**
	 * Put a revision's values back on its post.
	 *
	 * The post as it stands is recorded first, so a restore can itself be undone, and the board
	 * is rebuilt afterwards exactly as an ordinary edit rebuilds it.
	 */
	private function handleRestoreRequest(int $postUid): void {
		$this->assertRole($this->getRestoreRevisionsRole());

		$revisionId = (int)$this->moduleContext->request->getParameter('revisionId', 'POST', 0);
		$revision = $revisionId > 0 ? $this->revisions->getRevisionById($revisionId) : false;

		if (!$revision || (int)$revision['post_uid'] !== $postUid) {
			throw new BoardException(_T('post_revision_not_found'), 404);
		}

		$restoredPost = null;

		$this->moduleContext->transactionManager->run(function() use ($postUid, $revision, &$restoredPost) {
			$post = $this->getPost($postUid);

			$this->revisions->record($post, (int)$this->moduleContext->currentUserId);
			$this->moduleContext->postRepository->updatePost($postUid, $this->revisions->valuesOf($revision));

			$restoredPost = $this->getPost($postUid);
		});

		$this->logAction(
			"Restored post No.{$restoredPost->getNumber()} from revision #{$revisionId}",
			$restoredPost->getBoardUID() ?? GLOBAL_BOARD_UID,
			actionType::POST_EDIT
		);

		rebuildBoardsFromPosts([$postUid], $this->moduleContext->postService);

		redirect($this->getRevisionsUrl($postUid, false));
	}

	public function ModulePage() {
		// get post uid from request
		$postUid = $this->moduleContext->request->getParameter('postUid');

		// validate post uid
		validatePostInput($postUid);

		if($this->moduleContext->request->isPost()) {
			requirePostWithCsrf($this->moduleContext->request);

			if($this->moduleContext->request->getParameter('action', 'POST', '') !== 'restoreRevision') {
				throw new BoardException(_T('post_revision_not_found'), 400);
			}

			$this->handleRestoreRequest((int)$postUid);
			return;
		}

		// the post's edit history, which is all this half draws now
		$this->drawRevisionsPage((int)$postUid);
	}
}
