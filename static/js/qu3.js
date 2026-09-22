// Helper functions for hover previews and backlinks
const MIN_WIDTH     = 0
const OFFSET_X      = 10
const RIGHT_MARGIN  = 30
const PREVIEW_DELAY = 300
const REMOVAL_DELAY = 50

let previewStack   = []
let lastMouseEvent = null
let cleanupTimer   = null

// Cache for remote post API fetches (keyed by post_uid)
const fetchCache = new Map()

// Text quote rules and the API client for them; loaded by the page header, or by
// ensureLookupLibrary() on a static page built before quoteLookup.js existed
const LOOKUP_LIBRARY_SRC = (document.currentScript?.src || '').replace(/qu3\.js(\?.*)?$/, 'quoteLookup.js')
let quoteResolver = null

// What findSource() needs to know about a post and about a thread's posts, read once
let postRecords   = new WeakMap()
let threadPosts   = new WeakMap()

function createPreviewBox(notFound = false, loading = false) {
	const box = document.createElement('div')
	box.classList.add('previewBox')
	box.style.position  = 'absolute'
	box.style.zIndex    = '9998'
	box.style.minWidth  = `${MIN_WIDTH}px`
	box.style.display   = 'none'
	if (notFound) {
		box.innerHTML = `
			<div class="post reply">
				Quote source not found
			</div>
		`
	} else if (loading) {
		const fetchingText = document.querySelector('meta[name="postApiFetchingText"]')?.content || 'Fetching post...'
		box.innerHTML = `
			<div class="post reply">
				${fetchingText}
			</div>
		`
	}
	document.body.appendChild(box)
	return box
}

/** Get the post API base URL from the meta tag injected by the postApi module. */
function getPostApiUrl() {
	return document.querySelector('meta[name="postApiUrl"]')?.content || null
}

/**
 * Whether a preview should be asked for with this reader's session.
 *
 * Staff do: a post is then rendered for them as it would be on the page, with the controls and
 * the poster's address, and the answer is marked so no cache keeps it. Everybody else asks
 * without cookies, which makes every reader's request identical and lets a shared cache answer
 * it. The page says which, and static html never claims staff.
 */
function apiCredentials() {
	return document.querySelector('meta[name="postApiStaff"]') ? 'include' : 'omit'
}

/** Fetch post data from the API by post_uid. Returns a promise; results are cached. */
function fetchPostData(postUid) {
	if (fetchCache.has(postUid)) return fetchCache.get(postUid)

	const apiUrl = getPostApiUrl()
	if (!apiUrl) {
		const rejected = Promise.resolve(null)
		fetchCache.set(postUid, rejected)
		return rejected
	}

	const separator = apiUrl.includes('?') ? '&' : '?'
	const url = `${apiUrl}${separator}post_uid=${encodeURIComponent(postUid)}`

	const promise = fetch(url, { credentials: apiCredentials() })
		.then(res => res.ok ? res.json() : null)
		.catch(() => null)

	fetchCache.set(postUid, promise)
	return promise
}

/** Build a post element from API data containing server-rendered HTML. */
function buildPostFromApi(data) {
	if (!data || !data.html) return null

	const wrapper = document.createElement('div')
	wrapper.innerHTML = data.html

	const post = wrapper.querySelector('.post')
	if (!post) return null

	// Remove the deletion checkbox from the preview
	const checkbox = post.querySelector('.deletionCheckbox')
	if (checkbox) checkbox.remove()

	// a reply does not say which thread it is from, and its own text quotes need to
	if (data.parent_thread_uid) post.dataset.threadUid = data.parent_thread_uid

	return post
}

/** Prefetch post data for a quotelink whose target is not in the DOM. */
function prefetchPost(event) {
	const el = event.currentTarget
	const postUid = el.dataset.postUid
	const targetId = el.dataset.targetId
	if (!postUid || !targetId || document.getElementById(targetId)) return

	fetchPostData(postUid)
}

