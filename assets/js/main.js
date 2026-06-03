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
