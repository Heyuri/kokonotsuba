<?php

class postElementGenerator {
    public function __construct(
        private board $board
    ) {}

    /**
	 * Formats category field into HTML with search links.
	 */
	public function processCategoryLinks(string $category, string $crossLink): string {
		if (!$this->board->getConfigValue('USE_CATEGORY')) return '';
		$categories = explode(',', str_replace('&#44;', ',', $category));
		$categories = array_map('trim', $categories);
		return implode(', ', array_map(function ($c) use ($crossLink) {
			return '<a href="'.$crossLink.$this->board->getConfigValue('LIVE_INDEX_FILE') . '?mode=module&load=mod_searchcategory&c='.urlencode($c).'">'.$c.'</a>';
		}, array_filter($categories)));
	}

	/**
	 * Generates a quote button that links to a specific post.
	 * 
	 * If the quote system is enabled, this returns a clickable anchor tag
	 * pointing to the thread/post. Otherwise, it just returns the post number
	 * as plain text.
	 */
	public function generateQuoteButton(int $threadResno, int $no): string {
		if ($this->board->getConfigValue('USE_QUOTESYSTEM')) {
			// Build the URL to the specific post
			$threadUrl = $this->board->getBoardThreadURL($threadResno, $no, true);
			// Create the clickable quote button
			$quoteButton = '<a href="'.$threadUrl.'" class="qu" title="Quote">'.$no.'</a>';
		} else {
			// Fallback: show plain post number
			$quoteButton = $no;
		}

		return $quoteButton;
	}

	/**
	 * Generates a reply button linking to the reply form for the thread.
	 * 
	 * Builds a URL based on the thread number and returns it as a clickable
	 * [Reply] link.
	 */
	public function generateReplyButton(string $crossLink, int $threadResno): string {
		// Build the URL to the thread's reply form
		$replyUrl = $crossLink . $this->board->getConfigValue('LIVE_INDEX_FILE') . '?res=' . $threadResno;
		// Return the reply button HTML
		$replyButton = '[<a href="' . $replyUrl . '">' . _T('reply_btn') . '</a>]';

		return $replyButton;
	}
}