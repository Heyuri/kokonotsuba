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
	public function generateQuoteButton(int $threadResno, int $no, int $totalThreadPages = 0, ?string $crossLink = null): string {
		if ($this->board->getConfigValue('USE_QUOTESYSTEM')) {
			// Build the URL to the specific post
			$threadUrl = $this->board->getBoardThreadURL($threadResno, $no, true, $totalThreadPages, $crossLink);
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
	public function generateReplyButton(string $crossLink, int $threadResno, int $totalThreadPages = 0): string {
		// Build the URL to the thread's reply form
		$replyUrl = $this->getBaseThreadUrl($crossLink, $threadResno, $totalThreadPages);
		
		// Return the reply button HTML
		$replyButton = '[<a href="' . htmlspecialchars($replyUrl) . '">' . _T('reply_btn') . '</a>]';

		return $replyButton;
	}

	public function generateRecentRepliesButton(string $crossLink, int $threadResno, int $replyAmount): string {
		// get the config value for the default/max-range for the amount of replies to be shown
		$recentReplies = $this->board->getConfigValue('LAST_AMOUNT_OF_REPLIES', 50);
		
		// if the thread has less replies than the default max value then don't bother rendering it
		if($replyAmount < $recentReplies) {
			return '';
		}

		// build the url for the 'last X replies' anchor
		$url = $this->getBaseThreadUrl($crossLink, $threadResno) . '&recentReplies=' . htmlspecialchars($recentReplies);

		// then assemble the anchor html
		$recentRepliesAnchor = '[<a href="' . htmlspecialchars($url) . '">' . _T('recent_btn', $recentReplies) . '</a>]';

		// return anchor
		return $recentRepliesAnchor;
	}

	private function getBaseThreadUrl(string $crossLink, int $threadResno, ?int $page = null): string {
		// build url
		$url = $crossLink . $this->board->getConfigValue('LIVE_INDEX_FILE') . '?res=' . $threadResno;
		
		// append page if set
		if(!is_null($page)) {
			$url .= '&page=' . $page;
		}

		// then return url
		return $url;
	}
}