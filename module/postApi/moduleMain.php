<?php

namespace Kokonotsuba\Modules\postApi;

use Kokonotsuba\module_classes\abstractModuleMain;
use Kokonotsuba\module_classes\traits\listeners\FormFuncsListenerTrait;
use Kokonotsuba\module_classes\traits\listeners\ModuleHeaderListenerTrait;
use Kokonotsuba\PMCLibrary;
use Kokonotsuba\post\Post;
use Kokonotsuba\renderers\postRenderer;

use function Kokonotsuba\libraries\_T;
use function Kokonotsuba\libraries\searchBoardArrayForBoard;
use function Puchiko\json\renderCachedJsonPage;
use function Puchiko\json\renderJsonErrorPage;
use function Puchiko\strings\sanitizeStr;

class moduleMain extends abstractModuleMain {
	use FormFuncsListenerTrait;
	use ModuleHeaderListenerTrait;

	/** Resolved filesystem path to the cache directory. */
	private string $cacheDir;

	public function getName(): string {
		return 'Post API';
	}

	public function getVersion(): string {
		return '1.0';
	}

	public function initialize(): void {
		$this->addFormFuncLink($this->getModulePageURL(['pageName' => 'info'], false), _T('post_api_link'));
		$this->listenModuleHeader('onGenerateModuleHeader');
	}

	/** Inject the API base URL meta tag into the page header. */
	private function onGenerateModuleHeader(string &$moduleHeader): void {
		$apiUrl = $this->getModulePageURL();
		$fetchingText = sanitizeStr(_T('post_api_fetching'));

		$moduleHeader .= '<meta name="postApiUrl" content="' . $apiUrl . '">';
		$moduleHeader .= '<meta name="postApiFetchingText" content="' . $fetchingText . '">';
	}

	/** Module page — routes between info page and JSON API endpoints. */
	public function ModulePage(): void {
		$pageName = $this->moduleContext->request->getParameter('pageName', 'GET', '');

		if ($pageName === 'info') {
			$this->renderInfoPage();
			return;
		}

		if ($pageName === 'thread') {
			$this->handleThreadPostsRequest();
			return;
		}

		if ($pageName === 'threads') {
			$this->handleThreadListRequest();
			return;
		}

		// Default: single post API
		$this->handleSinglePostRequest();
	}

