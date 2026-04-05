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

class moduleMain extends abstractModuleMain {
	use FormFuncsListenerTrait;
	use ModuleHeaderListenerTrait;

	private const CACHE_DIR_NAME = 'post_api_cache';

	/** Resolved filesystem path to the cache directory. */
	private string $cacheDir;

	public function getName(): string {
		return 'Post API';
	}

	public function getVersion(): string {
		return '1.0';
	}

	public function initialize(): void {
		$this->cacheDir = getBackendGlobalDir() . self::CACHE_DIR_NAME . '/';
		$this->ensureCacheDir();

		$this->registerTranslations();

		$this->listenFormFuncs('onRenderFormFuncs');
		$this->listenModuleHeader('onGenerateModuleHeader');
	}

	/** Create the cache directory if it does not exist. */
	private function ensureCacheDir(): void {
		if (!is_dir($this->cacheDir)) {
			mkdir($this->cacheDir, 0755, true);
		}
	}

	private function registerTranslations(): void {
		PMCLibrary::getLanguageInstance()->attachLanguage([
			'post_api_link'     => 'Post API',
			'post_api_fetching' => 'Fetching post...',
		]);
	}

	/** Inject the "Post API" link into the formfuncs div. */
	private function onRenderFormFuncs(string &$formFuncsHtml): void {
		$url = $this->getModulePageURL(['pageName' => 'info']);
		$formFuncsHtml .= ' | <a class="postformOption" href="' . $url . '">' . _T('post_api_link') . '</a>';
	}

	/** Inject the API base URL meta tag into the page header. */
	private function onGenerateModuleHeader(string &$moduleHeader): void {
		$apiUrl = $this->getModulePageURL();
		$fetchingText = htmlspecialchars(_T('post_api_fetching'), ENT_QUOTES, 'UTF-8');

		$moduleHeader .= '<meta name="postApiUrl" content="' . $apiUrl . '">';
		$moduleHeader .= '<meta name="postApiFetchingText" content="' . $fetchingText . '">';
	}

	private function generateTripcode(?string $tripcode, ?string $secureTripcode): string {
		if ($secureTripcode) {
			return _T('cap_char') . $secureTripcode;
		} elseif ($tripcode) {
			return _T('trip_pre') . $tripcode;
		} else {
			return '';
		}
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
			'{$API_BASE_URL}' => $baseApiUrl,
		]);

		$html = $board->getBoardHead('Post API');
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
