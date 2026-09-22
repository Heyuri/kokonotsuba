/**
 * Text quote lookups for qu3.js: the rules a ">some text", ">file.jpg" or ">No.123" quote is
 * matched by, and the client for the post API endpoint that applies the same rules
 * (Kokonotsuba\quote_link\textQuoteMatcher) to the posts a page does not hold.
 *
 * Nothing here touches the DOM, so tests/js/ runs it under node as it is.
 */
(function (root, factory) {
	const api = factory()
	if (typeof module === 'object' && module.exports) module.exports = api
	else root.kkQuoteLookup = api
})(typeof self !== 'undefined' ? self : this, function () {
	/** Longest needle the API accepts, in characters. */
	const MAX_NEEDLE_LENGTH = 300

	const CONTROL_CHARACTERS = /[\x00-\x08\x0A-\x1F\x7F]/
	const POST_NUMBER = /^(?:No\. ?)?(\d+)$/

	/**
	 * Split a quote line into its text and whether it is a ">>" quote of a quote.
	 * @returns {{text: string, quoted: boolean}|null} null when the line is not a quote
	 */
	function parseQuote(raw) {
		let text = String(raw ?? '').trim()
		if (!isQuoteMarker(text[0])) return null
		text = text.slice(1).trim()

		let quoted = false
		if (isQuoteMarker(text[0])) {
			quoted = true
			text = text.slice(1).trim()
		}

		return text === '' ? null : { text, quoted }
	}

	function isQuoteMarker(character) {
		return character === '>' || character === '＞'
	}

	/** The needle as the API wants it, or null when it cannot match anything. */
	function normalizeNeedle(text) {
		let needle = String(text ?? '').trim()
		if (needle === '' || CONTROL_CHARACTERS.test(needle)) return null

		// by code point, so a surrogate pair is never cut in half
		const characters = Array.from(needle)
		if (characters.length > MAX_NEEDLE_LENGTH) {
			needle = characters.slice(0, MAX_NEEDLE_LENGTH).join('').trim()
		}

		// a lone surrogate cannot be sent as UTF-8
		if (typeof needle.isWellFormed === 'function' ? !needle.isWellFormed() : /[\uD800-\uDFFF]/.test(needle.replace(/[\uD800-\uDBFF][\uDC00-\uDFFF]/g, ''))) {
			return null
		}

		return needle === '' ? null : needle
	}

	/** The post number a ">123" or ">No.123" quote names, or null when it is text. */
	function postNumber(text) {
		const match = POST_NUMBER.exec(text)
		if (!match) return null

		const digits = match[1].replace(/^0+/, '')
		return digits === '' || digits.length > 10 ? 0 : Number(digits)
	}

	/**
	 * Whether the page draws this line as a quote. The leading whitespace tolerated is what
	 * trim() removes, which textQuoteMatcher::isQuoteLine() matches on the server.
	 */
	function isQuoteLine(line) {
		const text = String(line ?? '').replace(/^\s+/, '')
		return text.startsWith('>') || text.startsWith('＞')
	}

	/**
	 * Find the source of a quote among the posts a page holds.
	 *
	 * posts are in thread order, each {id, number, lines, fileNames, gapBefore}: lines are the
	 * comment's lines as a reader sees them, and gapBefore says the page is missing posts
	 * between this one and the one before it.
	 *
	 * @returns {{id: string|null, crossedGap: boolean}} crossedGap means a nearer source may
	 * sit among the missing posts, so the answer is only a fallback until the API is asked
	 */
	function findSource(posts, selfIndex, text, quoted) {
		const number = postNumber(text)
		let crossedGap = false

		for (let i = Math.min(selfIndex, posts.length) - 1; i >= 0; i--) {
			if (posts[i + 1]?.gapBefore) crossedGap = true

			if (postMatches(posts[i], text, quoted, number)) {
				// a post number names one post, so finding it settles the question
				return { id: posts[i].id, crossedGap: number === null && crossedGap }
			}
		}

		return { id: null, crossedGap }
	}

	/** The same rules as Kokonotsuba\quote_link\textQuoteMatcher applies to a stored comment. */
	function postMatches(post, text, quoted, number) {
		if (number !== null) return number > 0 && post.number === number
		if (text === '') return false

		let hasQuote = false
		let matched = false

		for (const line of post.lines || []) {
			const quoteLine = isQuoteLine(line)
			if (quoteLine) hasQuote = true
			if ((quoted || !quoteLine) && line.includes(text)) matched = true
		}

		if ((post.fileNames || []).includes(text)) matched = true

		return quoted ? (hasQuote && matched) : matched
	}

	/** The URL of a lookup, the same for every reader so shared caches can answer it. */
	function buildLookupUrl(apiUrl, threadUid, beforeUid, text, quoted) {
		const separator = apiUrl.includes('?') ? '&' : '?'
		return `${apiUrl}${separator}pageName=quote`
			+ `&thread_uid=${encodeURIComponent(threadUid)}`
			+ `&before_uid=${encodeURIComponent(beforeUid)}`
			+ `&quoted=${quoted ? 1 : 0}`
			+ `&text=${encodeURIComponent(text)}`
	}

	/**
	 * A client for the lookup endpoint that is careful with the server: every answer is
	 * remembered (misses too), identical lookups share one request, few run at once, and
	 * after repeated failures it stops asking for a while.
	 *
	 * @param {object} options apiUrl and fetch are required; the rest are tunables for tests
	 */
	function createResolver(options) {
		const settings = Object.assign({
			now: () => Date.now(),
			maxEntries: 200,
			answerTtl: 5 * 60 * 1000,
			// a quote points at an earlier post, so nothing posted later can turn a miss into a
			// hit: there is no reason to ask a second time while the reader is on the page
			missTtl: 30 * 60 * 1000,
			errorTtl: 15 * 1000,
			maxConcurrent: 2,
			maxQueued: 4,
			failuresBeforePause: 3,
			pauseMs: 60 * 1000,
		}, options)

		const cache = new Map()
		const queue = []
		let running = 0
		let failures = 0
		let pausedUntil = 0
		let requests = 0

		function resolve(threadUid, beforeUid, text, quoted) {
			const needle = normalizeNeedle(text)
			const before = Number(beforeUid)
			if (!settings.apiUrl || !threadUid || !Number.isInteger(before) || before <= 0 || needle === null) {
				return Promise.resolve(null)
			}

			const key = JSON.stringify([String(threadUid), before, needle, !!quoted])
			const cached = cache.get(key)
			if (cached && (cached.expires === null || cached.expires > settings.now())) {
				// refresh its place so the entries hovered most stay longest
				cache.delete(key)
				cache.set(key, cached)
				return cached.promise
			}

			const entry = { promise: null, expires: null }
			entry.promise = enqueue(buildLookupUrl(settings.apiUrl, threadUid, before, needle, !!quoted))
				.then(result => {
					if (result.dropped) cache.delete(key)
					else entry.expires = settings.now() + lifetime(result)
					return result.data
				})

			cache.delete(key)
			cache.set(key, entry)
			while (cache.size > settings.maxEntries) cache.delete(cache.keys().next().value)

			return entry.promise
		}

		function lifetime(result) {
			if (result.failed) return settings.errorTtl
			return result.data === null ? settings.missTtl : settings.answerTtl
		}

		function enqueue(url) {
			return new Promise(done => {
				queue.push({ url, done })
				// under a burst the oldest waiting lookup is the one nobody is hovering any more
				while (queue.length > settings.maxQueued) {
					queue.shift().done({ data: null, failed: false, dropped: true })
				}
				pump()
			})
		}

		function pump() {
			while (running < settings.maxConcurrent && queue.length) {
				const job = queue.shift()
				running++
				request(job.url).then(result => {
					running--
					job.done(result)
					pump()
				})
			}
		}

		function request(url) {
			if (settings.now() < pausedUntil) {
				return Promise.resolve({ data: null, failed: true, dropped: false })
			}

			requests++
			let response
			try {
				response = Promise.resolve(settings.fetch(url))
			} catch (error) {
				response = Promise.reject(error)
			}

			return response
				.then(res => {
					// a miss and a refused needle are answers, not failures
					if (res.status === 404 || res.status === 400) return answered(null)
					if (!res.ok) return failed()
					return Promise.resolve(res.json()).then(
						data => (data && typeof data === 'object' && typeof data.html === 'string') ? answered(data) : answered(null)
					)
				})
				.catch(failed)
		}

		function answered(data) {
			failures = 0
			return { data, failed: false, dropped: false }
		}

		function failed() {
			if (++failures >= settings.failuresBeforePause) {
				pausedUntil = settings.now() + settings.pauseMs
				failures = 0
			}
			return { data: null, failed: true, dropped: false }
		}

		return {
			resolve,
			/** Counters for tests and for reading in the console. */
			stats: () => ({ cached: cache.size, queued: queue.length, running, requests, paused: settings.now() < pausedUntil }),
		}
	}

	return { MAX_NEEDLE_LENGTH, parseQuote, normalizeNeedle, postNumber, isQuoteLine, findSource, buildLookupUrl, createResolver }
})
