<?php

namespace Kokonotsuba\kernel;

use Kokonotsuba\containers\appContainer;
use Kokonotsuba\cookie\cookieService;
use Kokonotsuba\database\databaseConnection;
use Kokonotsuba\database\transactionManager;
use Kokonotsuba\debug\requestMetrics;
use Kokonotsuba\debug\requestProfiler;
use Kokonotsuba\error\BoardException;
use Kokonotsuba\kernel\providers\boardListProvider;
use Kokonotsuba\kernel\providers\boardProvider;
use Kokonotsuba\kernel\providers\coreProvider;
use Kokonotsuba\kernel\providers\repositoryProvider;
use Kokonotsuba\kernel\providers\siteProvider;
use Kokonotsuba\kokoLibrary;
use Kokonotsuba\request\request;
use RuntimeException;

use function Kokonotsuba\libraries\_T;
use function Kokonotsuba\libraries\renderBasicBootstrapErrorPage;
use function Kokonotsuba\libraries\updateAccountSession;
use function Puchiko\json\renderJsonErrorPage;

/**
 * The shared backend boot. Every entry point (a board's koko.php, the front page, background
 * tasks, CLI utilities) builds its container here and adds only the layer it needs on top.
 */
final class kernel {
	private static bool $librariesLoaded = false;

	/** Constants, path helpers and the function libraries the autoloader can't reach. */
	public static function loadLibraries(): void {
		if (self::$librariesLoaded) {
			return;
		}
		self::$librariesLoaded = true;

		$root = dirname(__DIR__, 3) . '/';
		require_once $root . 'code/Kokonotsuba/constants.php';
		require_once $root . 'paths.php';

		require_once getBackendDir() . 'code/Puchiko/includes.php';

		$libraries = getBackendCodeDir() . 'libraries/';
		foreach ([
			'lib_admin', 'lib_attachment', 'lib_common', 'lib_compatible', 'lib_database',
			'lib_errorhandler', 'lib_external', 'lib_file', 'lib_filter', 'lib_post', 'lib_query',
			'lib_rebuild', 'lib_template', 'lib_tripcode',
			'html/boardList', 'html/configForm', 'html/filterForms', 'html/formAndLayout',
			'html/helperHtmlFunctions', 'html/massModerate', 'html/miscPartials', 'html/pagers',
			'html/postHtmlFunctions',
		] as $library) {
			require_once $libraries . $library . '.php';
		}
	}

	/** @throws RuntimeException when the connection can't be made */
	public static function connectDatabase(?array $dbSettings = null, ?array $tableNames = null): databaseHandles {
		self::loadLibraries();

		$dbSettings ??= getDatabaseSettings();
		// Table names are fixed by tables.php, not by the credentials file.
		$tableNames ??= getTableNames();

		databaseConnection::createInstance($dbSettings);
		$databaseConnection = databaseConnection::getInstance();

		return new databaseHandles($databaseConnection, new transactionManager($databaseConnection), $dbSettings, $tableNames);
	}

	/** Global config, policies, database, repositories and the board list. No session, no board. */
	public static function bootServices(request $request, cookieService $cookieService, ?databaseHandles $db = null): appContainer {
		self::loadLibraries();

		$container = new appContainer();

		coreProvider::register($container, $request, $cookieService, $db ?? self::connectDatabase());
		repositoryProvider::register($container);
		boardListProvider::register($container);

		return $container;
	}

	/** Everything a web request shares: bootServices plus the session and the site-scope services. */
	public static function bootWeb(): appContainer {
		self::loadLibraries();

		$request = request::fromGlobals();

		// Timings for the debug bar, and the request sampler when a profile was asked for. Both are
		// started here, ahead of the rest of the boot, so what they measure is the whole request.
		requestMetrics::begin($request->getRequestTimeFloat());
		requestProfiler::startIfRequested($request);

		self::startSession($request);
		$cookieService = self::startCookies();

		try {
			$db = self::connectDatabase();
		} catch (RuntimeException $runtimeException) {
			renderBasicBootstrapErrorPage($runtimeException->getMessage());
		}

		$container = self::bootServices($request, $cookieService, $db);
		siteProvider::register($container);

		return $container;
	}

	/** The board this request was made to, from the boardUID.ini beside the calling koko.php. */
	public static function bootBoard(appContainer $container): void {
		boardProvider::register($container);
	}

	/**
	 * Run a request handler. A BoardException is the client's refusal, served as JSON to scripts
	 * and as the error page otherwise; any other Throwable is logged and served as a 500.
	 */
	public static function run(appContainer $container, callable $handler): void {
		$request = $container->get('request');

		try {
			updateAccountSession($container->get('accountRepository'), $container->get('loginSessionHandler'));

			$handler($container);
		} catch (BoardException $boardException) {
			$errorMessage = $boardException->getMessage();

			// a refusal is the client's doing unless the exception says otherwise; 500 is kept for crashes
			$statusCode = $boardException->getCode();
			$statusCode = is_int($statusCode) && $statusCode >= 400 && $statusCode <= 599 ? $statusCode : 400;

			if ($request->isAjax()) {
				// strip html tags - message.js doesn't accept any raw html
				renderJsonErrorPage(strip_tags($errorMessage), $statusCode);
			} else {
				$container->get('softErrorHandler')->errorAndExit($errorMessage, $statusCode);
			}
		} catch (\Throwable $e) {
			kokoLibrary::getLoggerInstance($container->get('globalConfig')['ERROR_HANDLER_FILE'], 'Global')
				->error($e->__toString());

			$container->get('softErrorHandler')->errorAndExit(_T('blanket_error'), 500, true);
		}

		clearstatcache();
	}

	private static function startSession(request $request): void {
		// Named after the bootstrap directory, as it always has been, so existing sessions survive.
		session_name('kokonotsuba_session_id_' . substr(md5(getBackendDir() . 'bootstrap'), 0, 5));

		$host = $request->getHttpHost();
		$host = preg_replace('/:\d+$/', '', $host);
		$host = trim($host, '[]');

		$isIp = filter_var($host, FILTER_VALIDATE_IP) !== false;
		$isLocalhost = $host === 'localhost';
		$isOnion = strtolower(substr($host, -6)) === '.onion';

		if (!$isIp && !$isLocalhost && !$isOnion) {
			$parts = explode('.', $host);
			if (count($parts) > 2) {
				$host = implode('.', array_slice($parts, -2));
			}
		}

		$params = [
			'lifetime' => 0,
			'path'     => '/',
			'secure'   => $request->isHttps(),
			'httponly' => true,
			'samesite' => 'Lax'
		];

		if (!$isIp && !$isLocalhost && !$isOnion && $host !== '') {
			$params['domain'] = $host; // applies to apex + all subdomains
		}

		session_set_cookie_params($params);

		session_start();
	}

	private static function startCookies(): cookieService {
		$cookieService = new cookieService($_COOKIE);

		if (!$cookieService->has('viewDeletedPosts')) {
			$cookieService->set('viewDeletedPosts', '1', time() + (86400 * 30), '/');
		}

		return $cookieService;
	}
}
