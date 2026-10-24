/* Player PV Core: facade al clic, vista stereo e fullscreen 360. */
(function () {
	'use strict';

	function activate(el) {
		var iframe = el.querySelector('[data-pv-src]');
		var poster = el.querySelector('[data-pv-play]');

		if (!iframe) {
			return;
		}

		if (!iframe.src) {
			var src = iframe.getAttribute('data-pv-src');
			if (src) {
				iframe.src = src;
			}
		}

		el.classList.add('is-playing');

		if (poster) {
			poster.setAttribute('aria-hidden', 'true');
		}
	}

	function bindPlayer(root) {
		var players = root.querySelectorAll('.pv-player');

		Array.prototype.forEach.call(players, function (player) {
			var poster = player.querySelector('[data-pv-play]');

			if (poster && !poster.dataset.pvBound) {
				poster.dataset.pvBound = '1';
				poster.addEventListener('click', function () {
					activate(player);
				});
			}

			var vrToggle = player.querySelector('[data-pv-vr-toggle]');

			if (vrToggle && !vrToggle.dataset.pvBound) {
				vrToggle.dataset.pvBound = '1';
				vrToggle.addEventListener('click', function () {
					var on = player.classList.toggle('is-stereo');
					vrToggle.setAttribute('aria-pressed', on ? 'true' : 'false');
				});
			}

			var fsButton = player.querySelector('[data-pv-vr-fullscreen]');

			if (fsButton && !fsButton.dataset.pvBound) {
				fsButton.dataset.pvBound = '1';
				fsButton.addEventListener('click', function () {
					var frame = player.querySelector('.pv-player__frame');

					if (!frame) {
						return;
					}

					if (document.fullscreenElement) {
						document.exitFullscreen();
					} else if (frame.requestFullscreen) {
						frame.requestFullscreen();
					}
				});
			}
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () {
			bindPlayer(document);
		});
	} else {
		bindPlayer(document);
	}

	// Rende attivi anche i player inseriti dinamicamente.
	if (window.MutationObserver) {
		new MutationObserver(function (mutations) {
			mutations.forEach(function (m) {
				Array.prototype.forEach.call(m.addedNodes, function (node) {
					if (node.nodeType === 1) {
						bindPlayer(node);
					}
				});
			});
		}).observe(document.documentElement, { childList: true, subtree: true });
	}
})();
