/*
 * Post date hover: sets the date's title to "x ago" when the pointer reaches it.
 * The server writes the post's Unix time in data-timestamp; comparing it with the
 * viewer's clock needs no timezone, so board and browser zones never matter.
 * Strings come from window.KOKO_RELATIVE_TIME (generateRelativeTimeScript).
 */
(function () {
	'use strict';

	// below this many seconds the post reads as "Now"
	const NOW_THRESHOLD = 10;

	// each unit's length in seconds and the point the next unit takes over, smallest first
	const UNITS = [
		{ name: 'second', seconds: 1, until: 60 },
		{ name: 'minute', seconds: 60, until: 3600 },
		{ name: 'hour', seconds: 3600, until: 86400 },
		{ name: 'day', seconds: 86400, until: 86400 * 30 },
		{ name: 'month', seconds: 86400 * 30, until: 86400 * 365 },
		{ name: 'year', seconds: 86400 * 365, until: Infinity }
	];

	/**
	 * The unit an age is counted in.
	 *
	 * @param {number} age Seconds since the post
	 * @returns {{name: string, seconds: number, until: number}} The first unit whose range holds the age
	 */
	function unitFor(age) {
		// the last unit has no upper bound, so this always finds one
		return UNITS.find(function (unit) { return age < unit.until; });
	}

	/**
	 * The translated "x ago" text for an age.
	 *
	 * @param {number} age Seconds since the post, negative when the viewer's clock runs behind
	 * @param {{now: string, units: Object<string, string[]>}} strings Translations from the page head
	 * @returns {string} The hover text
	 */
	function relativeText(age, strings) {
		// fresh posts, and posts "from the future" on a slow clock, read as now
		if (age < NOW_THRESHOLD) {
			return strings.now;
		}

		// whole units only, so "1 hour ago" lasts until the second hour is reached
		const unit = unitFor(age);

		const count = Math.floor(age / unit.seconds);

		// [singular, plural] with %s for the count
		const pair = strings.units[unit.name];

		return pair[count === 1 ? 0 : 1].replace('%s', count);
	}

	/**
	 * Refresh the hovered date's title so the browser tooltip shows the current age.
	 *
	 * @param {MouseEvent} event The bubbling mouseover
	 * @returns {void}
	 */
	function onMouseOver(event) {
		// the date span itself or one of its inner parts
		const dateElement = event.target.closest && event.target.closest('.time[data-timestamp]');
		if (!dateElement) return;

		// 0 means the server had no time for this post
		const timestamp = parseInt(dateElement.dataset.timestamp, 10);
		if (!timestamp) return;

		// strings are missing on pages built before this script existed
		const strings = window.KOKO_RELATIVE_TIME;
		if (!strings) return;

		// both sides are epoch seconds, so timezones cancel out
		const age = Math.floor(Date.now() / 1000) - timestamp;

		dateElement.title = relativeText(age, strings);
	}

	// delegated, so posts added later by the updater or quote previews are covered
	document.addEventListener('mouseover', onMouseOver);
})();