function positionPreviewBox(box, e) {
	const vw = window.innerWidth, vh = window.innerHeight
	box.style.display  = 'block'

	// Anchor the box's left edge at the cursor's x position, then shrink it to
	// fit the space remaining to the right (rather than relocating it) so the
	// preview stays at the cursor's x location even when the post is wider than
	// the screen. setProperty(...,'important') so it beats the stylesheet.
	const left  = Math.max(0, e.clientX - OFFSET_X)
	const avail = vw - RIGHT_MARGIN - left
	box.style.setProperty('max-width', `${Math.max(MIN_WIDTH, avail)}px`, 'important')
	box.style.left = `${left}px`

	const rect  = e.target.getBoundingClientRect()
	const h     = box.offsetHeight
	const below = rect.bottom + window.scrollY
	const above = rect.top    + window.scrollY - h

	box.style.top = `${(rect.bottom + h > vh)
		? Math.max(above, window.scrollY)
		: below}px`
}

function attachPreviewHandlers(obj) {
	const { box, trigger } = obj
	box.addEventListener('mouseenter', () => {})
	box.addEventListener('mouseleave', () => setTimeout(checkPreviews, REMOVAL_DELAY))
	trigger.addEventListener('mouseenter', () => {})
	trigger.addEventListener('mouseleave', () => setTimeout(checkPreviews, REMOVAL_DELAY))
	trigger.addEventListener('mousemove', e => positionPreviewBox(box, e))
}

function checkPreviews() {
	previewStack.slice().forEach(obj => {
		if (!isHoveredOrDescendant(obj)) removeRecursively(obj)
	})
}

function isHoveredOrDescendant(obj) {
	if (obj.box.matches(':hover') || obj.trigger.matches(':hover')) return true
	return previewStack
		.filter(c => c.parent === obj)
		.some(isHoveredOrDescendant)
}

function removeRecursively(obj) {
	previewStack
		.filter(c => c.parent === obj)
		.forEach(removeRecursively)
	if (obj.box.parentNode) obj.box.parentNode.removeChild(obj.box)
	previewStack = previewStack.filter(c => c !== obj)
}

/** Open a preview box for a trigger and return its stack entry. */
function openPreview(trigger, contextPost, notFound = false, loading = false) {
	const parentBox  = trigger.closest('.previewBox')
	const parentPrev = parentBox
		? previewStack.find(o => o.box === parentBox)
		: null

	const box = createPreviewBox(notFound, loading)
	const obj = { box, trigger, parent: parentPrev, contextPost }
	previewStack.push(obj)
	attachPreviewHandlers(obj)
	return obj
}

function showPreview(obj) {
	positionPreviewBox(obj.box, lastMouseEvent)
	obj.box.style.display = 'block'
}

/** Put a post into a preview box: a copy of one on the page, or one the API rendered. */
function fillPreview(box, post, isCopy) {
	const shown = isCopy ? post.cloneNode(true) : post

	// a preview is a copy of something, so it carries no ids: two elements answering to one id
	// would send every getElementById on the page into the preview
	shown.removeAttribute('id')
	shown.querySelectorAll('[id]').forEach(el => el.removeAttribute('id'))

	shown.style.margin = '0'
	box.innerHTML = ''
	box.appendChild(shown)
	applyHoverListeners(box)
}

function fillNotFound(box) {
	box.innerHTML = `<div class="post reply">Quote source not found</div>`
}

/** Fill a preview from an API answer, preferring the page's own copy of that post. */
function fillFromApi(box, data) {
	const rendered = buildPostFromApi(data)
	if (!rendered) return false

	const onPage = rendered.id ? document.getElementById(rendered.id) : null
	if (onPage && !onPage.closest('.previewBox')) fillPreview(box, onPage, true)
	else fillPreview(box, rendered, false)
	return true
}

function getQuoteResolver() {
	if (!quoteResolver && window.kkQuoteLookup && getPostApiUrl()) {
		quoteResolver = window.kkQuoteLookup.createResolver({
			apiUrl: getPostApiUrl(),
			fetch: url => fetch(url, { credentials: apiCredentials() }),
		})
	}
	return quoteResolver
}

