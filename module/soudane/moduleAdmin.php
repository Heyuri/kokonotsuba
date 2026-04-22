<?php

namespace Kokonotsuba\Modules\soudane;

require_once __DIR__ . '/soudaneRepository.php';
require_once __DIR__ . '/soudaneService.php';

use Kokonotsuba\database\databaseConnection;
use Kokonotsuba\error\BoardException;
use Kokonotsuba\module_classes\abstractModuleAdmin;
use Kokonotsuba\module_classes\traits\listeners\PostControlHooksTrait;
use Kokonotsuba\post\Post;
use Kokonotsuba\userRole;

use function Kokonotsuba\libraries\getCsrfHiddenInput;
use function Kokonotsuba\libraries\html\drawPager;
use function Kokonotsuba\libraries\isActiveStaffSession;
use function Kokonotsuba\libraries\requirePostWithCsrf;
use function Kokonotsuba\libraries\validatePostInput;
use function Puchiko\request\redirect;
use function Puchiko\strings\sanitizeStr;

class moduleAdmin extends abstractModuleAdmin {
	use PostControlHooksTrait;

	private soudaneService $soudaneService;
	private string $moduleUrl;

	public function getRequiredRole(): userRole {
		return $this->getConfig('AuthLevels.CAN_VIEW_VOTES', userRole::LEV_MODERATOR);
	}

	public function getName(): string {
		return 'Soudane vote management';
	}

	public function getVersion(): string {
		return 'Koko BBS Release';
	}

	public function initialize(): void {
		$this->moduleUrl = $this->getModulePageURL([], false);

		$databaseConnection = databaseConnection::getInstance();
		$soudaneTable = getDatabaseSettings()['SOUDANE_TABLE'];
		$soudaneRepository = new soudaneRepository($databaseConnection, $soudaneTable);
		$this->soudaneService = new soudaneService($soudaneRepository);

		$this->registerPostWidgetHook('onRenderPostWidget');
	}

	private function onRenderPostWidget(array &$widgetArray, Post &$post): void {
		$viewVotesUrl = $this->getModulePageURL(['postUid' => $post->getUid()], false, true);

		$widgetArray[] = $this->buildWidgetEntry(
			$viewVotesUrl,
			'viewVotes',
			'View votes',
			''
		);
	}

	public function ModulePage(): void {
		if ($this->moduleContext->request->isPost()) {
			requirePostWithCsrf($this->moduleContext->request);
			$this->handleDeletions();
			return;
		}

		$this->drawIndex();
	}

	private function handleDeletions(): void {
		$entryIDs = $_POST['entryIDs'] ?? null;
		$postUid = (int) ($_POST['postUid'] ?? 0);

		if (empty($entryIDs) || empty($postUid)) {
			redirect($this->getModulePageURL(['postUid' => $postUid], false));
			return;
		}

		$this->soudaneService->deleteVotesByIds($entryIDs);

		redirect($this->getModulePageURL(['postUid' => $postUid], false));
	}

	private function drawIndex(): void {
		$postUid = (int) $this->moduleContext->request->getParameter('postUid', 'GET', 0);

		if (empty($postUid)) {
			throw new BoardException('No post UID provided.');
		}

		validatePostInput(
			$this->moduleContext->postRepository->getPostByUid($postUid, isActiveStaffSession()),
			false,
			404
		);

		$postNumber = $this->moduleContext->postRepository->resolvePostNumberFromUID($postUid);

		$entriesPerPage = 50;
		$page = (int) ($_GET['page'] ?? 0);

		$entries = $this->soudaneService->getVotesPaginated($postUid, $entriesPerPage, $page);
		$totalEntries = $this->soudaneService->getTotalVotesForPost($postUid);

		$templateRows = [];
		foreach ($entries as $entry) {
			$templateRows[] = [
				'{$ID}' => sanitizeStr($entry['id']),
				'{$POST_UID}' => sanitizeStr($entry['post_uid']),
				'{$IP_ADDRESS}' => sanitizeStr($entry['ip_address']),
				'{$VOTE_TYPE}' => $entry['yeah'] ? 'Yeah' : 'Nope',
				'{$DATE_ADDED}' => $this->moduleContext->postDateFormatter->formatFromDateString($entry['date_added']),
			];
		}

		$currentUrl = $this->getModulePageURL(['postUid' => $postUid], false);

		$indexHtml = $this->moduleContext->adminPageRenderer->ParseBlock('SOUDANE_VOTE_INDEX', [
			'{$ROWS}' => $templateRows,
			'{$MODULE_URL}' => sanitizeStr($currentUrl),
			'{$POST_UID}' => sanitizeStr((string) $postUid),
			'{$POST_NUMBER}' => sanitizeStr((string) $postNumber),
			'{$CSRF_TOKEN}' => getCsrfHiddenInput(),
		]);

		$pagerHtml = drawPager($entriesPerPage, $totalEntries, $currentUrl, $this->moduleContext->request);

		$pageHtml = $this->moduleContext->adminPageRenderer->ParsePage('GLOBAL_ADMIN_PAGE_CONTENT', [
			'{$PAGE_CONTENT}' => $indexHtml,
			'{$PAGER}' => $pagerHtml,
		], true);

		echo $pageHtml;
	}
}