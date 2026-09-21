/**
 * Unit tests for static/js/quoteLookup.js. No dependencies: node --test tests/js/
 *
 * The matching rules mirror Kokonotsuba\quote_link\textQuoteMatcher, pinned on the PHP side by
 * tests/unit/Kokonotsuba/TextQuoteMatcherTest.php; tests/integration/textQuotes.php checks the
 * two against each other on the same threads.
 */
const test = require('node:test')
const assert = require('node:assert/strict')
const lookup = require('../../static/js/quoteLookup.js')

/** A post as qu3.js reads it off the page: its comment's lines and its file names. */
function post(number, ownText, extra = {}) {
	const quoteLines = extra.quoteLines || []
	delete extra.quoteLines
	return Object.assign({
		id: `p1_${number}`,
		number,
		lines: quoteLines.map(l => '>' + l).concat(ownText === undefined ? [] : [ownText]),
		fileNames: [],
		gapBefore: false,
	}, extra)
}

/** A fetch() stand-in that records what it was asked and answers from a script. */
function fakeFetch(answer) {
	const calls = []
	const fn = url => {
		calls.push(url)
		return Promise.resolve(typeof answer === 'function' ? answer(url, calls.length) : answer)
	}
	fn.calls = calls
	return fn
}

const found = data => ({ status: 200, ok: true, json: () => Promise.resolve(data) })
const missing = { status: 404, ok: false, json: () => Promise.resolve('Post not found!') }
const broken = { status: 500, ok: false, json: () => Promise.reject(new Error('not json')) }

function clock(start = 1000) {
	const c = { t: start }
	c.now = () => c.t
	return c
}

// ─── parseQuote ─────────────────────────────────────────────────

test('isQuoteLine follows the same rule as the renderer', () => {
	assert.equal(lookup.isQuoteLine('>a'), true)
	assert.equal(lookup.isQuoteLine('  \u3000＞a'), true)
	assert.equal(lookup.isQuoteLine('a > b'), false)
	assert.equal(lookup.isQuoteLine(''), false)
})

test('parseQuote splits the marker from the text', () => {
	assert.deepEqual(lookup.parseQuote('>hello'), { text: 'hello', quoted: false })
	assert.deepEqual(lookup.parseQuote('  > hello  '), { text: 'hello', quoted: false })
	assert.deepEqual(lookup.parseQuote('>>hello'), { text: 'hello', quoted: true })
	assert.deepEqual(lookup.parseQuote('> > hello'), { text: 'hello', quoted: true })
	assert.deepEqual(lookup.parseQuote('＞全角'), { text: '全角', quoted: false })
})

test('parseQuote keeps a third marker as text', () => {
	assert.deepEqual(lookup.parseQuote('>>>deep'), { text: '>deep', quoted: true })
})

test('parseQuote refuses what is not a quote', () => {
	for (const raw of ['', '   ', 'hello', '>', '>>', '> >  ', null, undefined]) {
		assert.equal(lookup.parseQuote(raw), null, JSON.stringify(raw))
	}
})

// ─── normalizeNeedle / postNumber ───────────────────────────────

test('normalizeNeedle trims and refuses the empty and the multi-line', () => {
	assert.equal(lookup.normalizeNeedle('  a b 　'), 'a b')
	assert.equal(lookup.normalizeNeedle(''), null)
	assert.equal(lookup.normalizeNeedle(' \t '), null)
	assert.equal(lookup.normalizeNeedle('two\nlines'), null)
	assert.equal(lookup.normalizeNeedle('nul\u0000'), null)
	assert.equal(lookup.normalizeNeedle('tab\tok'), 'tab\tok')
})

test('normalizeNeedle truncates by code point', () => {
	const long = '😀'.repeat(lookup.MAX_NEEDLE_LENGTH + 50)
	const needle = lookup.normalizeNeedle(long)
	assert.equal(Array.from(needle).length, lookup.MAX_NEEDLE_LENGTH)
	assert.equal(needle, '😀'.repeat(lookup.MAX_NEEDLE_LENGTH))
})

test('normalizeNeedle refuses a lone surrogate', () => {
	assert.equal(lookup.normalizeNeedle('bad \uD800 half'), null)
})

test('postNumber reads the futaba forms', () => {
	assert.equal(lookup.postNumber('123'), 123)
	assert.equal(lookup.postNumber('No.123'), 123)
	assert.equal(lookup.postNumber('No. 123'), 123)
	assert.equal(lookup.postNumber('007'), 7)
	assert.equal(lookup.postNumber('0'), 0)
	assert.equal(lookup.postNumber('9'.repeat(30)), 0)
	assert.equal(lookup.postNumber('123 lol'), null)
	assert.equal(lookup.postNumber('no.123'), null)
})

// ─── findSource ─────────────────────────────────────────────────

