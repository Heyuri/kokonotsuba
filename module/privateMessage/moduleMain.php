<?php

namespace Kokonotsuba\Modules\privateMessage;

require_once __DIR__ . '/messageRepository.php';
require_once __DIR__ . '/messageService.php';
require_once __DIR__ . '/messageUtility.php';
require_once __DIR__ . '/messagePolicy.php';
require_once __DIR__ . '/messageRenderer.php';
require_once __DIR__ . '/messageRequestHandler.php';

use Kokonotsuba\database\databaseConnection;
use Kokonotsuba\error\BoardException;
use Kokonotsuba\module_classes\abstractModuleMain;
use Kokonotsuba\module_classes\traits\BanFileOperationsTrait;
use Kokonotsuba\module_classes\traits\listeners\TopLinksListenerTrait;
use Kokonotsuba\module_classes\traits\listeners\IncludeScriptTrait;
use Kokonotsuba\post\helper\postDateFormatter;

use function Kokonotsuba\libraries\_T;
use function Kokonotsuba\libraries\getRoleLevelFromSession;
use function Puchiko\strings\sanitizeStr;

class moduleMain extends abstractModuleMain {
	use TopLinksListenerTrait;
	use IncludeScriptTrait;
	use BanFileOperationsTrait;

	private string $modulePageUrl;
	private messageService $messageService;
	private messagePolicy $messagePolicy;
	private messageUtility $messageUtility;
	private messageRenderer $messageRenderer;
	private messageRequestHandler $messageRequestHandler;

	public function getName(): string {
		return 'Kokonotsuba Private messaging system';
	}

	public function getVersion(): string  {
		return 'VER. 9001';
	}

	public function initialize(): void {
		$this->modulePageUrl = $this->getModulePageURL([], false);

		$this->addTopLink($this->modulePageUrl, _T('private_message_top_bar'));
		$this->registerScript('privateMessage.js');

		// $this->listenPost('onRenderPost');

		// get database table and connection
		$databaseConnection = databaseConnection::getInstance();
		$privateMessageTable = getDatabaseSettings()['PRIVATE_MESSAGE_TABLE'];

		// init message repo
		$messageRepository = new messageRepository($databaseConnection, $privateMessageTable);

		// init message service
		$this->messageService = new messageService($messageRepository);

		// init message policy
		$this->messagePolicy = new messagePolicy(
			$this->getConfig('AuthLevels', []), 
			getRoleLevelFromSession(), 
			$this->moduleContext->currentUserId
		);

		// set the service in policy
		$this->messagePolicy->setMessageService($this->messageService);

		// now set the utility class
		$this->messageUtility = new messageUtility(
			$this->getModulePageURL(...),
			$this->getConfig('TRIPSALT')
		);

		// init message renderer
		$this->messageRenderer = new messageRenderer(
			$this->moduleContext->adminPageRenderer,
			$this->messageUtility,
			new postDateFormatter($this->getConfig('TIME_ZONE', 0)),
			$this->moduleContext->moduleEngine,
			$this->moduleContext->request
		);

		// init request handler
		$this->messageRequestHandler = new messageRequestHandler(
			$this->messageService,
			$this->messagePolicy,
			$this->messageRenderer,
			$this->messageUtility,
			$this->moduleContext->request,
			$this->getConfig('INPUT_MAX', 100),
			$this->getConfig('PM_MESSAGES_PER_PAGE', 20)
		);

		// browser notifications for unread PMs
		$this->registerUnreadNotificationHook();
	}

	private function registerUnreadNotificationHook(): void {
		if (!$this->messageUtility->isLoggedIn()) {
			return;
		}

		$apiUrl = $this->getModulePageURL(['notifications' => '1'], false);

		$this->moduleContext->moduleEngine->addListener('ModuleHeader',
			function (string &$moduleHeader) use ($apiUrl) {
				$moduleHeader .= '<meta name="pmNotifyApi" content="' . sanitizeStr($apiUrl) . '">';
			}
		);
	}

	public function ModulePage() {
		// check if the user's IP is banned
		$userIp = (string) $this->moduleContext->request->userIp();
		if ($this->isIpBanned($userIp)) {
			throw new BoardException(_T('pm_user_banned'));
		}

		// handle submitted forms and such
		if($this->moduleContext->request->isPost()) {
			$this->messageRequestHandler->handlePostRequest();
		} 
		// handle static pages
		else {
			$this->messageRequestHandler->handleGetRequest();
		}
	}
}