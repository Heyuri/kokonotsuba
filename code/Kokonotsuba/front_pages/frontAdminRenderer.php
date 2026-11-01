<?php

namespace Kokonotsuba\front_pages;

use Kokonotsuba\news\newsEntry;
use Kokonotsuba\news\newsPage;
use Kokonotsuba\template\templateEngine;

use function Kokonotsuba\libraries\_T;
use function Kokonotsuba\libraries\getCsrfHiddenInput;
use function Puchiko\strings\sanitizeStr;

/**
 * The front page's staff screens: the news and page lists, and the editors. Form values come in
 * as the field arrays below, so a refused save draws the form again with what was typed.
 */
final class frontAdminRenderer {
	public function __construct(
		private readonly templateEngine $templateEngine,
		private readonly frontPageLinks $links,
	) {}

	/**
	 * @param newsPage|null $newsPage Null when the viewer may not edit news.
	 * @param frontPage[]|null $pages Null when the viewer may not edit pages.
	 * @param string|null $logoUrl The current logo ('' for none); null when the viewer may not change it.
	 */
	public function dashboard(?newsPage $newsPage, ?array $pages, ?string $logoUrl = null): string {
		$sections = '';

		if ($newsPage !== null) {
			$rows = array_map(fn(newsEntry $entry): array => [
				'{$VIEW_URL}'   => sanitizeStr($this->links->news($entry->id)),
				'{$SUBJECT}'    => sanitizeStr($entry->subject),
				'{$AUTHOR}'     => sanitizeStr($entry->displayAuthor()),
				'{$DATE}'       => sanitizeStr($entry->createdAt),
				'{$STATUS}'     => $this->status($entry->isPublished),
				'{$EDIT_URL}'   => sanitizeStr($this->links->editNews($entry->id)),
				'{$EDIT_LABEL}' => _T('front_admin_edit'),
			], $newsPage->entries);

			$sections .= $this->templateEngine->ParseBlock('FRONT_ADMIN_NEWS', [
				'{$HEADING}'    => _T('front_admin_news'),
				'{$NEW_URL}'    => sanitizeStr($this->links->editNews()),
				'{$NEW_LABEL}'  => _T('front_admin_new_news'),
				'{$L_SUBJECT}'  => _T('front_admin_subject'),
				'{$L_AUTHOR}'   => _T('front_admin_author'),
				'{$L_DATE}'     => _T('front_admin_date'),
				'{$L_STATUS}'   => _T('front_admin_status'),
				'{$ROWS}'       => $rows,
				'{$EMPTY}'      => $rows === [],
				'{$EMPTY_TEXT}' => _T('front_news_empty'),
				'{$PAGER}'      => $this->pager($newsPage),
			]);
		}

		if ($pages !== null) {
			$rows = array_map(fn(frontPage $page): array => [
				'{$VIEW_URL}'   => sanitizeStr($this->links->page($page->slug)),
				'{$TITLE}'      => sanitizeStr($page->title),
				'{$SLUG}'       => sanitizeStr($page->slug),
				'{$NAV}'        => $page->showInNav ? (string)$page->navOrder : '-',
				'{$STATUS}'     => $this->status($page->isPublished),
				'{$EDIT_URL}'   => sanitizeStr($this->links->editPage($page->id)),
				'{$EDIT_LABEL}' => _T('front_admin_edit'),
			], $pages);

			$sections .= $this->templateEngine->ParseBlock('FRONT_ADMIN_PAGES', [
				'{$HEADING}'    => _T('front_admin_pages'),
				'{$NEW_URL}'    => sanitizeStr($this->links->editPage()),
				'{$NEW_LABEL}'  => _T('front_admin_new_page'),
				'{$L_TITLE}'    => _T('front_admin_title'),
				'{$L_SLUG}'     => _T('front_admin_slug'),
				'{$L_NAV}'      => _T('front_admin_nav_order'),
				'{$L_STATUS}'   => _T('front_admin_status'),
				'{$ROWS}'       => $rows,
				'{$EMPTY}'      => $rows === [],
				'{$EMPTY_TEXT}' => _T('front_admin_no_pages'),
			]);
		}

		if ($logoUrl !== null) {
			$sections .= $this->templateEngine->ParseBlock('FRONT_ADMIN_LOGO', [
				'{$HEADING}'        => _T('front_admin_logo'),
				'{$LOGO_URL}'       => sanitizeStr($logoUrl),
				'{$NO_LOGO}'        => _T('front_admin_no_logo'),
				'{$ACTION_URL}'     => sanitizeStr($this->links->logo()),
				'{$CSRF_TOKEN}'     => getCsrfHiddenInput(),
				'{$UPLOAD_LABEL}'   => _T('front_admin_logo_upload'),
				'{$HINT}'           => _T('front_admin_logo_hint', frontLogoService::MAX_BYTES / 1024),
				'{$REMOVE_LABEL}'   => _T('front_admin_logo_remove'),
				'{$REMOVE_CONFIRM}' => _T('front_admin_logo_remove_confirm'),
			]);
		}

		return $this->templateEngine->ParseBlock('FRONT_ADMIN', ['{$SECTIONS}' => $sections]);
	}

