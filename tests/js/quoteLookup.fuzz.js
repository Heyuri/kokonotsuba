/**
 * Fuzzer for static/js/quoteLookup.js: hostile text at the parsers, random threads at the matcher
 * (checked against a plain re-statement of the rules), and random traffic at the resolver.
 *
 *   node tests/js/quoteLookup.fuzz.js                      # 2000 iterations, random seed
 *   node tests/js/quoteLookup.fuzz.js --iterations=50000 --seed=12345
 *
 * Exit code 0 when nothing fails, 1 otherwise. A failure prints the seed and the input.
 */
const assert = require('node:assert/strict')
const lookup = require('../../static/js/quoteLookup.js')

const args = Object.fromEntries(process.argv.slice(2).map(a => a.replace(/^--/, '').split('=')))
const iterations = Number(args.iterations || 2000)
const seed = Number(args.seed || (Date.now() % 2147483647))

// mulberry32: small, seedable, good enough to reproduce a run
let state = seed >>> 0
function random() {
	state = (state + 0x6D2B79F5) >>> 0
	let t = state
	t = Math.imul(t ^ (t >>> 15), t | 1)
	t ^= t + Math.imul(t ^ (t >>> 7), t | 61)
	return ((t ^ (t >>> 14)) >>> 0) / 4294967296
}
const int = (min, max) => min + Math.floor(random() * (max - min + 1))
const pick = list => list[int(0, list.length - 1)]

const FRAGMENTS = ['>', '>>', '＞', ' ', '　', '\t', '\n', '\r\n', '\u0000', '\u007f', 'No.', 'No. ', '123', '0', '007',
	'cat', 'Cat', 'the', '草', 'ｷﾀ━(ﾟ∀ﾟ)━!', '😀', '\uD83D', '\uDE00', '%', '_', '\\', '&amp;', '<b>', '"', "'", '#', '?', '&', '=',
	'.jpg', '.', 'file.png', 'é', 'é', '‮', '﻿', '​', 'x'.repeat(40)]

function nasty(maxParts = 8) {
	let out = ''
	for (let i = int(0, maxParts); i > 0; i--) out += random() < 0.2 ? String.fromCharCode(int(0, 0xFFFF)) : pick(FRAGMENTS)
	return out
}

/** Short words from a tiny alphabet, so needles really do occur in posts. */
function words(count) {
	const out = []
	for (let i = 0; i < count; i++) out.push(pick(['cat', 'dog', 'the', 'a', 'Cat', '草', 'file.png', '12', 'No.3']))
	return out.join(pick([' ', '', '  ']))
}

function randomThread() {
	const posts = []
	const size = int(0, 12)
	for (let i = 0; i < size; i++) {
		const lines = []
		for (let j = int(0, 3); j > 0; j--) {
			lines.push(random() < 0.4 ? '>' + words(int(1, 3)) : words(int(0, 4)))
		}
		posts.push({
			id: `p1_${i + 1}`,
			number: i + 1,
			lines,
			fileNames: random() < 0.3 ? [pick(['file.png', 'cat.jpg', 'the'])] : [],
			gapBefore: i > 0 && random() < 0.2,
		})
	}
	return posts
}

/** The rules written the long way round, as the oracle for findSource(). */
function reference(posts, selfIndex, text, quoted) {
	const number = lookup.postNumber(text)
	const before = posts.slice(0, Math.max(0, Math.min(selfIndex, posts.length)))
	let matchIndex = -1

	before.forEach((p, i) => {
		let hit
		if (number !== null) {
			hit = number > 0 && p.number === number
		} else if (text === '') {
			hit = false
		} else {
			const quoteLines = p.lines.filter(l => lookup.isQuoteLine(l))
			const searched = (quoted ? p.lines : p.lines.filter(l => !lookup.isQuoteLine(l)))
			hit = searched.some(l => l.includes(text)) || p.fileNames.includes(text)
			if (quoted) hit = hit && quoteLines.length > 0
		}
		if (hit) matchIndex = i
	})

	const gapAfter = from => posts.slice(from + 1, Math.min(selfIndex, posts.length - 1) + 1).some(p => p.gapBefore)
	if (matchIndex === -1) return { id: null, crossedGap: gapAfter(-1) && before.length > 0 }
	return { id: posts[matchIndex].id, crossedGap: number === null && gapAfter(matchIndex) }
}

