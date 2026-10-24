/* Menu responsive (tema Pornovirtuale) */
(function () {
	'use strict';

	function initMenu() {
		var hamburger = document.getElementById('hamburger');
		var navMenu = document.getElementById('nav-menu');
		var header = document.getElementById('header');

		if (!hamburger || !navMenu || !header) {
			return;
		}

		hamburger.addEventListener('click', function () {
			navMenu.classList.toggle('active');
		});

		document.querySelectorAll('#nav-menu .dropdown > a').forEach(function (link) {
			link.addEventListener('click', function (e) {
				if (window.innerWidth <= 1024) {
					e.preventDefault();
					var parent = link.parentElement;
					var open = parent.classList.contains('active');

					document.querySelectorAll('#nav-menu .dropdown').forEach(function (d) {
						d.classList.remove('active');
					});

					if (!open) {
						parent.classList.add('active');
					}
				}
			});
		});

		document.querySelectorAll('#nav-menu a').forEach(function (link) {
			link.addEventListener('click', function () {
				if (window.innerWidth <= 1024 && !link.closest('.dropdown')) {
					navMenu.classList.remove('active');
				}
			});
		});

		var last = 0;
		window.addEventListener('scroll', function () {
			var st = window.pageYOffset;
			header.style.top = st > last ? '-100px' : '0';
			last = st <= 0 ? 0 : st;
		});

		window.addEventListener('resize', function () {
			if (window.innerWidth > 1024) {
				navMenu.classList.remove('active');
				document.querySelectorAll('#nav-menu .dropdown').forEach(function (d) {
					d.classList.remove('active');
				});
			}
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initMenu);
	} else {
		initMenu();
	}
})();
