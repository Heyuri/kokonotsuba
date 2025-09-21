<?php

namespace Kokonotsuba\Modules\sticky;

use BoardException;
use FlagHelper;
use Kokonotsuba\ModuleClasses\abstractModuleAdmin;
use Kokonotsuba\Root\Constants\userRole;

class moduleAdmin extends abstractModuleAdmin {
	public function getRequiredRole(): userRole {
		return $this->getConfig('AuthLevels.CAN_STICKY', userRole::LEV_MODERATOR);
	}

	public function getName(): string {
		return 'Sticky tools';
	}

	public function getVersion(): string {
		return 'Koko 2025';
	}

	public function initialize(): void {
		$this->moduleContext->moduleEngine->addRoleProtectedListener(
			$this->getRequiredRole(),
			'ThreadAdminControls',
			function(string &$modControlSection, array &$post) {
				$this->onRenderThreadAdminControls($modControlSection, $post);
			}
		);
	}

    public function onRenderThreadAdminControls(string &$modfunc, array $post): void {
		$fh = new FlagHelper($post['status']);
		$stickyTitle = $fh->value('sticky') ? 'Unsticky' : 'Sticky post';
		$toggleLabel = $fh->value('sticky') ? 's' : 'S'; 

		$stickyButtonUrl = $this->getModulePageURL(
			[
				'thread_uid' => $post['thread_uid']
			],
			true,
			true
		);

		$modfunc .= '<span class="adminFunctions adminStickyFunction">[<a href="' . $stickyButtonUrl . '" title="' . $stickyTitle . '">'.$toggleLabel.'</a>]</span>';
	}

    public function ModulePage(): void {
		$thread_uid = $_GET['thread_uid'] ?? null;

		// no thread uid selected - throw exception
		if($thread_uid === null) {
			throw new BoardException("No thread was selected!");
		}
		
		// get the thread and associated data (thread data, posts, etc)
		$thread = $this->moduleContext->threadService->getThreadByUID($thread_uid);
		
		// throw an exception if the thread doesn't exist
		if (!$thread) {
			throw new BoardException('ERROR: Thread does not exist.');
		}

		// get the thread data itself
		$threadData = $thread['thread'];
		
		// get the posts
		$posts = $thread['posts'];

		// select the opening post
		$openingPost = $posts[0];

		// bool column for if the thread is stickied
		$is_sticky = $threadData['is_sticky'];

		// if it's already sticky'd, then unsticky it
		if($is_sticky) {
			$this->moduleContext->threadRepository->unStickyThread($thread_uid);
		}
		// sticky the thread
		else {
			$this->moduleContext->threadRepository->stickyThread($thread_uid);
		}

		// toggles OP post status too so we don't have to refactor too much code for rendering in the time being
		$flags = new FlagHelper($openingPost['status']);
		$flags->toggle('sticky');
		$this->moduleContext->postRepository->setPostStatus($openingPost['post_uid'], $flags->toString());


		// post op number of the thread
		$post_op_number = $threadData['post_op_number'];

		// board uid of the thread
		$boardUid = $threadData['boardUID'];

		$this->moduleContext->actionLoggerService->logAction(
			'Changed sticky status on post No.' . $post_op_number . ' (' . ($is_sticky ? 'true' : 'false') . ')',
			$boardUid
		);

		$board = searchBoardArrayForBoard($boardUid);

		$board->rebuildBoard();

		redirect('back', 1);
	}
}