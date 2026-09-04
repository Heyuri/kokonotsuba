/**
 * postEdit.js - the post edit window.
 *
 * Opens the edit form in a draggable window, filled from the post's stored values rather than
 * from what the page happens to show, and patches the post in place afterwards. The server sends
 * back the edited post rendered through the normal pipeline, so the pieces an edit can change are
 * lifted straight out of that render: what you end up looking at is what a reload would show.
 *
 * Both edit windows run on this: the staff one, and the reader one for editing your own post.
 * They differ only in the form they clone and the endpoint their menu entry points at, so
 * register() takes both and everything below is shared.
 *
 * Depends on: postWidget.js, windowLibrary.js, message.js
 */
(function () {
	'use strict';

	// The staff and reader modules each ship this file, so a page showing both loads it twice.
	if (window.postEditWindow) return;

	// Wrappers that hold exactly one post. Some templates keep the subject and the tag outside
	// .post, so those are looked for in the wrapper, never wider, or a neighbouring post's
	// subject would be the first match.
	const POST_WRAPPER = '.op-container, .reply-container';

	/** The comment body, which shares its class with the notes/soudane strip below it. */
	const COMMENT_SELECTOR = '.comment:not(.belowComment)';

	/** Mirrors Kokonotsuba\post\textFormat: how a post's stored text is held. */
	const TEXT_FORMAT = { LEGACY_HTML: 0, PLAIN_TEXT: 1, RAW_HTML: 2 };

	/**
	 * Whether a post's stored text is already markup.
	 *
	 * A legacy post was escaped and marked up at post time, and a raw-HTML one is staff-authored
	 * markup on purpose; both are put in as HTML. Anything else is what the poster typed and only
	 * ever goes in as text.
	 */
	function storedTextIsHtml(textFormat) {
		return textFormat === TEXT_FORMAT.LEGACY_HTML || textFormat === TEXT_FORMAT.RAW_HTML;
	}

	/** Put stored text into an element as text, with its newlines as real line breaks. */
	function setTextWithBreaks(el, text) {
		el.textContent = '';

		String(text ?? '').split('\n').forEach(function (line, index) {
			if (index) el.appendChild(document.createElement('br'));
			el.appendChild(document.createTextNode(line));
		});
	}

	/** Find one of a post's parts, falling back to its wrapper for templates that render it there. */
	function findPart(post, selector) {
		const own = post.querySelector(selector);
		if (own) return own;

		const wrapper = post.closest(POST_WRAPPER);
		return wrapper ? wrapper.querySelector(selector) : null;
	}

	/** The wrapper a post's attachments are rendered into, whether or not it has any. */
	const ATTACHMENTS_SELECTOR = '.imageSourceContainer';

	/** Copy the contents of a freshly rendered part over the live one. */
	function replaceContents(livePart, freshPart) {
		if (!livePart || !freshPart) return;
		livePart.innerHTML = freshPart.innerHTML;
	}

	/**
	 * Sync the tag chip, which templates only render when the post has a tag: an edit can add one,
	 * drop one, or change it. It always sits directly after the subject.
	 */
	function syncTag(post, freshPost) {
		const liveTag = findPart(post, '.tag');
		const freshTag = findPart(freshPost, '.tag');

		if (liveTag && freshTag) {
			liveTag.replaceWith(document.importNode(freshTag, true));
			return;
		}

		if (liveTag) {
			liveTag.remove();
			return;
		}

		if (!freshTag) return;

		const title = findPart(post, '.title');
		if (title && title.parentNode) {
			title.parentNode.insertBefore(document.importNode(freshTag, true), title.nextSibling);
		}
	}

	/** Carry the post's data-post-* attributes over so the other widgets read the new values. */
	function syncDataAttributes(post, freshPost) {
		Array.prototype.forEach.call(freshPost.attributes, function (attr) {
			if (attr.name.indexOf('data-post-') === 0) {
				post.setAttribute(attr.name, attr.value);
			}
		});
	}

	/**
	 * Re-wire the page features that hang off the parts a patch replaces.
	 *
	 * The quote links in a patched comment and the anchors in a patched attachment block are new
	 * elements, so hover previews, quote inlining and image expansion have to be applied to them
	 * again. Every entry point here is idempotent, so nothing ends up wired twice.
	 */
	function rewirePost(post) {
		try {
			if (typeof processPost === 'function') {
				delete post.dataset.backlinksProcessed;
				processPost(post);
			}

			if (typeof kkinline !== 'undefined' && kkinline) {
				kkinline.startup();
			}

			if (typeof attachmentExpander !== 'undefined' && attachmentExpander) {
				attachmentExpander.startUpimageExpanding();
			}
		} catch (err) {
			console.error('Post edit re-init error:', err);
		}
	}

	/**
	 * Patch the live post from the server's render of it.
	 *
	 * @returns {boolean} false when the render carried no post to patch from.
	 */
	function patchFromHtml(post, html) {
		if (!html) return false;

		const freshPost = new DOMParser().parseFromString(html, 'text/html').querySelector('.post');
		if (!freshPost) return false;

		replaceContents(findPart(post, '.name'), findPart(freshPost, '.name'));
		replaceContents(findPart(post, '.title'), findPart(freshPost, '.title'));
		replaceContents(post.querySelector(COMMENT_SELECTOR), freshPost.querySelector(COMMENT_SELECTOR));
		// An edit can add or drop files, so the attachment block is patched like the rest.
		replaceContents(findPart(post, ATTACHMENTS_SELECTOR), findPart(freshPost, ATTACHMENTS_SELECTOR));
		syncTag(post, freshPost);
		syncDataAttributes(post, freshPost);
		rewirePost(post);

		return true;
	}

	/**
	 * Patch from the saved values alone.
	 *
	 * Only reached when the board's template has no post block to render (a listing template such
	 * as kokoflash), so the page is left showing the new text even without the markup around it.
	 */
	function patchFromFields(post, data) {
		// These are the post's stored values, not a render of them, so a plain-text post's text
		// goes in as text. Only a post whose stored text is already markup is put in as HTML.
		const asHtml = storedTextIsHtml(data.textFormat);

		const name = findPart(post, '.postername') || findPart(post, '.name');
		if (name) {
			if (asHtml) name.innerHTML = data.postUserName || '';
			else name.textContent = data.postUserName || '';
		}

		const title = findPart(post, '.title');
		if (title) {
			if (asHtml) title.innerHTML = data.subject || '';
			else title.textContent = data.subject || '';
		}

		const comment = post.querySelector(COMMENT_SELECTOR);
		if (comment) {
			if (asHtml) comment.innerHTML = (data.comment || '').replace(/\n/g, '<br>');
			else setTextWithBreaks(comment, data.comment);
		}

		post.dataset.postUserName = data.postUserName || '';
		post.dataset.postEmail = data.postEmail || '';

		rewirePost(post);
	}

	/** The post's stored values, which the page itself does not carry in an editable form. */
	async function fetchFields(url) {
		const fieldsUrl = new URL(url, location.href);
		fieldsUrl.searchParams.set('pageName', 'fields');

		const res = await fetch(fieldsUrl, {
			credentials: 'same-origin',
			headers: { 'X-Requested-With': 'XMLHttpRequest' }
		});

		if (!res.ok) throw await PostActionUtils.responseError(res);

		return res.json();
	}

	/** Put a stored value into a form field, if the form has one by that name. */
	function fillField(form, name, value) {
		const field = form.elements[name];
		if (field) field.value = value ?? '';
	}

	/**
	 * Select the post's tag, keeping a tag the board no longer offers rather than silently
	 * clearing it on the next save.
	 */
	function fillTag(form, tag) {
		const select = form.elements['tag'];
		if (!select) return;

		select.value = tag || '';

		if (select.value !== (tag || '')) {
			select.add(new Option(tag, tag, false, true));
		}
	}

	/**
	 * Draw the attachment row: one removable entry per file the post carries, and the input that
	 * adds more. The row stays hidden when this post's files are not the editor's to change - a
	 * text-only board, or a post past the reader's attachment window.
	 */
	function fillAttachments(form, fields) {
		const row = form.querySelector('.editAttachmentsRow');
		if (!row) return;

		row.hidden = !fields.canEditAttachments;
		if (!fields.canEditAttachments) return;

		const limit = fields.attachmentLimit || 1;
		const upload = row.querySelector('.editAttachmentUpload');
		if (upload) {
			upload.dataset.attachmentLimit = limit;
			upload.multiple = limit > 1;
		}

		const list = row.querySelector('.editAttachmentList');
		if (!list) return;

		list.textContent = '';

		const attachments = fields.attachments || [];

		if (!attachments.length) {
			const empty = document.createElement('i');
			empty.className = 'editAttachmentsEmpty';
			empty.textContent = list.dataset.emptyText || '';
			list.appendChild(empty);
			return;
		}

		attachments.forEach(function (attachment) {
			const entry = document.createElement('label');
			entry.className = 'editAttachmentEntry';

			const checkbox = document.createElement('input');
			checkbox.type = 'checkbox';
			checkbox.name = 'removeAttachments[]';
			checkbox.value = attachment.fileId;
			entry.appendChild(checkbox);

			if (attachment.thumbnailUrl) {
				const thumbnail = document.createElement('img');
				thumbnail.className = 'editAttachmentThumb';
				thumbnail.src = attachment.thumbnailUrl;
				thumbnail.alt = '';
				entry.appendChild(thumbnail);
			}

			const name = document.createElement('span');
			name.className = 'editAttachmentName';
			name.textContent = attachment.name || '';
			entry.appendChild(name);

			list.appendChild(entry);
		});
	}

	/** Whether the page being edited from shows a single thread, which is rendered differently. */
	function isThreadView() {
		return new URLSearchParams(location.search).has('res');
	}

	/**
	 * Wire one edit action to the form template its window clones.
	 *
	 * @param {string} action     Post menu action this handles.
	 * @param {string} templateId Selector of the <template> holding the form.
	 */
	function register(action, templateId) {
		window.postWidget.registerActionHandler(action, async function (ctx) {
			const postEl = ctx?.post || ctx?.arrow?.closest('.post');
			if (!postEl) return;

			let fields;
			try {
				fields = await fetchFields(ctx.url);
			} catch (err) {
				console.error('Post edit fetch error:', err);
				showMessage(PostActionUtils.errorMessage(err, 'Could not load that post for editing.'), false);
				return;
			}

			PostActionUtils.openWindow({
				templateId: templateId,
				title: 'Edit post No. ' + fields.postNumber,
				postEl,
				fields: [],
				onOpen: ({ form, win }) => {
					if (!form) return;

					// The heading sits outside the <form>, so the number is looked up in the window
					// instead. Scoping it there also stops a second edit window from writing into
					// the first one's heading, since every clone carries the same id.
					const scope = (win && win.div) ? win.div : form;

					const postNumber = scope.querySelector('#post_number');
					if (postNumber) postNumber.textContent = fields.postNumber;

					fillField(form, 'postUid', fields.postUid);
					fillField(form, 'postUserName', fields.postUserName);
					fillField(form, 'postEmail', fields.postEmail);
					fillField(form, 'subject', fields.subject);
					fillField(form, 'comment', fields.comment);
					fillTag(form, fields.tag);
					fillAttachments(form, fields);

					// A form whose <template> was baked into a cached page carries no usable token,
					// so the fields response hands it a fresh one. Staff forms send their own.
					if (fields.csrfToken) fillField(form, 'csrf_token', fields.csrfToken);
				},
				onSubmit: ({ formData }) => {
					// This request is made against the module URL, which says nothing about the page
					// it was made from, so the render context travels with it.
					formData.set('threadView', isThreadView() ? '1' : '0');
				},
				onSuccess: ({ res, postEl }) => {
					let data = res;
					if (typeof data === 'string') {
						try { data = JSON.parse(data); } catch (_) {}
					}

					if (!data) return;

					if (!patchFromHtml(postEl, data.html)) {
						patchFromFields(postEl, data);
					}

					showMessage('Edited post No. ' + data.postNumber, true);
				},
				onFail: ({ err }) => {
					console.error('Post edit error:', err);
					showMessage(PostActionUtils.errorMessage(err, 'There was an error while editing post.'), false);
				}
			});
		});
	}

	window.postEditWindow = { register: register };

	// Staff editing any post, and a poster editing their own with its password.
	register('editPost', '#postEditFormTemplate');
	register('editOwnPost', '#userPostEditFormTemplate');
})();