/**
 * Preview a text quote whose source may be among posts the page does not hold: ask the API,
 * and fall back to the match the page has, if any.
 */
function showLookupPreview(trigger) {
	const resolver = getQuoteResolver()
	const fallback = document.getElementById(trigger.dataset.targetId)

	if (!resolver) {
		const obj = openPreview(trigger, fallback, !fallback)
		if (fallback) fillPreview(obj.box, fallback, true)
		showPreview(obj)
		return
	}

	const obj = openPreview(trigger, fallback, false, true)
	showPreview(obj)

	resolver.resolve(
		trigger.dataset.quoteThread,
		trigger.dataset.quoteBefore,
		trigger.dataset.quoteText,
		trigger.dataset.quoteQuoted === '1'
	).then(data => {
		if (!previewStack.includes(obj)) return

		if (data && fillFromApi(obj.box, data)) {
			// numbered quotes of the same post can reuse the answer
			if (data.post_uid && !fetchCache.has(String(data.post_uid))) {
				fetchCache.set(String(data.post_uid), Promise.resolve(data))
			}
		} else if (fallback) {
			fillPreview(obj.box, fallback, true)
		} else {
			fillNotFound(obj.box)
		}

		if (lastMouseEvent) positionPreviewBox(obj.box, lastMouseEvent)
	})
}

function startHover(event) {
	const trigger = event.currentTarget
	if (trigger.hoverTimeout) return

	lastMouseEvent = event
	function track(e) { lastMouseEvent = e }
	document.addEventListener('mousemove', track)

	trigger.hoverTimeout = setTimeout(() => {
		trigger.hoverTimeout = null
		document.removeEventListener('mousemove', track)

		if (trigger.classList.contains('replies-label')) {
			showAggregated(trigger, lastMouseEvent)
			return
		}

		const targetId = trigger.dataset.targetId
		if (!targetId) return

		if (trigger.dataset.quoteLookup) {
			showLookupPreview(trigger)
			return
		}

		const post = document.getElementById(targetId)

		// Post is in the DOM — show it directly
		if (post) {
			const obj = openPreview(trigger, post)
			fillPreview(obj.box, post, true)
			showPreview(obj)
			return
		}

		// Post not in DOM — try remote fetch via data-post-uid
		const postUid = trigger.dataset.postUid
		if (!postUid) {
			showPreview(openPreview(trigger, null, true))
			return
		}

		// Show loading state, then fetch
		const obj = openPreview(trigger, null, false, true)
		showPreview(obj)

		fetchPostData(postUid).then(data => {
			// If the preview was already removed while fetching, bail out
			if (!previewStack.includes(obj)) return

			if (!data || !fillFromApi(obj.box, data)) fillNotFound(obj.box)

			// Reposition after content change
			if (lastMouseEvent) positionPreviewBox(obj.box, lastMouseEvent)
		})
	}, PREVIEW_DELAY)
}

function stopHover(event) {
	const trigger = event.currentTarget
	if (trigger.hoverTimeout) {
		clearTimeout(trigger.hoverTimeout)
		trigger.hoverTimeout = null
	}
	if (cleanupTimer) clearTimeout(cleanupTimer)
	cleanupTimer = setTimeout(checkPreviews, REMOVAL_DELAY)
}

function trackHoverMove(event) {
	const obj = previewStack.find(o => o.trigger === event.currentTarget)
	if (obj) positionPreviewBox(obj.box, event)
}

function showAggregated(trigger, e) {
	const container = trigger.parentElement
	let refs = container._refs
	if (!refs && container.dataset.refs) {
		try { refs = JSON.parse(container.dataset.refs) } catch { refs = [] }
	}
	if (!refs || !refs.length) return
	refs = refs.slice().sort((a, b) => Number(a.num) - Number(b.num))

	const box  = createPreviewBox()
	const wrap = document.createElement('div')
	refs.forEach(r => {
		const p = document.getElementById(r.id)
		if (p) {
			const c = p.cloneNode(true)
			c.removeAttribute('id')
			c.style.margin = '0'
			const w = document.createElement('div')
			w.appendChild(c)
			wrap.appendChild(w)
		}
	})
	box.innerHTML = ''
	box.appendChild(wrap)

	const parentBox  = trigger.closest('.previewBox')
	const parentPrev = parentBox
		? previewStack.find(o => o.box === parentBox)
		: null
	const obj = {
		box,
		trigger,
		parent: parentPrev,
		contextPost: container.closest('.post')
	}
	previewStack.push(obj)

	attachPreviewHandlers(obj)
	applyHoverListeners(box)
	positionPreviewBox(box, e)
	box.style.display = 'block'
}