test('findSource takes the nearest earlier post', () => {
	const posts = [post(1, 'op about cats'), post(2, 'cats are fine'), post(3, 'cats again'), post(4, 'me')]
	assert.deepEqual(lookup.findSource(posts, 3, 'cats', false), { id: 'p1_3', crossedGap: false })
	assert.deepEqual(lookup.findSource(posts, 2, 'cats', false), { id: 'p1_2', crossedGap: false })
	assert.deepEqual(lookup.findSource(posts, 1, 'again', false), { id: null, crossedGap: false })
})

test('findSource ignores a post that only repeats the quote', () => {
	const posts = [post(1, 'op'), post(2, 'source line'), post(3, 'lol', { quoteLines: ['source line'] }), post(4, 'me')]
	assert.equal(lookup.findSource(posts, 3, 'source line', false).id, 'p1_2')
	assert.equal(lookup.findSource(posts, 3, 'source line', true).id, 'p1_3')
})

test('a needle only matches inside one line', () => {
	const posts = [post(1, undefined, { lines: ['ab', 'cd'] }), post(2, 'me')]
	assert.equal(lookup.findSource(posts, 1, 'ab', false).id, 'p1_1')
	assert.equal(lookup.findSource(posts, 1, 'bc', false).id, null)
})

test('a quote of a quote skips posts that quote nothing', () => {
	const posts = [post(1, 'plain words'), post(2, 'me')]
	assert.equal(lookup.findSource(posts, 1, 'plain words', true).id, null)
})

test('findSource matches whole file names only', () => {
	const posts = [post(1, 'op', { fileNames: ['cat.jpg', 'dog.png'] }), post(2, 'me')]
	assert.equal(lookup.findSource(posts, 1, 'cat.jpg', false).id, 'p1_1')
	assert.equal(lookup.findSource(posts, 1, 'dog.png', false).id, 'p1_1')
	assert.equal(lookup.findSource(posts, 1, 'dog', false).id, null)
	assert.equal(lookup.findSource(posts, 1, 'DOG.png', false).id, null)
})

test('findSource resolves numbers by post number and never as text', () => {
	const posts = [post(1, 'the year 1999'), post(1999, 'x'), post(2000, 'me')]
	assert.equal(lookup.findSource(posts, 2, 'No.1999', false).id, 'p1_1999')
	assert.equal(lookup.findSource(posts, 1, '1999', false).id, null)
	assert.equal(lookup.findSource(posts, 2, '0', false).id, null)
})

test('a match past a gap is only a fallback', () => {
	const posts = [post(1, 'cats in the op'), post(50, 'unrelated', { gapBefore: true }), post(51, 'me')]
	assert.deepEqual(lookup.findSource(posts, 2, 'cats', false), { id: 'p1_1', crossedGap: true })
	assert.deepEqual(lookup.findSource(posts, 2, 'unrelated', false), { id: 'p1_50', crossedGap: false })
	assert.deepEqual(lookup.findSource(posts, 2, 'nowhere', false), { id: null, crossedGap: true })
})

test('a gap right before the quoting post counts', () => {
	const posts = [post(1, 'cats'), post(50, 'me', { gapBefore: true })]
	assert.deepEqual(lookup.findSource(posts, 1, 'cats', false), { id: 'p1_1', crossedGap: true })
})

test('a number found past a gap is settled, a number missing is not', () => {
	const posts = [post(1, 'op'), post(50, 'x', { gapBefore: true }), post(51, 'me')]
	assert.deepEqual(lookup.findSource(posts, 2, '1', false), { id: 'p1_1', crossedGap: false })
	assert.deepEqual(lookup.findSource(posts, 2, '30', false), { id: null, crossedGap: true })
})

test('findSource tolerates an index past the end and an empty page', () => {
	assert.deepEqual(lookup.findSource([], 0, 'x', false), { id: null, crossedGap: false })
	assert.equal(lookup.findSource([post(1, 'abc')], 99, 'abc', false).id, 'p1_1')
})

// ─── buildLookupUrl ─────────────────────────────────────────────

test('buildLookupUrl encodes everything a poster can type', () => {
	const url = lookup.buildLookupUrl('/b/koko.php?mode=module&load=postApi', 'ab12', 77, 'a&b=c #d 草%', true)
	const params = new URL(url, 'http://x').searchParams
	assert.equal(params.get('pageName'), 'quote')
	assert.equal(params.get('thread_uid'), 'ab12')
	assert.equal(params.get('before_uid'), '77')
	assert.equal(params.get('quoted'), '1')
	assert.equal(params.get('text'), 'a&b=c #d 草%')
	assert.equal(params.get('load'), 'postApi')
})

test('buildLookupUrl copes with a base that has no query', () => {
	assert.ok(lookup.buildLookupUrl('/api', 't', 1, 'x', false).startsWith('/api?pageName=quote&'))
})