const targets = {
	parseQuote() {
		const raw = nasty()
		const quote = lookup.parseQuote(raw)
		if (quote === null) return
		assert.equal(typeof quote.quoted, 'boolean')
		assert.notEqual(quote.text, '')
		assert.equal(quote.text, quote.text.trim())
		assert.ok(raw.includes(quote.text), 'the text comes from the line')
	},

	normalizeNeedle() {
		const raw = random() < 0.1 ? nasty(200) : nasty()
		const needle = lookup.normalizeNeedle(raw)
		if (needle === null) return
		assert.ok(Array.from(needle).length <= lookup.MAX_NEEDLE_LENGTH)
		assert.notEqual(needle, '')
		assert.equal(needle, needle.trim())
		assert.ok(!/[\x00-\x08\x0A-\x1F\x7F]/.test(needle))
		assert.equal(lookup.normalizeNeedle(needle), needle, 'idempotent')
		// what is sent must survive the trip as UTF-8
		assert.equal(decodeURIComponent(encodeURIComponent(needle)), needle)
	},

	postNumber() {
		const raw = random() < 0.5 ? pick(['', 'No.', 'No. ']) + String(int(0, 99999)).padStart(int(1, 14), '0') : nasty(3)
		const number = lookup.postNumber(raw)
		if (number === null) return
		assert.ok(Number.isSafeInteger(number) && number >= 0 && number <= 9999999999)
	},

	buildLookupUrl() {
		const text = lookup.normalizeNeedle(nasty())
		if (text === null) return
		const thread = pick(['ab12', 'A_b-9', '0'.repeat(64)])
		const before = int(1, 2 ** 31)
		const quoted = random() < 0.5
		const base = pick(['/koko.php?mode=module&load=postApi', '/api', 'https://x.test/b/koko.php?mode=module&load=postApi'])
		const params = new URL(lookup.buildLookupUrl(base, thread, before, text, quoted), 'http://host').searchParams
		assert.equal(params.get('text'), text)
		assert.equal(params.get('thread_uid'), thread)
		assert.equal(params.get('before_uid'), String(before))
		assert.equal(params.get('quoted'), quoted ? '1' : '0')
		assert.equal(params.getAll('text').length, 1, 'a needle cannot smuggle a second parameter')
		assert.equal(params.getAll('pageName').length, 1)
	},

	findSource() {
		const posts = randomThread()
		const selfIndex = int(0, posts.length + 1)
		const text = random() < 0.8 ? words(int(1, 2)).trim() || 'cat' : nasty(2) || 'x'
		const quoted = random() < 0.3
		const frozen = JSON.stringify(posts)

		const got = lookup.findSource(posts, selfIndex, text, quoted)
		assert.deepEqual(got, reference(posts, selfIndex, text, quoted), JSON.stringify({ posts, selfIndex, text, quoted }))
		assert.equal(JSON.stringify(posts), frozen, 'the page records are not modified')
		if (got.id !== null) assert.ok(posts.findIndex(p => p.id === got.id) < selfIndex, 'only earlier posts')
		if (!posts.some(p => p.gapBefore)) assert.equal(got.crossedGap, false)
	},
}