/** What a text quote is matched against, read from a post once and kept until it changes. */
function postRecord(post) {
	let record = postRecords.get(post)
	if (record) return record

	record = {
		id: post.id,
		number: Number(post.dataset.postNumber || post.id.split('_').pop()),
		lines: commentLines(post.querySelector('.comment')),
		fileNames: fileNamesOf(post),
		gapBefore: false,
	}
	postRecords.set(post, record)
	return record
}

/** The comment's lines as a reader sees them, which is what the server compares against. */
function commentLines(comment) {
	if (!comment) return []

	// <br> contributes nothing to textContent, so it becomes a real break first
	const clone = comment.cloneNode(true)
	clone.querySelectorAll('br').forEach(br => br.replaceWith(document.createTextNode('\n')))

	return clone.textContent.split(/\r\n|\r|\n/)
}

/** The full names of a post's attachments, as the file line shows them. */
function fileNamesOf(post) {
	// the download link carries the whole name; the visible one is truncated
	let names = Array.from(post.querySelectorAll('.filesize a[data-filename]'), a => a.dataset.filename)

	if (!names.length) {
		names = Array.from(post.querySelectorAll('.filesize'), bar => {
			const shown = bar.querySelector('a')
			return shown
				? (shown.getAttribute('onmouseover')?.match(/this\.textContent='([^']+)'/)?.[1] || shown.textContent.trim())
				: ''
		})
	}

	return Array.from(new Set(names.filter(Boolean)))
}

/** A thread's posts on the page, in order, and whether replies are missing after the OP. */
function postsOfThread(threadElem) {
	let entry = threadPosts.get(threadElem)
	if (!entry) {
		const notices = Array.from(threadElem.querySelectorAll('.omittedposts'))
		entry = {
			posts: Array.from(threadElem.querySelectorAll('.post.op, .post.reply'))
				.filter(p => !p.closest('.previewBox')),
			// a notice without data-gap was drawn before the attribute existed: assume a gap
			hasGap: notices.some(n => n.dataset.gap !== '0'),
		}
		threadPosts.set(threadElem, entry)
	}
	return entry
}

/**
 * Find the source of a text quote among the posts on the page.
 *
 * @returns {{id: string, lookup: object|null}} id is a post's element id or 'notFound';
 * lookup is set when the API should be asked on hover, because the page is missing posts
 * the source could be among
 */