// ─── resolver ───────────────────────────────────────────────────

test('resolver returns the post and asks once for the same quote', async () => {
	const fetch = fakeFetch(found({ post_uid: 5, html: '<div class="post"></div>' }))
	const resolver = lookup.createResolver({ apiUrl: '/api?x=1', fetch })

	const [a, b] = await Promise.all([resolver.resolve('t', 9, 'cats', false), resolver.resolve('t', 9, ' cats ', false)])
	const c = await resolver.resolve('t', '9', 'cats', false)

	assert.equal(a.post_uid, 5)
	assert.equal(b, a)
	assert.equal(c, a)
	assert.equal(fetch.calls.length, 1)
})

test('resolver keeps different quotes apart', async () => {
	const fetch = fakeFetch(missing)
	const resolver = lookup.createResolver({ apiUrl: '/api', fetch })

	await resolver.resolve('t', 9, 'cats', false)
	await resolver.resolve('t', 9, 'cats', true)
	await resolver.resolve('t', 8, 'cats', false)
	await resolver.resolve('u', 9, 'cats', false)

	assert.equal(fetch.calls.length, 4)
})

test('resolver remembers a miss', async () => {
	const fetch = fakeFetch(missing)
	const resolver = lookup.createResolver({ apiUrl: '/api', fetch })

	assert.equal(await resolver.resolve('t', 9, 'nothing', false), null)
	assert.equal(await resolver.resolve('t', 9, 'nothing', false), null)
	assert.equal(fetch.calls.length, 1)
})

test('resolver never asks for what cannot match', async () => {
	const fetch = fakeFetch(missing)
	const resolver = lookup.createResolver({ apiUrl: '/api', fetch })

	for (const args of [['t', 9, '', false], ['t', 9, '  ', false], ['', 9, 'x', false], ['t', 0, 'x', false],
		['t', -4, 'x', false], ['t', 'abc', 'x', false], ['t', 1.5, 'x', false], ['t', 9, 'a\nb', false]]) {
		assert.equal(await resolver.resolve(...args), null)
	}
	assert.equal(fetch.calls.length, 0)
	assert.equal(await lookup.createResolver({ apiUrl: null, fetch }).resolve('t', 9, 'x', false), null)
	assert.equal(fetch.calls.length, 0)
})

test('an answer without html is a miss', async () => {
	const resolver = lookup.createResolver({ apiUrl: '/api', fetch: fakeFetch(found({ post_uid: 5 })) })
	assert.equal(await resolver.resolve('t', 9, 'x', false), null)
})

test('answers expire, so a page left open does not serve them forever', async () => {
	const time = clock()
	const fetch = fakeFetch(found({ post_uid: 5, html: '<p>' }))
	const resolver = lookup.createResolver({ apiUrl: '/api', fetch, now: time.now, answerTtl: 1000 })

	await resolver.resolve('t', 9, 'x', false)
	time.t += 999
	await resolver.resolve('t', 9, 'x', false)
	assert.equal(fetch.calls.length, 1)
	time.t += 2
	await resolver.resolve('t', 9, 'x', false)
	assert.equal(fetch.calls.length, 2)
})

test('a miss is kept longer than an answer, since nothing later can change it', async () => {
	const time = clock()
	const fetch = fakeFetch(missing)
	const resolver = lookup.createResolver({ apiUrl: '/api', fetch, now: time.now, answerTtl: 1000, missTtl: 10000 })

	await resolver.resolve('t', 9, 'x', false)
	time.t += 5000
	await resolver.resolve('t', 9, 'x', false)
	assert.equal(fetch.calls.length, 1, 'still remembered past the answer window')
	time.t += 5001
	await resolver.resolve('t', 9, 'x', false)
	assert.equal(fetch.calls.length, 2)
})

test('a failure is retried sooner than an answer, but not at once', async () => {
	const time = clock()
	const fetch = fakeFetch(broken)
	const resolver = lookup.createResolver({ apiUrl: '/api', fetch, now: time.now, errorTtl: 100, failuresBeforePause: 99 })

	assert.equal(await resolver.resolve('t', 9, 'x', false), null)
	assert.equal(await resolver.resolve('t', 9, 'x', false), null)
	assert.equal(fetch.calls.length, 1)
	time.t += 101
	await resolver.resolve('t', 9, 'x', false)
	assert.equal(fetch.calls.length, 2)
})

