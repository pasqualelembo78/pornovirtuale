<?php
/**
 * Theme JS: menu mobile, header auto-hide, ricerca rapida.
 *
 * @package Pornovirtuale
 */

(function () {
	'use strict';

	function init() {
		var header = document.getElementById('site-header');
		var toggle = document.getElementById('nav-toggle');
		var nav = document.getElementById('site-nav');

		if (toggle && nav) {
			toggle.addEventListener('click', function () {
				var open = nav.classList.toggle('is-open');
				toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
			});

			// Sottomenu su mobile.
			nav.querySelectorAll('.menu-item-has-children > a').forEach(function (link) {
				link.addEventListener('click', function (e) {
					if (window.innerWidth > 1024) {
						return;
					}
					e.preventDefault();
					link.parentElement.classList.toggle('is-open');
				});
			});
		}

		// Header auto-hide allo scroll.
		if (header) {
			var last = 0;
			window.addEventListener('scroll', function () {
				var st = window.pageYOffset;
				if (st > 220 && st > last) {
					header.classList.add('is-hidden');
				} else {
					header.classList.remove('is-hidden');
				}
				last = st <= 0 ? 0 : st;
			});
		}

		// Evidenzia il link di menu attivo.
		var path = window.location.pathname;

		document.querySelectorAll('#site-nav a').forEach(function (a) {
			if (a.pathname === path && path !== '/') {
				a.style.color = '#ff6f91';
			}
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
