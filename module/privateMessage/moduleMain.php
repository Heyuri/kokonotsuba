<?php

namespace Kokonotsuba\Modules\privateMessage;

require_once __DIR__ . '/messageRepository.php';
require_once __DIR__ . '/messageService.php';
require_once __DIR__ . '/messageUtility.php';
require_once __DIR__ . '/messagePolicy.php';
require_once __DIR__ . '/messageRenderer.php';
require_once __DIR__ . '/messageRequestHandler.php';

use Kokonotsuba\board\board;
use Kokonotsuba\database\databaseConnection;
use Kokonotsuba\error\BoardException;
use Kokonotsuba\module_classes\abstractModuleMain;
use Kokonotsuba\ban\banCheckpoint;
use Kokonotsuba\module_classes\traits\BanCheckpointTrait;
use Kokonotsuba\module_classes\traits\listeners\TopLinksListenerTrait;
use Kokonotsuba\module_classes\traits\listeners\IncludeScriptTrait;
use Kokonotsuba\module_classes\traits\listeners\PostListenerTrait;
use Kokonotsuba\module_classes\traits\listeners\RegistPostInsertedListenerTrait;
use Kokonotsuba\post\helper\postDateFormatter;
use Kokonotsuba\post\Post;

use function Kokonotsuba\libraries\_T;
use function Kokonotsuba\libraries\getRoleLevelFromSession;
use function Puchiko\strings\sanitizeStr;

class moduleMain extends abstractModuleMain {
	use TopLinksListenerTrait;
	use IncludeScriptTrait;
	use BanCheckpointTrait;
	use PostListenerTrait;
	use RegistPostInsertedListenerTrait;

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
		$this->registerScript('privateMessage.js?v=2');

		// PM button next to tripcode'd names
		if ($this->getModuleConfig('APPEND_TRIP_PM_BUTTON_TO_POST', true)) {
			$this->listenPost('onRenderPost');
		}

		// get database table and connection
		$databaseConnection = databaseConnection::getInstance();
		$privateMessageTable = $this->moduleContext->getTableName('PRIVATE_MESSAGE_TABLE');

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

		// posting with a tripcode signs you in to the PM system as that tripcode
		$this->listenRegistPostInserted('onPostInserted');

		// init request handler
		$this->messageRequestHandler = new messageRequestHandler(
			$this->messageService,
			$this->messagePolicy,
			$this->messageRenderer,
			$this->messageUtility,
			$this->moduleContext->request,
			$this->getConfig('COMM_MAX', 4000),
			$this->getConfig('PM_MESSAGES_PER_PAGE', 20)
		);

		// browser notifications for unread PMs
		$this->registerUnreadNotificationHook();
	}

	private function onRenderPost(array &$templateValues, Post &$post, array &$threadPosts, board &$board, bool &$adminMode): void {
		if (!isset($templateValues['{$NAME}'])) {
			return;
		}

		// only posts made with a tripcode get a PM button
		$recipientTrip = $this->messageUtility->buildTripcodeIdentity($post->getTripcode(), $post->getSecureTripcode());
		if ($recipientTrip === '') {
			return;
		}

		$composeUrl = $this->getModulePageURL(['recipient' => $recipientTrip]);

		$templateValues['{$NAME}'] .= ' <span class="pmNameLinkContainer">[<a href="' . $composeUrl . '" class="pmNameLink" title="' . _T('pm_post_button_title') . '">PM</a>]</span>';
	}

	/**
	 * Adopt the tripcode a freshly registered post was made with as the PM identity.
	 * The stored columns are read back rather than the submitted name so a board that
	 * strips or rewrites tripcodes is respected.
	 */
	private function onPostInserted(int $postUid, string $ip): void {
		if (!$this->messageUtility->canAdoptPostTripcode()) {
			return;
		}

		$post = $this->moduleContext->postRepository->getPostByUid($postUid);
		if ($post === false) {
			return;
		}

		$this->messageUtility->loginFromPostTripcode($post->getTripcode(), $post->getSecureTripcode());
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
		// check if the user is banned from private messages
		$this->assertNotBanned(banCheckpoint::PM);

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