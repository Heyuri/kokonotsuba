<?php

namespace Kokonotsuba\module_classes\traits\listeners;

use Kokonotsuba\error\BoardException;
use Kokonotsuba\post\Post;
use Kokonotsuba\userRole;

/**
 * Trait for modules that offer a bulk action in the [Moderate] window (the post checkboxes).
 *
 * A tool is data, not markup: the entry below is rendered by templates/global/MASS_MODERATE_ITEM.tpl
 * into the page's <template>, and massModerate.js posts the selection to the tool's URL as
 * post_uids[]. Register with a role-protected listener so the entry only exists for accounts that
 * may use it — the handler still has to check for itself.
 *
 * Requires the using class to extend abstractModuleAdmin.
 */
trait MassModerateListenerTrait {
	/** Upper bound on one request's selection, so a crafted POST cannot ask for unbounded work. */
	protected function getMassModerateLimit(): int {
		return 500;
	}

	/**
	 * @param userRole|null $requiredRole Role the entries need, when it is stricter than the
	 *                                    module's own (a purge next to a restore, say).
	 */
	protected function listenMassModerateTools(string $methodName, int $priority = 0, ?userRole $requiredRole = null): void {
		$this->moduleContext->moduleEngine->addRoleProtectedListener(
			$requiredRole ?? $this->getRequiredRole(),
			'MassModerateTools',
			function(array &$tools) use ($methodName) {
				$this->$methodName($tools);
			},
			$priority
		);
	}

	/**
	 * Describe one entry in the [Moderate] list.
	 *
	 * @param string $action  Unique key, also sent back as the 'action' POST parameter by default.
	 * @param string $label   Text shown in the list.
	 * @param array  $options scope: 'post' (default) or 'thread' — thread entries are hidden unless
	 *                        an OP is selected, and only OPs are sent.
	 *                        effect: what JS does to the selected posts on success —
	 *                        'delete', 'restore', 'purge', 'flag', 'deleteFiles', 'reload' or
	 *                        'none'.
	 *                        indicator: for effect 'flag', the indicator class to show/hide.
	 *                        requires: 'deleted' to only enable the entry for deleted posts,
	 *                        'files' for posts that still have an attachment.
	 *                        form: id of a <template> with extra fields to fill in first.
	 *                        confirm: prompt shown before the request ('{action}' and '{count}' are
	 *                        filled in by the window).
	 *                        group: heading the entry is listed under, defaulting to this module's
	 *                        name.
	 *                        params: extra POST parameters.
	 *                        url, priority.
	 */
	protected function buildMassTool(string $action, string $label, array $options = []): array {
		return [
			'action'    => $action,
			'label'     => $label,
			'group'     => $options['group'] ?? $this->getName(),
			'url'       => $options['url'] ?? $this->getModulePageURL([], false, true),
			'scope'     => $options['scope'] ?? 'post',
			'effect'    => $options['effect'] ?? 'none',
			'indicator' => $options['indicator'] ?? '',
			'requires'  => $options['requires'] ?? '',
			'form'      => $options['form'] ?? '',
			'confirm'   => $options['confirm'] ?? '',
			'params'    => ($options['params'] ?? []) + ['action' => $action],
			'priority'  => $options['priority'] ?? 0,
		];
	}

	/**
	 * The selection this request is acting on: post_uids[] from the window, or the single post_uid
	 * the per-post widgets and the no-script forms send.
	 *
	 * @return int[] Unique post UIDs.
	 */
	protected function getRequestedPostUids(): array {
		$uids = $this->moduleContext->request->getParameter('post_uids', 'POST');

		if (!is_array($uids)) {
			$single = $this->moduleContext->request->getParameter('post_uid');
			$uids = $single === null ? [] : [$single];
		}

		$uids = array_values(array_unique(array_filter(
			array_map(fn($uid) => (int)$uid, $uids),
			fn(int $uid) => $uid > 0
		)));

		if (!$uids) {
			throw new BoardException('No posts were selected!');
		}

		$limit = $this->getMassModerateLimit();

		if (count($uids) > $limit) {
			throw new BoardException('Too many posts selected (limit ' . $limit . ').');
		}

		return $uids;
	}

	/**
	 * Fetch the selected posts in one query.
	 *
	 * @return Post[]
	 */
	protected function fetchRequestedPosts(array $postUids, bool $viewDeleted = false): array {
		$posts = $this->moduleContext->postService->getPostsByUids($postUids, $viewDeleted);

		if (!$posts) {
			throw new BoardException('Posts not found!');
		}

		return $posts;
	}
}
