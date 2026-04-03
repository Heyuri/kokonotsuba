<?php

namespace Kokonotsuba\Modules\postApi;

use Kokonotsuba\module_classes\abstractModuleMain;
use Kokonotsuba\module_classes\traits\listeners\PostMenuListListenerTrait;
use Kokonotsuba\module_classes\traits\listeners\ModuleHeaderListenerTrait;
use Kokonotsuba\PMCLibrary;
use Kokonotsuba\post\Post;
use Kokonotsuba\renderers\postRenderer;

use function Kokonotsuba\libraries\_T;
use function Kokonotsuba\libraries\html\generatePostNameHtml;
use function Kokonotsuba\libraries\searchBoardArrayForBoard;
use function Puchiko\json\renderCachedJsonPage;
use function Puchiko\json\renderJsonPage;
use function Puchiko\json\renderJsonErrorPage;

class moduleMain extends abstractModuleMain {
	use PostMenuListListenerTrait;
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

		$this->listenPostMenuList('onRenderPostMenuList');
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

	/** Inject the "Post API" link into the rules list below the post form. */
	private function onRenderPostMenuList(string &$postMenuListHtml): void {
		$url = $this->getModulePageURL();
		$postMenuListHtml .= '<li><a class="postformOption" href="' . $url . '">' . _T('post_api_link') . '</a></li>';
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
            return  _T('cap_char') . $secureTripcode;
        } elseif ($tripcode) {
            return _T('trip_pre') . $tripcode;
        } else {
            return '';
        }


    }

	/** Module page — serves the JSON API with server-rendered post HTML. */
	public function ModulePage(): void {
		$postUid = (int) $this->moduleContext->request->getParameter('post_uid', 'GET', '');

		if ($postUid <= 0) {
			renderJsonErrorPage(_T('post_not_found'), 400);
		}

		$post = $this->moduleContext->postRepository->getPostByUid($postUid);

		if (!$post) {
			renderJsonErrorPage(_T('post_not_found'), 404);
		}

		$html = $this->renderPostHtml($post);
		$data = [
            'timestamp' => $post->getRoot(),
            'post_uid' => $post->getUid(),
            'name' => $post->getName(),
            'tripcode' => $this->generateTripcode($post->getTripcode(), $post->getSecureTripcode()),
            'mod_capcode' => $post->getCapcode(),
            'email' => $post->getEmail(),
            'html' => $html
            ];

		renderCachedJsonPage($data);
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
			'',              // warnBeKill
			'',              // warnOld
			'',              // warnHidePost
			'',              // warnEndReply
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
