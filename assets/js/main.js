/*
	Spectral by HTML5 UP — Enhanced + Fixed
	Production Stable Version
*/

(function($) {

	var $window = $(window),
		$body = $('body'),
		$wrapper = $('#page-wrapper'),
		$banner = $('#banner'),
		$header = $('#header'),
		$menu = $('#menu');

	// =============================
	// BREAKPOINTS
	// =============================
	breakpoints({
		xlarge: [ '1281px','1680px' ],
		large: [ '981px','1280px' ],
		medium: [ '737px','980px' ],
		small: [ '481px','736px' ],
		xsmall: [ null,'480px' ]
	});

	// =============================
	// INITIAL LOAD ANIMATION
	// =============================
	$window.on('load', function() {
		setTimeout(() => $body.removeClass('is-preload'), 100);
	});

	// =============================
	// MOBILE DETECTION
	// =============================
//	if (browser.mobile) {
//		$body.addClass('is-mobile');
//	} else {
//		breakpoints.on('>medium', () => $body.removeClass('is-mobile'));
//		breakpoints.on('<=medium', () => $body.addClass('is-mobile'));
//	}

	// =============================
	// SMOOTH SCROLL
	// =============================
	$('.scrolly').scrolly({
		speed: 1500,
		offset: $header.length ? $header.outerHeight() : 0
	});

	// =============================
	// MENU PANEL (HTML5UP)
	// =============================
	if ($menu.length) {

		$menu
			.append('<a href="#menu" class="close"></a>')
			.appendTo($body)
			.panel({
				delay: 300,
				hideOnClick: true,
				hideOnSwipe: true,
				resetScroll: true,
				resetForms: true,
				side: 'right',
				target: $body,
				visibleClass: 'is-menu-visible'
			});

		// ESC closes menu
		$(document).on('keydown', e => {
			if(e.key === "Escape")
				$body.removeClass('is-menu-visible');
		});
	}

	// =============================
	// HEADER SHRINK ON SCROLL
	// =============================
	$window.on('scroll', function(){

		if($window.scrollTop() > 80)
			$header.addClass('scrolled');
		else
			$header.removeClass('scrolled');

	});

	// =============================
	// BANNER ALT HEADER
	// =============================
	if ($banner.length > 0 && $header.hasClass('alt')) {

		$window.on('resize', debounce(() => {
			$window.trigger('scroll');
		}, 150));

		$banner.scrollex({
			bottom: $header.outerHeight() + 1,
			terminate: () => $header.removeClass('alt'),
			enter: () => $header.addClass('alt'),
			leave: () => $header.removeClass('alt')
		});
	}

	// =============================
	// FORM DOUBLE SUBMIT PROTECTION
	// =============================
	$('form').on('submit', function(){
		var $btn = $(this).find('input[type=submit]');
		if($btn.data('submitted')) return false;
		$btn.data('submitted', true).val('Saadan...');
	});

	// =============================
	// LAZY IMAGE AUTO
	// =============================
	$('img').each(function(){
		if(!$(this).attr('loading'))
			$(this).attr('loading','lazy');
	});

	// =============================
	// FILE UPLOAD PREVIEWS
	// =============================
	(function initFileUploadPreviews() {
		var inputs = document.querySelectorAll('input[type="file"][name="attachments[]"]');
		var imagePattern = /\.(jpe?g|png|gif|webp)$/i;

		if (!inputs.length) {
			return;
		}

		if (!document.getElementById('file-upload-preview-styles')) {
			var style = document.createElement('style');
			style.id = 'file-upload-preview-styles';
			style.textContent = '.file-upload-preview{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin:14px 0 24px;width:100%;letter-spacing:0;text-transform:none}.file-upload-preview[hidden]{display:none}.file-preview-item{display:flex;gap:10px;align-items:center;min-width:0;padding:10px;border:1px solid rgba(255,255,255,.22);border-radius:8px;background:rgba(255,255,255,.07);color:#fff}.file-preview-thumb{width:58px;height:58px;flex:0 0 58px;border-radius:6px;object-fit:cover;background:rgba(0,0,0,.18)}.file-preview-icon{width:58px;height:58px;flex:0 0 58px;display:flex;align-items:center;justify-content:center;border-radius:6px;background:rgba(255,255,255,.12);font-size:.72em;font-weight:700;letter-spacing:.04em;text-transform:uppercase}.file-preview-meta{min-width:0}.file-preview-name{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:.82em;line-height:1.35}.file-preview-size{display:block;color:rgba(255,255,255,.68);font-size:.72em;line-height:1.35}.file-preview-remove{flex:0 0 auto;margin-left:auto;width:34px;height:34px;border:1px solid rgba(255,255,255,.35);border-radius:50%;background:rgba(0,0,0,.12);color:#fff;cursor:pointer;font-size:20px;line-height:1;padding:0}.file-preview-remove:hover{background:rgba(255,255,255,.16)}@media screen and (max-width:736px){.file-upload-preview{grid-template-columns:1fr}.file-preview-item{padding:9px}.file-preview-thumb,.file-preview-icon{width:52px;height:52px;flex-basis:52px}}';
			document.head.appendChild(style);
		}

		function formatSize(bytes) {
			if (!bytes && bytes !== 0) {
				return '';
			}

			if (bytes < 1024) {
				return bytes + ' B';
			}

			if (bytes < 1024 * 1024) {
				return (bytes / 1024).toFixed(1) + ' KB';
			}

			return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
		}

		function getExtension(fileName) {
			var parts = fileName.split('.');
			return parts.length > 1 ? parts.pop().slice(0, 5) : 'fail';
		}

		function getFileKey(file) {
			return [file.name, file.size, file.lastModified].join('|');
		}

		function syncInputFiles(input) {
			if (typeof DataTransfer === 'undefined') {
				return;
			}

			var transfer = new DataTransfer();
			(input._selectedFiles || []).forEach(function(file) {
				transfer.items.add(file);
			});
			input.files = transfer.files;
		}

		function clearPreview(preview) {
			var urls = preview._objectUrls || [];
			urls.forEach(function(url) {
				URL.revokeObjectURL(url);
			});
			preview._objectUrls = [];
			preview.innerHTML = '';
			preview.hidden = true;
		}

		function renderPreview(input, preview) {
			var files = input._selectedFiles || Array.prototype.slice.call(input.files || []);
			clearPreview(preview);

			if (!files.length) {
				return;
			}

			preview.hidden = false;

			files.forEach(function(file, index) {
				var item = document.createElement('div');
				var media;
				var meta = document.createElement('div');
				var name = document.createElement('span');
				var size = document.createElement('span');
				var remove = document.createElement('button');

				item.className = 'file-preview-item';
				meta.className = 'file-preview-meta';
				name.className = 'file-preview-name';
				size.className = 'file-preview-size';
				remove.className = 'file-preview-remove';
				remove.type = 'button';
				remove.setAttribute('aria-label', 'Eemalda fail ' + file.name);
				remove.textContent = '×';
				name.textContent = file.name;
				size.textContent = formatSize(file.size);

				if (imagePattern.test(file.name)) {
					var objectUrl = URL.createObjectURL(file);
					preview._objectUrls.push(objectUrl);

					media = document.createElement('img');
					media.className = 'file-preview-thumb';
					media.src = objectUrl;
					media.alt = file.name;
				} else {
					media = document.createElement('div');
					media.className = 'file-preview-icon';
					media.textContent = getExtension(file.name);
				}

				remove.addEventListener('click', function() {
					input._selectedFiles = (input._selectedFiles || []).filter(function(selectedFile, selectedIndex) {
						return selectedIndex !== index;
					});
					syncInputFiles(input);
					renderPreview(input, preview);
				});

				meta.appendChild(name);
				meta.appendChild(size);
				item.appendChild(media);
				item.appendChild(meta);
				item.appendChild(remove);
				preview.appendChild(item);
			});
		}

		inputs.forEach(function(input) {
			var preview = document.createElement('div');
			var form = input.closest('form');
			var actions = input.closest('.glass-actions');
			var fieldWrap = input.closest('.col-12') || input.parentElement;

			preview.className = 'file-upload-preview';
			preview.hidden = true;
			preview._objectUrls = [];
			input._selectedFiles = [];

			if (actions) {
				actions.insertAdjacentElement('afterend', preview);
			} else if (fieldWrap) {
				fieldWrap.insertAdjacentElement('afterend', preview);
			} else {
				input.insertAdjacentElement('afterend', preview);
			}

			input.addEventListener('change', function() {
				var seen = {};
				var nextFiles = [];

				(input._selectedFiles || []).concat(Array.prototype.slice.call(input.files || [])).forEach(function(file) {
					var key = getFileKey(file);
					if (!seen[key]) {
						seen[key] = true;
						nextFiles.push(file);
					}
				});

				input._selectedFiles = nextFiles;
				syncInputFiles(input);
				renderPreview(input, preview);
			});

			if (form) {
				form.addEventListener('reset', function() {
					setTimeout(function() {
						input._selectedFiles = [];
						clearPreview(preview);
					}, 0);
				});
			}
		});
	})();

	// =============================
	// SUBMENU (STABLE VERSION)
	// =============================
	document.addEventListener('click', function(event) {
		var header = event.target.closest && event.target.closest('#menu .submenu-header');

		if(!header) return;

		event.preventDefault();
		event.stopPropagation();

		if(event.stopImmediatePropagation)
			event.stopImmediatePropagation();

		var submenu = header.closest('.submenu');

		if(!submenu) return;

		document.querySelectorAll('#menu .submenu.open').forEach(function(item) {
			if(item !== submenu)
				item.classList.remove('open');
		});

		submenu.classList.toggle('open');
	}, true);

	$('#menu').on('click', '.submenu-header', function(e){

		e.preventDefault();
		e.stopPropagation();

		var $parent = $(this).closest('.submenu');

		// ainult üks korraga lahti
		$('.submenu').not($parent).removeClass('open');

		$parent.toggleClass('open');
	});

// =============================
// ACTIVE MENU AUTO DETECT (FIXED)
// =============================
var path = window.location.pathname.split("/").pop();

// eemalda .html / .php / trailing slash
path = path.replace('.html','').replace('.php','').replace('/','');

$('#menu a, #nav a').each(function(){

	var href = $(this).attr('href');

	if(!href) return;

	href = href.replace('.html','').replace('.php','').replace('/','');

	if(href === path){
		$(this).addClass('active');

		// kui submenu sees → ava ka parent
		$(this).closest('.submenu').addClass('open');
	}
});

	// =============================
	// CONTACT FORM BACKEND SUBMIT
	// =============================
	document.addEventListener('submit', async function(event) {
		var form = event.target;

		if (!form || form.id !== 'contactForm') {
			return;
		}

		event.preventDefault();
		event.stopPropagation();

		if (event.stopImmediatePropagation)
			event.stopImmediatePropagation();

		var submitButton = form.querySelector('button[type="submit"], input[type="submit"]');
		var messageBox = document.getElementById('contactMessage');

		if (!messageBox) {
			messageBox = document.createElement('div');
			messageBox.id = 'contactMessage';
			messageBox.className = 'form-message';
			form.parentNode.insertBefore(messageBox, form);
		}

		var originalText = submitButton ? (submitButton.value || submitButton.textContent) : '';
		var formData = new FormData(form);
		formData.set('source', window.location.href);

		if (submitButton) {
			submitButton.disabled = true;
			if (submitButton.tagName === 'INPUT')
				submitButton.value = 'Saadan...';
			else
				submitButton.textContent = 'Saadan...';
		}

		messageBox.hidden = false;
		messageBox.textContent = 'Saadan paringut...';

		try {
			var response = await fetch('/api/contact.php', {
				method: 'POST',
				body: formData,
				headers: {
					'Accept': 'application/json'
				}
			});

			var result = {};
			var responseText = await response.text();

			try {
				result = responseText ? JSON.parse(responseText) : {};
			} catch (parseError) {
				result = {};
			}

			if (!response.ok || !result.success)
				throw new Error(result.message || 'Paringu saatmine ebaonnestus. Palun proovi hiljem uuesti.');

			messageBox.textContent = result.message || 'Paring saadetud. Votame sinuga uhendust.';
			form.reset();
		} catch (error) {
			messageBox.textContent = error.message || 'Serveri viga. Palun proovi hiljem uuesti.';
		} finally {
			if (submitButton) {
				submitButton.disabled = false;
				if (submitButton.tagName === 'INPUT')
					submitButton.value = originalText;
				else
					submitButton.textContent = originalText;
			}
		}
	}, true);

	// =============================
	// RESIZE DEBOUNCE
	// =============================
	function debounce(func, wait){
		var timeout;
		return function(){
			clearTimeout(timeout);
			timeout = setTimeout(func, wait);
		};
	}

})(jQuery);