function findQuoteSource(text, post, quoted) {
	const lib = window.kkQuoteLookup
	const ownThread = post.closest('.thread')
	const threadUid = ownThread?.dataset.threadUid || post.dataset.threadUid
	const threadElem = ownThread || (threadUid
		? document.querySelector(`.thread[data-thread-uid="${CSS.escape(threadUid)}"]`)
		: null)
	const selfUid = Number(post.dataset.postUid)

	let posts, selfIndex, outsidePage = false
	if (threadElem) {
		const entry = postsOfThread(threadElem)
		posts = entry.posts
		selfIndex = posts.indexOf(post)
		if (selfIndex === -1) {
			// a post the API rendered: only what was posted before it counts, and the
			// page says nothing about the posts around it
			outsidePage = true
			posts = posts.filter((p, i) => i === 0 || Number(p.dataset.postUid) < selfUid)
			selfIndex = posts.length
		}
	} else if (threadUid) {
		// a post the API rendered from a thread that is not on the page at all
		outsidePage = true
		posts = []
		selfIndex = 0
	} else {
		// no thread to go by (a search result, say): whatever the page holds
		posts = Array.from(document.querySelectorAll('.post.op, .post.reply')).filter(p => !p.closest('.previewBox'))
		selfIndex = posts.indexOf(post)
		if (selfIndex === -1) selfIndex = posts.length
	}

	const hasGap = threadElem ? postsOfThread(threadElem).hasGap : false
	const records = posts.map((p, i) => {
		const record = postRecord(p)
		// the only place a page leaves replies out is between the OP and the first one drawn
		return i === 1 && hasGap ? Object.assign({}, record, { gapBefore: true }) : record
	})

	const found = lib.findSource(records, selfIndex, text, quoted)
	// a post number names one post, so finding it on the page settles it
	const settled = found.id && lib.postNumber(text) !== null
	const needsLookup = found.crossedGap || (outsidePage && !settled)
	const needle = lib.normalizeNeedle(text)

	return {
		id: found.id || 'notFound',
		lookup: needsLookup && threadUid && selfUid > 0 && needle !== null
			? { thread: threadUid, before: selfUid, text: needle, quoted }
			: null,
	}
}

function processPost(post) {
	if (post.dataset.backlinksProcessed) return
	post.dataset.backlinksProcessed = 'true'

	const numEl   = post.querySelector('.postnum .qu')
	if (!numEl) return
	const replyNum = numEl.textContent.trim()

	// a post drawn inside a preview is a throwaway copy: its quotes still get their targets, so
	// hovering them works, but it must not add itself to the backlinks of the posts it quotes
	const wantBack = _kkSetting('addbacklinks') && !post.closest('.previewBox')

	post.querySelectorAll('.comment .unkfunc, .comment a.quotelink').forEach(el => {
		let targetId = el.dataset.targetId
		if (!targetId) {
			const href = el.tagName.toLowerCase() === 'a' && el.classList.contains('quotelink')
				? (el.getAttribute('href') || '')
				: ''
			if (href.includes('#')) {
				targetId = href.split('#').pop()
			} else {
				// a quote line holding a >>123 link is previewed by the link
				if (el.querySelector('a.quotelink')) return

				const quote = window.kkQuoteLookup.parseQuote(el.textContent)
				if (!quote) return

				const source = findQuoteSource(quote.text, post, quote.quoted)
				targetId = source.id
				if (source.lookup) {
					el.dataset.quoteLookup = '1'
					el.dataset.quoteThread = source.lookup.thread
					el.dataset.quoteBefore = source.lookup.before
					el.dataset.quoteText   = source.lookup.text
					el.dataset.quoteQuoted = source.lookup.quoted ? '1' : '0'
				}
			}
		}
		if (!targetId) return

		el.dataset.targetId = targetId

		if (wantBack) {
			const tgt = document.getElementById(targetId)
			if (!tgt) return

			let container = tgt.querySelector('.backlinks')
			if (!container) {
				container = document.createElement('span')
				container.className = 'backlinks'
				const info = tgt.querySelector('.postinfo') || tgt
				info.appendChild(container)
			}

			if (!container._refs) container._refs = []
			if (container._refs.some(r => r.id === post.id)) return

			container._refs.push({ id: post.id, num: replyNum })
			container.dataset.refs = JSON.stringify(container._refs)

			container.innerHTML = ''
			const label = document.createElement('a')
			label.href         = 'javascript:void(0)'
			label.className    = 'replies-label'
			label.style.cursor = 'pointer'
			label.textContent  = `Replies(${container._refs.length}):`
			container.appendChild(label)

			container._refs.forEach(r => {
				const link = document.createElement('a')
				link.href             = `#${r.id}`
				link.className        = 'backlink'
				link.textContent      = `>>${r.num}`
				link.dataset.targetId = r.id
				container.appendChild(document.createTextNode(' '))
				container.appendChild(link)
			})

			applyHoverListeners(container)
		}
	})

	applyHoverListeners(post)
}