test('network errors and throwing fetches resolve to null', async () => {
	const rejecting = lookup.createResolver({ apiUrl: '/api', fetch: () => Promise.reject(new Error('offline')) })
	const throwing = lookup.createResolver({ apiUrl: '/api', fetch: () => { throw new Error('blocked') } })
	const badJson = lookup.createResolver({ apiUrl: '/api', fetch: fakeFetch({ status: 200, ok: true, json: () => Promise.reject(new Error('html error page')) }) })

	assert.equal(await rejecting.resolve('t', 9, 'x', false), null)
	assert.equal(await throwing.resolve('t', 9, 'x', false), null)
	assert.equal(await badJson.resolve('t', 9, 'x', false), null)
})

test('repeated failures pause the resolver, then it recovers', async () => {
	const time = clock()
	let healthy = false
	const fetch = fakeFetch(() => healthy ? found({ post_uid: 1, html: '<p>' }) : broken)
	const resolver = lookup.createResolver({ apiUrl: '/api', fetch, now: time.now, failuresBeforePause: 3, pauseMs: 5000, errorTtl: 10 })

	for (let i = 0; i < 3; i++) await resolver.resolve('t', 9, 'fail' + i, false)
	assert.equal(fetch.calls.length, 3)
	assert.equal(resolver.stats().paused, true)

	// while paused nothing reaches the server
	for (let i = 0; i < 20; i++) assert.equal(await resolver.resolve('t', 9, 'paused' + i, false), null)
	assert.equal(fetch.calls.length, 3)

	healthy = true
	time.t += 5001
	assert.equal((await resolver.resolve('t', 9, 'back', false)).post_uid, 1)
	assert.equal(resolver.stats().paused, false)
})

test('a miss is not a failure and does not pause anything', async () => {
	const fetch = fakeFetch(missing)
	const resolver = lookup.createResolver({ apiUrl: '/api', fetch, failuresBeforePause: 2 })

	for (let i = 0; i < 10; i++) await resolver.resolve('t', 9, 'miss' + i, false)
	assert.equal(fetch.calls.length, 10)
	assert.equal(resolver.stats().paused, false)
})

test('only a few lookups run at once and a burst drops the oldest waiting', async () => {
	const pending = []
	let inFlight = 0, peak = 0
	const fetch = url => new Promise(done => {
		inFlight++
		peak = Math.max(peak, inFlight)
		pending.push(() => { inFlight--; done(missing) })
	})
	const resolver = lookup.createResolver({ apiUrl: '/api', fetch, maxConcurrent: 2, maxQueued: 3 })

	const results = []
	for (let i = 0; i < 10; i++) results.push(resolver.resolve('t', 9, 'burst' + i, false))
	assert.equal(resolver.stats().running, 2)
	assert.equal(resolver.stats().queued, 3)

	while (pending.length) { pending.shift()(); await new Promise(r => setImmediate(r)) }
	assert.deepEqual(await Promise.all(results), Array(10).fill(null))
	assert.equal(peak, 2)
	// 2 ran at once, 3 waited, 5 were dropped without a request
	assert.equal(resolver.stats().requests, 5)
})

test('a dropped lookup is asked again when hovered again', async () => {
	const pending = []
	const fetch = () => new Promise(done => pending.push(() => done(found({ post_uid: 3, html: '<p>' }))))
	const resolver = lookup.createResolver({ apiUrl: '/api', fetch, maxConcurrent: 1, maxQueued: 1 })

	resolver.resolve('t', 9, 'running', false)
	const dropped = resolver.resolve('t', 9, 'dropped', false)
	resolver.resolve('t', 9, 'pushes it out', false)
	assert.equal(await dropped, null)

	const again = resolver.resolve('t', 9, 'dropped', false)
	while (pending.length) { pending.shift()(); await new Promise(r => setImmediate(r)) }
	assert.equal((await again).post_uid, 3)
})

test('the cache is bounded and forgets the least recently used', async () => {
	const fetch = fakeFetch(missing)
	const resolver = lookup.createResolver({ apiUrl: '/api', fetch, maxEntries: 5 })

	for (let i = 0; i < 5; i++) await resolver.resolve('t', 9, 'q' + i, false)
	await resolver.resolve('t', 9, 'q0', false)          // touch the oldest
	await resolver.resolve('t', 9, 'q5', false)          // evicts q1
	assert.equal(resolver.stats().cached, 5)

	const before = fetch.calls.length
	await resolver.resolve('t', 9, 'q0', false)
	assert.equal(fetch.calls.length, before, 'q0 was kept')
	await resolver.resolve('t', 9, 'q1', false)
	assert.equal(fetch.calls.length, before + 1, 'q1 was evicted')
})

test('the same quote makes the same URL for every reader', async () => {
	const a = fakeFetch(missing), b = fakeFetch(missing)
	await lookup.createResolver({ apiUrl: '/api', fetch: a }).resolve('t', 9, '  shared  ', false)
	await lookup.createResolver({ apiUrl: '/api', fetch: b }).resolve('t', '9', 'shared', 0)
	assert.equal(a.calls[0], b.calls[0])
})
