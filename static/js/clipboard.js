(function() {
	'use strict';

	var fileInput = document.querySelector('input[type="file"][name^="upfile"]');
	if (!fileInput) return;

	// Determines how many files may be attached at once
	var rawLimit = fileInput.getAttribute('data-attachment-limit');
	var attachmentLimit = (rawLimit && !isNaN(parseInt(rawLimit, 10)))
		? parseInt(rawLimit, 10)
		: 1;

	// References for handling optional animated GIF checkbox behavior
	var anigifWrapper = document.getElementById('anigifContainer');
	var anigifInput = document.getElementById('anigif');
	var anigifLimit = 0;
	if (anigifInput && anigifInput.getAttribute('data-size-limit')) {
		anigifLimit = parseInt(anigifInput.getAttribute('data-size-limit'), 10) || 0;
	}

	// Holds all selected or pasted files in memory before syncing with file input
	var filesState = [];
	var allowedPreviewTypes = ['image/jpeg','image/png','image/gif','image/bmp','image/webp','image/svg+xml'];
	var ignoreChange = false;

	// Hides the server's built-in GIF checkbox since this script replaces that UI
	if (anigifWrapper) anigifWrapper.style.display = 'none';

	// Determines a suitable extension for a given MIME type
	function getFileExt(m) {
		switch (m) {
			case "image/jpeg": return ".jpg";
			case "image/png": return ".png";
			case "image/gif": return ".gif";
			case "image/bmp": return ".bmp";
			case "image/webp": return ".webp";
			case "image/svg+xml": return ".svg";
			default: return "";
		}
	}

	// Splits a filename into its base name and extension
	function splitName(n) {
		if (!n) return { nameBase:'image', extension:'' };
		var p = n.lastIndexOf('.');
		if (p <= 0) return { nameBase:n, extension:'' };
		return { nameBase:n.slice(0,p), extension:n.slice(p) };
	}

	// Returns how many more files the user may add
	function remaining() {
		return Math.max(0, attachmentLimit - filesState.length);
	}

	function canAdd() {
		return remaining() > 0;
	}

	// Rebuilds the <input type="file"> value to reflect the internal file state
	function syncInputFiles() {
		var dt = new DataTransfer();
		for (var i=0;i<filesState.length;i++) {
			var st = filesState[i];
			var f = new File([st.blob], st.nameBase + st.extension, { type: st.type });
			dt.items.add(f);
		}
		ignoreChange = true;
		fileInput.files = dt.files;
		fileInput.dispatchEvent(new Event('change',{ bubbles:true }));
		ignoreChange = false;
	}

	// ----------------------------------------
	// Dropzone UI for multi-file selection
	// ----------------------------------------

	function makeDropzone() {
		var wrap = document.createElement('div');
		wrap.id = 'userscript-dropzone-wrap';
		wrap.style.userSelect = 'none';
		wrap.style.marginTop = '4px';

		var dz = document.createElement('div');
		dz.tabIndex = 0;
		dz.style.border = '2px dashed #888';
		dz.style.padding = '12px';
		dz.style.borderRadius = '6px';
		dz.style.cursor = 'pointer';
		dz.style.textAlign = 'center';
		dz.style.color = '#666';

		var hint = document.createElement('div');
		hint.textContent = 'Select / drop / paste images here';

		dz.appendChild(hint);
		wrap.appendChild(dz);
		fileInput.after(wrap);

		// Hidden picker allows traditional browsing while keeping the UI consistent
		var hiddenPicker = document.createElement('input');
		hiddenPicker.type = 'file';
		hiddenPicker.multiple = true;
		hiddenPicker.style.display='none';
		if (fileInput.accept) hiddenPicker.setAttribute('accept', fileInput.getAttribute('accept'));
		wrap.after(hiddenPicker);

		dz.addEventListener('click', function(){
			if (!canAdd()) return;
			hiddenPicker.click();
		});

		// Provides visual feedback while dragging files over the dropzone
		dz.addEventListener('dragover', function(e){
			e.preventDefault(); e.stopPropagation();
			dz.style.borderColor='#33a'; dz.style.color='#33a';
		});

		dz.addEventListener('dragleave', function(e){
			e.preventDefault(); e.stopPropagation();
			dz.style.borderColor='#888'; dz.style.color='#666';
		});

		// Accepts dropped files and forwards them to the file handler
		dz.addEventListener('drop', function(e){
			e.preventDefault(); e.stopPropagation();
			dz.style.borderColor='#888'; dz.style.color='#666';

			var fl = e.dataTransfer.files;
			if (!fl || !fl.length) return;

			var slots = remaining();
			for (var i=0;i<fl.length && slots>0;i++,slots--) addFile(fl[i], fl[i].name);
		});

		hiddenPicker.addEventListener('change', function(){
			if (!hiddenPicker.files.length) return;
			var slots = remaining();
			for (var i=0;i<hiddenPicker.files.length && slots>0;i++,slots--)
				addFile(hiddenPicker.files[i], hiddenPicker.files[i].name);
			hiddenPicker.value='';
		});
	}

	// ----------------------------------------
	// Rendering and layout of file entries
	// ----------------------------------------

	function ensureList() {
		var c = document.getElementById('userscript-file-list-container');
		if (!c) {
			c = document.createElement('div');
			c.id='userscript-file-list-container';
			c.style.display='flex';
			c.style.flexWrap='wrap';
			c.style.gap='8px';
			c.style.marginTop='4px';

			var dz = document.getElementById('userscript-dropzone-wrap');
			if (dz) dz.after(c);
			else fileInput.after(c);
		}
		return c;
	}

	// Removes the file list container entirely when no files remain
	function clearList() {
		var c = document.getElementById('userscript-file-list-container');
		if (c) c.remove();
	}

	// Updates the displayed list of files, including previews and controls
	function render() {
		if (!filesState.length) clearList();
		else {
			var c = ensureList();
			c.innerHTML='';
			for (var i=0;i<filesState.length;i++) c.appendChild(renderBlock(filesState[i], i));
		}

		var dz = document.getElementById('userscript-dropzone-wrap');
		if (dz) dz.style.display = canAdd() ? 'block' : 'none';
	}

	// Creates a single file entry including filename input, preview, size display, and actions
	function renderBlock(st,index) {
		var b=document.createElement('div');
		b.style.display='inline-block';
		b.style.maxWidth='220px';

		// Removes this file from the selection
		var x=document.createElement('span');
		x.innerHTML='[<a href="javascript:void(0);">X</a>]';
		x.style.display='block';
		x.style.marginBottom='4px';
		x.querySelector('a').addEventListener('click',function(e){
			e.preventDefault(); removeFile(index);
		});
		b.appendChild(x);

		// Allows the user to rename the file before submission
		var fn=document.createElement('div');
		var l=document.createElement('label');
		l.textContent='Filename';
		var inp=document.createElement('input');
		inp.type='text'; inp.classList.add('inputtext'); inp.style.width='100%';
		inp.value=st.nameBase;
		inp.addEventListener('input',function(){
			st.nameBase = inp.value || 'image';
			syncInputFiles();
		});
		fn.appendChild(l); fn.appendChild(inp);
		b.appendChild(fn);

		// Displays the file's size in kilobytes
		var sc=document.createElement('div');
		var sl=document.createElement('label');
		sl.textContent='File size';
		var sv=document.createElement('div');
		sv.textContent=(st.blob.size/1024).toFixed(2)+' KB';
		sc.appendChild(sl); sc.appendChild(sv);
		b.appendChild(sc);

		// Shows a preview for supported image formats
		if (allowedPreviewTypes.indexOf(st.type)!==-1) {
			var img=document.createElement('img');
			img.style.display='block';
			img.style.marginTop='4px';
			img.style.maxWidth='200px';
			img.style.height='auto';
			var fr=new FileReader();
			fr.onload=e=>img.src=e.target.result;
			fr.readAsDataURL(st.blob);
			b.appendChild(img);

			// Offers a conversion option for WebP files, since PNG is more widely supported
			if (st.type==='image/webp') {
				var bw=document.createElement('div');
				var cb=document.createElement('button');
				cb.textContent='Convert WebP to PNG';
				cb.addEventListener('click',function(e){
					e.preventDefault(); convertWebP(index,img,sv,cb);
				});
				bw.appendChild(cb);
				b.appendChild(bw);
			}
		}

		// Adds a toggle for animated GIFs if the server allows it and the file is within limits
		if (st.type === 'image/gif' && anigifInput && anigifLimit > 0) {
			var sizeKB = st.blob.size / 1024;
			if (sizeKB <= anigifLimit) {
				var wrap = document.createElement('div');
				wrap.style.marginTop='6px';

				var before=document.createElement('span');
				before.textContent='[';

				var label=document.createElement('label');
				label.style.cursor='pointer';
				label.style.margin='0 4px';

				var chk=document.createElement('input');
				chk.type='checkbox';
				chk.name='anigif';
				chk.value='on';
				chk.style.marginRight='4px';

				label.appendChild(chk);
				label.appendChild(document.createTextNode('Animated GIF'));

				var after=document.createElement('span');
				after.textContent=']';

				wrap.appendChild(before);
				wrap.appendChild(label);
				wrap.appendChild(after);

				b.appendChild(wrap);
			}
		}

		return b;
	}

	// Converts a WebP file to PNG and updates its state
	function convertWebP(index,img,sizeDiv,btn) {
		var st=filesState[index];
		if (!st || st.type!=='image/webp') return;

		btn.style.opacity='0.5';
		btn.style.pointerEvents='none';

		var cvs=document.createElement('canvas');
		var ctx=cvs.getContext('2d');
		var im=new Image();

		im.onload=function(){
			cvs.width=im.width;
			cvs.height=im.height;
			ctx.drawImage(im,0,0);
			cvs.toBlob(function(p){
				if (!p) return;
				st.blob=p;
				st.type='image/png';
				st.extension='.png';
				sizeDiv.textContent=(p.size/1024).toFixed(2)+' KB';
				var fr=new FileReader();
				fr.onload=e=>img.src=e.target.result;
				fr.readAsDataURL(p);
				syncInputFiles();
			},'image/png');
		};

		var fr2=new FileReader();
		fr2.onload=e=>im.src=e.target.result;
		fr2.readAsDataURL(st.blob);
	}

	// Removes a file from the interface and syncs the input
	function removeFile(i){
		filesState.splice(i,1);
		render();
		syncInputFiles();
	}

	// Adds a new file to the internal state and triggers rendering
	function addFile(f,name){
		var p=splitName(name||f.name||'image');
		var ext=p.extension || getFileExt(f.type);
		var st = {
			blob:f,
			nameBase:p.nameBase||'image',
			extension:ext,
			type:f.type||'application/octet-stream'
		};

		// Single-file mode replaces any existing file
		if (attachmentLimit <= 1) {
			filesState = [st];
		} else {
			if (!canAdd()) return;
			filesState.push(st);
		}

		syncInputFiles();
		render();
	}

	// ----------------------------------------
	// Global event handling
	// ----------------------------------------

	// Multi-file mode hides the original file input UI
	if (attachmentLimit > 1) {
		fileInput.style.display = 'none';
		makeDropzone();
	} else {
		fileInput.style.display = '';
	}

	// Processes files selected through the input element
	fileInput.addEventListener('change',function(){
		if (ignoreChange) return;

		if (!fileInput.files || !fileInput.files.length) {
			if (attachmentLimit <= 1 && anigifWrapper) {
				anigifWrapper.style.display = 'none';
				if (anigifInput) anigifInput.checked = false;
			}
			return;
		}

		if (attachmentLimit <= 1) {
			addFile(fileInput.files[0], fileInput.files[0].name);
		} else {
			fileInput.value='';
		}
	});

	// Allows pasted images to be processed just like dropped or selected files
	document.addEventListener('paste',function(e){
		var cd=e.clipboardData || (e.originalEvent && e.originalEvent.clipboardData);
		if (!cd || !cd.items) return;

		if (attachmentLimit <= 1) {
			for (var i=0;i<cd.items.length;i++) {
				if (cd.items[i].kind==='file') {
					var bl=cd.items[i].getAsFile();
					if (bl) addFile(bl, bl.name);
					break;
				}
			}
			return;
		}

		var slots=remaining();
		for (var j=0;j<cd.items.length && slots>0;j++){
			var it=cd.items[j];
			if (it.kind==='file') {
				var b=it.getAsFile();
				if (b) { addFile(b,b.name); slots--; }
			}
		}
	});

	// Automatically displays an already-selected single file on page load
	if (attachmentLimit <= 1 && fileInput.files && fileInput.files.length === 1) {
		addFile(fileInput.files[0], fileInput.files[0].name);
	}
})();