function hasPostNode(nodes) {
	return Array.from(nodes).some(n => n.nodeType === 1 && !n.classList.contains('previewBox')
		&& (n.matches('.post, .omittedposts') || n.querySelector('.post, .omittedposts')))
}

function observeNewPosts() {
	const obs = new MutationObserver(muts => {
		muts.forEach(m => {
			// what was read from a post is stale once its comment or file line changes
			const changed = m.target.nodeType === 1 && m.target.closest('.comment, .filesize')?.closest('.post')
			if (changed) postRecords.delete(changed)
			const inPreview = m.target.nodeType === 1 && m.target.closest('.previewBox')
			if (!inPreview && (hasPostNode(m.addedNodes) || hasPostNode(m.removedNodes))) threadPosts = new WeakMap()

			m.addedNodes.forEach(n => {
				if (n.nodeType !== 1) return
				if (n.matches('.post.op, .post.reply')) processPost(n)
				else n.querySelectorAll('.post.op, .post.reply').forEach(processPost)
			})
		})
	})
	obs.observe(document.body, { childList: true, subtree: true })
}

function applyHoverListeners(root) {
	root.querySelectorAll('[data-target-id]').forEach(el => {
		if (
			!el.classList.contains('unkfunc')
		 && !el.classList.contains('backlink')
		 && !el.classList.contains('quotelink')
		) return
		el.removeEventListener('mouseover', startHover)
		el.removeEventListener('mouseout',  stopHover)
		el.removeEventListener('mouseenter', prefetchPost)
		el.addEventListener   ('mouseover', startHover)
		el.addEventListener   ('mouseout',  stopHover)
		el.addEventListener   ('mouseenter', prefetchPost)
	})
	root.querySelectorAll('.replies-label').forEach(el => {
		el.removeEventListener('mouseover',  startHover)
		el.removeEventListener('mouseout',   stopHover)
		el.removeEventListener('mousemove',  trackHoverMove)
		el.addEventListener   ('mouseover',  startHover)
		el.addEventListener   ('mouseout',   stopHover)
		el.addEventListener   ('mousemove',  trackHoverMove)
	})
}

/** Run once the text quote library is there, loading it when the page's header predates it. */
function ensureLookupLibrary(then) {
	if (window.kkQuoteLookup) return then()

	const script = document.createElement('script')
	script.src = LOOKUP_LIBRARY_SRC
	script.onload = then
	script.onerror = () => console.error('ERROR: quoteLookup.js could not be loaded; hover previews are off.')
	document.head.appendChild(script)
}

function init() {
	document.addEventListener('mousemove', e => {
		lastMouseEvent = e
		// Only churn the cleanup timer while previews are actually open;
		// otherwise this fires clearTimeout/setTimeout on every mouse frame for nothing.
		if (!previewStack.length) return
		if (cleanupTimer) clearTimeout(cleanupTimer)
		cleanupTimer = setTimeout(checkPreviews, REMOVAL_DELAY)
	}, { passive: true })

	document.querySelectorAll('.post.op, .post.reply').forEach(processPost)
	applyHoverListeners(document)
	observeNewPosts()
}

// KOKOJS module definition
const kkhoverbacklink = {
	name: "Heyuri Hover Previews + Backlinks",
	startup: function() {
		ensureLookupLibrary(init)
		return true
	},
	reset: function() {
		document.querySelectorAll('.previewBox').forEach(el => el.remove())
		document.querySelectorAll('.backlinks').forEach(el => el.remove())
		document.querySelectorAll('[data-backlinks-processed]').forEach(el => el.removeAttribute('data-backlinks-processed'))
		previewStack = []
		postRecords  = new WeakMap()
		threadPosts  = new WeakMap()
	},
}

if (typeof KOKOJS !== "undefined") {
	kkjs.modules.push(kkhoverbacklink)
	kkSetting.add({ key: "addbacklinks", label: "Add reply backlinks", onChange: function () {
		kkhoverbacklink.reset()
		kkhoverbacklink.startup()
	} }, "Quotes & Replies")
} else {
	console.error("ERROR: KOKOJS not loaded! Please load 'koko.js' before this script.")
}