	/** Render the API documentation info page. */
	private function renderInfoPage(): void {
		$board = $this->moduleContext->board;
		$baseApiUrl = htmlspecialchars($this->getModulePageURL([], false));

		$this->moduleContext->adminPageRenderer->setTemplate('admin');
		$infoHtml = $this->moduleContext->adminPageRenderer->ParseBlock('POST_API_INFO', [
			'{$API_BASE_URL}'              => $baseApiUrl,
			'{$POST_API_TITLE}'            => _T('post_api_title'),
			'{$POST_API_DESCRIPTION}'      => _T('post_api_description'),
			'{$GET_SINGLE_POST}'           => _T('post_api_get_single_post'),
			'{$RETURNS_JSON_POST}'         => _T('post_api_returns_json_post'),
			'{$PARAMETERS}'                => _T('post_api_parameters'),
			'{$TH_PARAMETER}'              => _T('post_api_th_parameter'),
			'{$TH_TYPE}'                   => _T('post_api_th_type'),
			'{$TH_DESCRIPTION}'            => _T('post_api_th_description'),
			'{$TH_FIELD}'                  => _T('post_api_th_field'),
			'{$POST_UID_DESC}'             => _T('post_api_post_uid_desc'),
			'{$RESPONSE_FIELDS}'           => _T('post_api_response_fields'),
			'{$FIELD_POST_UID}'            => _T('post_api_field_post_uid'),
			'{$FIELD_TIMESTAMP}'           => _T('post_api_field_timestamp'),
			'{$FIELD_NAME}'                => _T('post_api_field_name'),
			'{$FIELD_TRIPCODE}'            => _T('post_api_field_tripcode'),
			'{$FIELD_SECURE_TRIPCODE}'     => _T('post_api_field_secure_tripcode'),
			'{$FIELD_CAPCODE}'             => _T('post_api_field_capcode'),
			'{$FIELD_EMAIL}'               => _T('post_api_field_email'),
			'{$FIELD_SUBJECT}'             => _T('post_api_field_subject'),
			'{$FIELD_COMMENT}'             => _T('post_api_field_comment'),
			'{$FIELD_PARENT_THREAD_UID}'   => _T('post_api_field_parent_thread_uid'),
			'{$FIELD_PARENT_POST_NUMBER}'  => _T('post_api_field_parent_post_number'),
			'{$FIELD_HTML}'                => _T('post_api_field_html'),
			'{$GET_THREAD_POSTS}'          => _T('post_api_get_thread_posts'),
			'{$RETURNS_JSON_THREAD}'       => _T('post_api_returns_json_thread'),
			'{$THREAD_UID_DESC}'           => _T('post_api_thread_uid_desc'),
			'{$PAGE_PARAM_DESC}'           => _T('post_api_page_param_desc'),
			'{$RESPONSE}'                  => _T('post_api_response'),
			'{$THREAD_RESPONSE_DESC}'      => _T('post_api_thread_response_desc'),
			'{$BOARD_UID_DESC}'            => _T('post_api_board_uid_desc'),
			'{$GET_THREAD_LIST}'           => _T('post_api_get_thread_list'),
			'{$RETURNS_JSON_THREAD_LIST}'  => _T('post_api_returns_json_thread_list'),
			'{$THREAD_LIST_RESPONSE_DESC}' => _T('post_api_thread_list_response_desc'),
			'{$THREAD_LIST_FIELD_THREAD_UID}' => _T('post_api_thread_list_field_thread_uid'),
			'{$THREAD_LIST_FIELD_SUBJECT}' => _T('post_api_thread_list_field_subject'),
			'{$THREAD_LIST_FIELD_LAST_BUMP_TIME}' => _T('post_api_thread_list_field_last_bump_time'),
			'{$THREAD_LIST_FIELD_CREATED_TIME}' => _T('post_api_thread_list_field_created_time'),
			'{$THREAD_LIST_FIELD_POST_COUNT}' => _T('post_api_thread_list_field_post_count'),
		]);

		$html = $board->getBoardHead(_T('post_api_title'));
		$html .= $infoHtml;
		$html .= $board->getBoardFooter(false);

		echo $html;
	}

	/** Handle request for posts from a thread (paginated). */
	private function handleThreadPostsRequest(): void {
		$threadUid = $this->moduleContext->request->getParameter('thread_uid', 'GET', '');

		if (empty($threadUid)) {
			renderJsonErrorPage('Missing thread_uid parameter', 400);
		}

		$page = max(0, (int) $this->moduleContext->request->getParameter('page', 'GET', '0'));
		$repliesPerPage = (int) $this->moduleContext->board->getConfigValue('REPLIES_PER_PAGE', 200);

		$posts = $this->moduleContext->threadRepository->getPostsFromThread($threadUid, false, $repliesPerPage, $page * $repliesPerPage);

		if (!$posts) {
			renderCachedJsonPage('Thread not found', 60, 404);
		}

		$postsData = [];
		foreach ($posts as $post) {
			$html = $this->renderPostHtml($post);
			$postsData[] = $this->buildPostData($post, $html);
		}

		$data = [
			'thread_uid' => $threadUid,
			'page' => $page,
			'post_count' => count($postsData),
			'posts' => $postsData,
		];

		renderCachedJsonPage($data);
	}

	/** Handle request for a paginated list of thread UIDs sorted by creation time. */
	private function handleThreadListRequest(): void {
		$page = max(0, (int) $this->moduleContext->request->getParameter('page', 'GET', '0'));
		$boardUid = (int) $this->moduleContext->request->getParameter('board_uid', 'GET', '0');

		if ($boardUid > 0) {
			$board = searchBoardArrayForBoard($boardUid);
			if (!$board) {
				renderJsonErrorPage(_T('board_not_found'), 404);
			}
		} else {
			$board = $this->moduleContext->board;
		}

		$threadsPerPage = (int) $board->getConfigValue('PAGE_DEF', 15);

		$threadPreviews = $this->moduleContext->threadService->getThreadPreviewsFromBoard(
			$board,
			0,
			$threadsPerPage,
			$page * $threadsPerPage,
			false,
			'thread_created_time',
			true
		);

		$threads = [];
		foreach ($threadPreviews as $preview) {
			$thread = $preview->getThread();
			$op = $preview->getOpeningPost();

			$threads[] = [
				'thread_uid' => $thread->getUid(),
				'subject' => $op ? $op->getSubject() : '',
				'last_bump_time' => $thread->getLastBumpTime(),
				'thread_created_time' => $thread->getCreatedTime(),
				'post_count' => $thread->getPostCount(),
			];
		}

		$data = [
			'page' => $page,
			'threads_per_page' => $threadsPerPage,
			'thread_count' => count($threads),
			'threads' => $threads,
		];

		renderCachedJsonPage($data, 60);
	}