	/** @return array{id: int, subject: string, byline: string, email: string, body: string, published: bool} */
	public static function newsFields(?newsEntry $entry): array {
		return [
			'id'        => $entry?->id ?? 0,
			'subject'   => $entry?->subject ?? '',
			'byline'    => $entry?->byline ?? '',
			'email'     => $entry?->email ?? '',
			'body'      => $entry?->body ?? '',
			'published' => $entry?->isPublished ?? true,
		];
	}

	/** @return array{id: int, title: string, slug: string, body: string, published: bool, show_in_nav: bool, nav_order: int} */
	public static function pageFields(?frontPage $page): array {
		return [
			'id'          => $page?->id ?? 0,
			'title'       => $page?->title ?? '',
			'slug'        => $page?->slug ?? '',
			'body'        => $page?->body ?? '',
			'published'   => $page?->isPublished ?? true,
			'show_in_nav' => $page?->showInNav ?? true,
			'nav_order'   => $page?->navOrder ?? 0,
		];
	}

	public function newsForm(array $fields, string $error = ''): string {
		$isNew = $fields['id'] === 0;

		return $this->errorBox($error) . $this->templateEngine->ParseBlock('NEWS_FORM', [
			'{$ACTION}'             => sanitizeStr($this->links->editNews($isNew ? null : $fields['id'])),
			'{$CSRF_TOKEN}'         => getCsrfHiddenInput(),
			'{$ID}'                 => $fields['id'],
			'{$HEADING}'            => _T($isNew ? 'front_admin_new_news' : 'front_admin_edit_news'),
			'{$L_SUBJECT}'          => _T('front_admin_subject'),
			'{$SUBJECT}'            => sanitizeStr($fields['subject']),
			'{$L_BYLINE}'           => _T('front_admin_byline'),
			'{$BYLINE}'             => sanitizeStr($fields['byline']),
			'{$BYLINE_PLACEHOLDER}' => sanitizeStr(_T('front_admin_byline_hint')),
			'{$L_EMAIL}'            => _T('front_admin_email'),
			'{$EMAIL}'              => sanitizeStr($fields['email']),
			'{$L_BODY}'             => _T('front_admin_body'),
			'{$BODY}'               => sanitizeStr($fields['body']),
			'{$PUBLISHED_CHECKED}'  => $fields['published'] ? ' checked' : '',
		] + $this->commonLabels($isNew));
	}

	public function pageForm(array $fields, string $error = ''): string {
		$isNew = $fields['id'] === 0;

		return $this->errorBox($error) . $this->templateEngine->ParseBlock('PAGE_FORM', [
			'{$ACTION}'            => sanitizeStr($this->links->editPage($isNew ? null : $fields['id'])),
			'{$CSRF_TOKEN}'        => getCsrfHiddenInput(),
			'{$ID}'                => $fields['id'],
			'{$HEADING}'           => _T($isNew ? 'front_admin_new_page' : 'front_admin_edit_page'),
			'{$L_TITLE}'           => _T('front_admin_title'),
			'{$TITLE}'             => sanitizeStr($fields['title']),
			'{$L_SLUG}'            => _T('front_admin_slug'),
			'{$SLUG}'              => sanitizeStr($fields['slug']),
			'{$L_SLUG_HINT}'       => _T('front_admin_slug_hint'),
			'{$L_BODY}'            => _T('front_admin_body'),
			'{$BODY}'              => sanitizeStr($fields['body']),
			'{$PUBLISHED_CHECKED}' => $fields['published'] ? ' checked' : '',
			'{$L_NAV}'             => _T('front_admin_nav'),
			'{$NAV_CHECKED}'       => $fields['show_in_nav'] ? ' checked' : '',
			'{$L_NAV_HINT}'        => _T('front_admin_nav_hint'),
			'{$L_NAV_ORDER}'       => _T('front_admin_nav_order'),
			'{$NAV_ORDER}'         => (int)$fields['nav_order'],
		] + $this->commonLabels($isNew));
	}

	private function commonLabels(bool $isNew): array {
		return [
			'{$L_PUBLISHED}'      => _T('front_admin_published'),
			'{$L_PUBLISHED_HINT}' => _T('front_admin_published_hint'),
			'{$L_SAVE}'           => _T('front_admin_save'),
			'{$CAN_DELETE}'       => !$isNew,
			'{$L_DELETE}'         => _T('front_admin_delete'),
			'{$L_DELETE_CONFIRM}' => sanitizeStr(_T('front_admin_delete_confirm')),
			'{$L_PREVIEW}'        => _T('front_admin_preview'),
		];
	}

	private function errorBox(string $error): string {
		return $error === '' ? '' : '<p class="frontAdminError" role="alert">' . sanitizeStr($error) . '</p>';
	}

	private function status(bool $isPublished): string {
		return $isPublished ? _T('front_admin_status_published') : '<span class="newsDraft">' . _T('front_news_draft') . '</span>';
	}

	private function pager(newsPage $newsPage): string {
		if ($newsPage->pageCount <= 1) {
			return '';
		}

		$parts = [];
		for ($page = 1; $page <= $newsPage->pageCount; $page++) {
			$parts[] = $page === $newsPage->page
				? '[<b>' . $page . '</b>]'
				: '[<a href="' . sanitizeStr($this->links->with(['mode' => 'admin', 'page' => $page])) . '">' . $page . '</a>]';
		}

		return '<div class="frontPager">' . implode(' ', $parts) . '</div>';
	}
}
