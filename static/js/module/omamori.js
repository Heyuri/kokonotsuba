(function () {
	const meta = document.querySelector('meta[name="prefsKey"]');
	if (!meta || !meta.content) return;

	const name = meta.content;
	const storageKey = 'koko.' + name;

	const valid = /^[a-f0-9]{32}\.[a-f0-9]{16}$/;

	const DB_NAME = 'koko';
	const STORE_NAME = 'kv';

	function readCookie() {
		const escaped = name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
		const match = document.cookie.match(new RegExp('(?:^|; )' + escaped + '=([^;]*)'));
		return match ? decodeURIComponent(match[1]) : '';
	}

	function writeCookie(value) {
		const expires = new Date(Date.now() + 730 * 86400000).toUTCString();
		document.cookie = name + '=' + encodeURIComponent(value) +
			'; expires=' + expires + '; path=/; SameSite=Lax' +
			(location.protocol === 'https:' ? '; Secure' : '');
	}

	function readLocal() {
		try {
			return localStorage.getItem(storageKey) || '';
		} catch (e) {
			// Private mode or storage disabled - the other two still work.
			return '';
		}
	}

	function writeLocal(value) {
		try { localStorage.setItem(storageKey, value); } catch (e) {}
	}

	// IndexedDB, wrapped down to get/set on one key. Every failure resolves rather than rejects:
	// this is a second copy of something we already have, never a reason to stop.
	function openDb() {
		return new Promise(function (resolve) {
			let request;
			try {
				request = indexedDB.open(DB_NAME, 1);
			} catch (e) {
				resolve(null);
				return;
			}

			request.onupgradeneeded = function () {
				try { request.result.createObjectStore(STORE_NAME); } catch (e) {}
			};
			request.onsuccess = function () { resolve(request.result); };
			request.onerror = function () { resolve(null); };
			request.onblocked = function () { resolve(null); };
		});
	}

	function withStore(mode, run) {
		return openDb().then(function (db) {
			if (!db) return '';

			return new Promise(function (resolve) {
				let store;
				try {
					store = db.transaction(STORE_NAME, mode).objectStore(STORE_NAME);
				} catch (e) {
					resolve('');
					return;
				}

				const request = run(store);
				request.onsuccess = function () { resolve(request.result || ''); };
				request.onerror = function () { resolve(''); };
			});
		});
	}

	function readDb() {
		return withStore('readonly', function (store) { return store.get(storageKey); });
	}

	function writeDb(value) {
		return withStore('readwrite', function (store) { return store.put(value, storageKey); });
	}

	function clean(value) {
		return valid.test(value) ? value : '';
	}

	window.kokoPrefs = readDb().then(function (fromDb) {
		const cookie = clean(readCookie());
		const local = clean(readLocal());
		const db = clean(fromDb);

		const token = [local, db, cookie].filter(Boolean)[0] || '';

		if (!token) return '';

		if (token !== cookie) writeCookie(token);
		if (token !== local) writeLocal(token);
		if (token !== db) return writeDb(token).then(function () { return token; });

		return token;
	});
})();