	/** Handle request for a single post. */
	private function handleSinglePostRequest(): void {
		$postUid = (int) $this->moduleContext->request->getParameter('post_uid', 'GET', '');

		if ($postUid <= 0) {
			renderCachedJsonPage(_T('post_not_found'), 3600, 400);
		}

		$post = $this->moduleContext->postRepository->getCorePostByUid($postUid);

		if (!$post) {
			renderCachedJsonPage(_T('post_not_found'), 60, 404);
		}

		$html = $this->renderPostHtml($post);
		$data = $this->buildPostData($post, $html);

		renderCachedJsonPage($data, 60);
	}

	/** Build the JSON-safe data array for a post. */
	private function buildPostData(Post $post, string $html): array {
		return [
			'timestamp' => $post->getRoot(),
			'post_uid' => $post->getUid(),
			'name' => $post->getName(),
			'tripcode' => $post->getTripcode(),
			'secure_tripcode' => $post->getSecureTripcode(),
			'capcode' => $post->getCapcode(),
			'email' => $post->getEmail(),
			'subject' => $post->getSubject(),
			'comment' => $post->getComment(),
			'parent_thread_uid' => $post->getThreadUid(),
			'parent_post_number' => $post->getOpNumber(),
			'attachments' => $this->buildAttachmentsData($post),
			'html' => $html,
		];
	}

	/** Build the attachments array for the API response. */
	private function buildAttachmentsData(Post $post): array {
		$attachments = [];

		foreach ($post->getAttachments() as $id => $att) {
			$attachments[] = [
				'file_id' => $id,
				'file_name' => $att['fileName'] ?? '',
				'file_extension' => $att['fileExtension'] ?? '',
				'file_size' => (int)($att['fileSize'] ?? 0),
				'file_width' => (int)($att['fileWidth'] ?? 0),
				'file_height' => (int)($att['fileHeight'] ?? 0),
				'thumb_width' => (int)($att['thumbWidth'] ?? 0),
				'thumb_height' => (int)($att['thumbHeight'] ?? 0),
				'mime_type' => $att['mimeType'] ?? '',
				'md5' => $att['fileMd5'] ?? '',
			];
		}

		return $attachments;
	}

	/** Render a post to full HTML using the postRenderer pipeline. */
	private function renderPostHtml(Post $post): string {
		// Resolve the board for this post
		$board = searchBoardArrayForBoard($post->getBoardUID());

		if (!$board) {
			$board = $this->moduleContext->board;
		}

		$config = $board->loadBoardConfig();
		$boardUrl = $board->getBoardURL();

		// Build the post renderer with all the standard rendering logic
		$postRenderer = new postRenderer(
			$board,
			$config,
			$this->moduleContext->moduleEngine,
			$this->moduleContext->templateEngine,
			[],
			$this->moduleContext->request
		);

		// Fetch and set quote links for this post
		$quoteLinks = $this->moduleContext->quoteLinkService->getQuoteLinksByPostUids(
			[$post->getUid()]
		);
		$postRenderer->setQuoteLinks($quoteLinks);

		// Determine thread number
		$threadNumber = $post->getOpNumber();

		// Render using the full post rendering pipeline
		$templateValues = [];
		return $postRenderer->render(
			$post,
			$templateValues,
			$threadNumber,
			false,           // killSensor
			[$post],         // threadPosts
			false,           // adminMode
			'',              // postFormExtra
			'',              // warnHidePost
			0,               // replyCount
			false,           // threadMode
			$boardUrl,       // crossLink
			false            // renderAsOp
		);
	}
}
