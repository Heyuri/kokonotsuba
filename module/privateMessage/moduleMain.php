<?php

namespace Kokonotsuba\Modules\privateMessage;

require_once __DIR__ . '/messageRepository.php';
require_once __DIR__ . '/messageService.php';
require_once __DIR__ . '/messageUtility.php';
require_once __DIR__ . '/messagePolicy.php';
require_once __DIR__ . '/messageRenderer.php';
require_once __DIR__ . '/messageRequestHandler.php';

use Kokonotsuba\database\databaseConnection;
use Kokonotsuba\module_classes\abstractModuleMain;
use Kokonotsuba\module_classes\traits\listeners\TopLinksListenerTrait;

use function Kokonotsuba\libraries\_T;
use function Kokonotsuba\libraries\getRoleLevelFromSession;
use function Puchiko\strings\sanitizeStr;

class moduleMain extends abstractModuleMain {
	use TopLinksListenerTrait;

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

		$this->listenTopLinks('onRenderTopLink');

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
			$this->messageUtility
		);

		// init request handler
		$this->messageRequestHandler = new messageRequestHandler(
			$this->messageService,
			$this->messagePolicy,
			$this->messageRenderer,
			$this->messageUtility
		);
	}

	private function onRenderTopLink(string &$topLinkHookHtml): void {
		$topLinkHookHtml .= '[<a href="' . sanitizeStr($this->modulePageUrl) . '">' . sanitizeStr(_T('private_message_top_bar')) . '</a>]';
	}

	public function ModulePage() {
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