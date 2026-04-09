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
			'{$FIELD_HTML}'                => _T('post_api_field_html'),
			'{$GET_THREAD_POSTS}'          => _T('post_api_get_thread_posts'),
			'{$RETURNS_JSON_THREAD}'       => _T('post_api_returns_json_thread'),
			'{$THREAD_UID_DESC}'           => _T('post_api_thread_uid_desc'),
			'{$RESPONSE}'                  => _T('post_api_response'),
			'{$THREAD_RESPONSE_DESC}'      => _T('post_api_thread_response_desc'),
		]);

		$html = $board->getBoardHead(_T('post_api_title'));
		$html .= $infoHtml;
		$html .= $board->getBoardFooter(false);

		echo $html;
	}

	/** Handle request for all posts from a thread. */
	private function handleThreadPostsRequest(): void {
		$threadUid = $this->moduleContext->request->getParameter('thread_uid', 'GET', '');

		if (empty($threadUid)) {
			renderJsonErrorPage('Missing thread_uid parameter', 400);
		}

		$posts = $this->moduleContext->threadRepository->getAllPostsFromThread($threadUid, false);

		if (!$posts) {
			renderJsonErrorPage('Thread not found', 404);
		}

		$postsData = [];
		foreach ($posts as $post) {
			$html = $this->renderPostHtml($post);
			$postsData[] = $this->buildPostData($post, $html);
		}

		$data = [
			'thread_uid' => $threadUid,
			'post_count' => count($postsData),
			'posts' => $postsData,
		];

		renderCachedJsonPage($data);
	}

	/** Handle request for a single post. */
	private function handleSinglePostRequest(): void {
		$postUid = (int) $this->moduleContext->request->getParameter('post_uid', 'GET', '');

		if ($postUid <= 0) {
			renderJsonErrorPage(_T('post_not_found'), 400);
		}

		$post = $this->moduleContext->postRepository->getPostByUid($postUid);

		if (!$post) {
			renderJsonErrorPage(_T('post_not_found'), 404);
		}

		$html = $this->renderPostHtml($post);
		$data = $this->buildPostData($post, $html);

		renderCachedJsonPage($data);
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
			'html' => $html,
		];
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

	/** Get the cache file path for a post UID. */
	private function getCachePath(int $postUid): string {
		return $this->cacheDir . $postUid . '.json';
	}

	/** Read cached JSON data for a post, or null if not cached. */
	private function readCache(int $postUid): ?array {
		$path = $this->getCachePath($postUid);
		if (!file_exists($path)) {
			return null;
		}

		$json = file_get_contents($path);
		if ($json === false) {
			return null;
		}

		$data = json_decode($json, true);
		return is_array($data) ? $data : null;
	}

	/** Write post API data to the filesystem cache. */
	private function writeCache(int $postUid, array $data): void {
		$path = $this->getCachePath($postUid);
		file_put_contents($path, json_encode($data, JSON_UNESCAPED_UNICODE), LOCK_EX);
	}
}