/** Random hovering against a server that is slow, missing, broken or lying, all at once. */
async function resolverTraffic(rounds) {
	const time = { t: 0 }
	const settings = { maxConcurrent: int(1, 3), maxQueued: int(1, 5), maxEntries: int(1, 12), failuresBeforePause: int(1, 4), pauseMs: int(10, 500), answerTtl: int(10, 500), errorTtl: int(1, 50) }
	let inFlight = 0, pausedCalls = 0
	const waiting = []
	let resolver

	const fetch = url => {
		if (resolver.stats().paused) pausedCalls++
		inFlight++
		assert.ok(inFlight <= settings.maxConcurrent, 'concurrency cap')
		assert.ok(new URL(url, 'http://h').searchParams.get('pageName') === 'quote')
		const outcome = pick(['found', 'found', 'missing', 'missing', 'broken', 'reject', 'throw', 'junk', 'badjson'])
		if (outcome === 'throw') { inFlight--; throw new Error('sync failure') }
		return new Promise((done, fail) => waiting.push(() => {
			inFlight--
			if (outcome === 'found') done({ status: 200, ok: true, json: async () => ({ post_uid: int(1, 99), html: '<div class="post"></div>' }) })
			else if (outcome === 'missing') done({ status: pick([404, 400]), ok: false, json: async () => 'no' })
			else if (outcome === 'broken') done({ status: pick([500, 502, 429, 403]), ok: false, json: async () => ({}) })
			else if (outcome === 'junk') done({ status: 200, ok: true, json: async () => pick([null, 5, 'x', [], { html: 7 }]) })
			else if (outcome === 'badjson') done({ status: 200, ok: true, json: () => Promise.reject(new Error('html')) })
			else fail(new Error('offline'))
		}))
	}

	resolver = lookup.createResolver(Object.assign({ apiUrl: '/api?m=1', fetch, now: () => time.t }, settings))

	const promises = []
	for (let i = 0; i < rounds; i++) {
		const action = random()
		if (action < 0.6) {
			const p = resolver.resolve(pick(['t1', 't2', '']), pick([0, 5, 9, '9', -1, 2.5]), pick(['cat', 'dog', ' cat ', '', nasty(3)]), random() < 0.3)
			assert.ok(p instanceof Promise)
			promises.push(p)
		} else if (action < 0.9 && waiting.length) {
			waiting.splice(int(0, waiting.length - 1), 1)[0]()
			await new Promise(r => setImmediate(r))
		} else {
			time.t += int(0, 300)
		}
		const stats = resolver.stats()
		assert.ok(stats.cached <= settings.maxEntries, 'cache bound')
		assert.ok(stats.queued <= settings.maxQueued, 'queue bound')
		assert.ok(stats.running <= settings.maxConcurrent)
	}

	// let the server answer everything still open; a lookup that throws restarts the queue a
	// tick later, so idle is judged after the tick rather than before it
	for (let idleTicks = 0; idleTicks < 2;) {
		await new Promise(r => setImmediate(r))
		if (waiting.length) { waiting.shift()(); idleTicks = 0 }
		else idleTicks++
	}
	const results = await Promise.race([
		Promise.all(promises),
		new Promise((_, fail) => setTimeout(() => fail(new Error('a lookup never settled: ' + JSON.stringify(resolver.stats()))), 2000)),
	])
	for (const r of results) assert.ok(r === null || typeof r.html === 'string')
	assert.equal(pausedCalls, 0, 'nothing is sent while paused')
	assert.equal(resolver.stats().running, 0)
	assert.equal(resolver.stats().queued, 0)
}

async function main() {
	console.log(`quoteLookup fuzz: seed=${seed} iterations=${iterations}`)
	let failures = 0

	for (const [name, fn] of Object.entries(targets)) {
		if (args.target && !name.includes(args.target)) continue
		let failed = false
		for (let i = 0; i < iterations && !failed; i++) {
			try { fn() } catch (error) {
				failed = true
				failures++
				console.log(`  FAIL ${name} at iteration ${i}: ${error.message}`)
			}
		}
		if (!failed) console.log(`  ok   ${name} (${iterations})`)
	}

	if (!args.target || 'resolver'.includes(args.target)) {
		const runs = Math.max(1, Math.floor(iterations / 20))
		try {
			for (let i = 0; i < runs; i++) await resolverTraffic(200)
			console.log(`  ok   resolver traffic (${runs} runs of 200 steps)`)
		} catch (error) {
			failures++
			console.log(`  FAIL resolver traffic: ${error.stack}`)
		}
	}

	console.log(failures ? `${failures} target(s) failed; rerun with --seed=${seed}` : 'no failing input found')
	process.exit(failures ? 1 : 0)
}

main()
