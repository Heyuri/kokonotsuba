// Helper functions for hover previews and backlinks
const MIN_WIDTH     = 0
const OFFSET_X      = 10
const RIGHT_MARGIN  = 30
const PREVIEW_DELAY = 300
const REMOVAL_DELAY = 50

let previewStack   = []
let lastMouseEvent = null
let cleanupTimer   = null

function createPreviewBox(notFound = false) {
	const box = document.createElement('div')
	box.classList.add('previewBox')
	box.style.position  = 'absolute'
	box.style.zIndex    = '9999'
	box.style.minWidth  = `${MIN_WIDTH}px`
	box.style.display   = 'none'
	if (notFound) {
		box.innerHTML = `
			<div class="post reply">
				Quote source not found
			</div>
		`
	}
	document.body.appendChild(box)
	return box
}

function positionPreviewBox(box, e) {
	const vw = window.innerWidth, vh = window.innerHeight
	box.style.maxWidth = `${vw - RIGHT_MARGIN}px`
	box.style.display  = 'block'

	const w = Math.max(box.offsetWidth, MIN_WIDTH)
	let left = e.clientX - OFFSET_X
	if (left + w > vw - RIGHT_MARGIN) left = vw - w - RIGHT_MARGIN
	box.style.left = `${Math.max(0, left)}px`

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

		const post       = document.getElementById(targetId)
		const parentBox  = trigger.closest('.previewBox')
		const parentPrev = parentBox
			? previewStack.find(o => o.box === parentBox)
			: null
		const box        = createPreviewBox(!post)
		const obj        = {
			box,
			trigger,
			parent: parentPrev,
			contextPost: post
		}
		previewStack.push(obj)

		if (post) {
			box.innerHTML = ''
			const clone = post.cloneNode(true)
			clone.removeAttribute('id')
			clone.style.margin = '0'
			box.appendChild(clone)
		}

		attachPreviewHandlers(obj)
		applyHoverListeners(box)
		positionPreviewBox(box, lastMouseEvent)
		box.style.display = 'block'
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

function findMatchingPostId(text, selfId, includeUnkfunc) {
	const nm = text.match(/^No\. ?(\d+)$/)
	if (nm) return `p${nm[1]}`

	const posts = Array.from(document.querySelectorAll('.post.op, .post.reply'))
		.reverse()
	for (const p of posts) {
		if (p.id === selfId) continue
		const comment = p.querySelector('.comment')
		if (comment) {
			let content
			if (!includeUnkfunc) {
				const clone = comment.cloneNode(true)
				clone.querySelectorAll('.unkfunc').forEach(el => el.remove())
				content = clone.textContent
			} else {
				content = comment.textContent
			}
			if (content.includes(text)) return p.id
		}
		const fl = p.querySelector('.filesize a')
		if (fl) {
			const vis  = fl.textContent.trim()
			const full = fl.getAttribute('onmouseover')
				?.match(/this\.textContent='([^']+)'/)?.[1] || vis
			if (full === text) return p.id
		}
	}
	// no match → signal "not found" so the preview shows your error message
	return 'notFound'
}

function processPost(post) {
	if (post.dataset.backlinksProcessed) return
	post.dataset.backlinksProcessed = 'true'

	const numEl   = post.querySelector('.postnum .qu')
	if (!numEl) return
	const replyNum = numEl.textContent.trim()
	const wantBack = localStorage.getItem('addbacklinks') === 'true'

	post.querySelectorAll('.comment .unkfunc, .comment a.quotelink').forEach(el => {
		let targetId = el.dataset.targetId
		if (!targetId) {
			const href = el.tagName.toLowerCase() === 'a' && el.classList.contains('quotelink')
				? (el.getAttribute('href') || '')
				: ''
			if (href.includes('#')) {
				targetId = href.split('#').pop()
			} else {
				const raw = el.textContent.trim()
				if (!raw.startsWith('>')) return
				let txt = raw.slice(1).trim()
				let dbl = false
				if (txt.startsWith('>')) {
					dbl = true
					txt = txt.slice(1).trim()
				}
				targetId = findMatchingPostId(txt, post.id, dbl)
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

function observeNewPosts() {
	const obs = new MutationObserver(muts => {
		muts.forEach(m => {
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
		if (!el.classList.contains('unkfunc') && !el.classList.contains('backlink')) return
		el.removeEventListener('mouseover', startHover)
		el.removeEventListener('mouseout',  stopHover)
		el.addEventListener   ('mouseover', startHover)
		el.addEventListener   ('mouseout',  stopHover)
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

function init() {
	document.addEventListener('mousemove', e => {
		lastMouseEvent = e
		if (cleanupTimer) clearTimeout(cleanupTimer)
		cleanupTimer = setTimeout(checkPreviews, REMOVAL_DELAY)
	})

	document.querySelectorAll('.post.op, .post.reply').forEach(processPost)
	applyHoverListeners(document)
	observeNewPosts()
}

// KOKOJS module definition
const kkhoverbacklink = {
	name: "Heyuri Hover Previews + Backlinks",
	startup: function() {
		if (!localStorage.getItem("addbacklinks")) {
			localStorage.setItem("addbacklinks", "false")
		}
		init()
		return true
	},
	reset: function() {
		document.querySelectorAll('.previewBox').forEach(el => el.remove())
		document.querySelectorAll('.backlinks').forEach(el => el.remove())
		document.querySelectorAll('[data-backlinks-processed]').forEach(el => el.removeAttribute('data-backlinks-processed'))
		previewStack = []
	},
	sett: function(tab, div) {
		if (tab !== "general") return
		div.innerHTML +=
			'<label><input type="checkbox" onchange="localStorage.setItem(\'addbacklinks\',this.checked);kkhoverbacklink.reset();kkhoverbacklink.startup();" ' +
			(localStorage.getItem("addbacklinks") === "true" ? 'checked' : '') +
			' /> Add Backlinks</label>'
	}
}

if (typeof KOKOJS !== "undefined") {
	kkjs.modules.push(kkhoverbacklink)
} else {
	console.error("ERROR: KOKOJS not loaded! Please load 'koko.js' before this script.")
}
