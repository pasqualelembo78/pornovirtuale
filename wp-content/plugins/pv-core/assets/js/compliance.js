/* PV Core - overlay compliance (age gate + cookie banner). */
(function () {
	'use strict';

	var cfg = window.pvCompliance || {};

	function setCookie(name, value, days) {
		var d = new Date();
		d.setTime(d.getTime() + (days * 24 * 60 * 60 * 1000));
		var str = name + '=' + encodeURIComponent(value) + ';expires=' + d.toUTCString() + ';path=/;SameSite=Lax';
		if (location.protocol === 'https:') {
			str += ';Secure';
		}
		document.cookie = str;
	}

	function getCookie(name) {
		var m = document.cookie.match('(^|;)\\s*' + name + '\\s*=\\s*([^;]+)');
		return m ? decodeURIComponent(m.pop()) : null;
	}

	function parseConsent(s) {
		try {
			return JSON.parse(s);
		} catch (e) {
			return null;
		}
	}

	function isBot() {
		return /bot|crawl|spider|slurp|google|bingbot/i.test(navigator.userAgent);
	}

	function blockMedia() {
		if (isBot()) {
			return;
		}
		document.querySelectorAll('img, video, iframe, source').forEach(function (el) {
			if (el.hasAttribute('data-pv-blocked') || el.hasAttribute('data-noscan')) {
				return;
			}
			if (el.src) {
				el.setAttribute('data-pv-src', el.src);
				el.removeAttribute('src');
			}
			if (el.getAttribute('srcset')) {
				el.setAttribute('data-pv-srcset', el.getAttribute('srcset'));
				el.removeAttribute('srcset');
			}
			el.setAttribute('data-pv-blocked', '1');
			el.style.opacity = '0';
			el.style.transition = 'opacity .25s ease';
		});
	}

	function unblockMedia() {
		document.querySelectorAll('[data-pv-blocked]').forEach(function (el) {
			if (el.getAttribute('data-pv-src')) {
				el.src = el.getAttribute('data-pv-src');
			}
			if (el.getAttribute('data-pv-srcset')) {
				el.srcset = el.getAttribute('data-pv-srcset');
			}
			el.removeAttribute('data-pv-blocked');
			el.style.opacity = '1';
		});
	}

	document.addEventListener('DOMContentLoaded', function () {
		var overlay = document.getElementById('pv-compliance-overlay');
		if (!overlay) {
			return;
		}

		var acceptAge = document.getElementById('pv-accept-age');
		var declineAge = document.getElementById('pv-decline-age');
		var verifyId = document.getElementById('pv-verify-id');
		var acceptAll = document.getElementById('pv-accept-all');
		var acceptNecessary = document.getElementById('pv-accept-necessary');
		var declineAll = document.getElementById('pv-decline-all');
		var cookieSettingsBtn = document.getElementById('pv-cookie-settings');
		var saveSettings = document.getElementById('pv-save-cookie-settings');
		var analyticsBox = document.getElementById('pv-analytics-cookie');
		var marketingBox = document.getElementById('pv-marketing-cookie');
		var settingsPanel = document.getElementById('pv-cookie-settings-panel');
		var mainParts = document.querySelectorAll('main, header, footer, .pv-player, .site-content');

		function showOverlay() {
			overlay.style.display = 'flex';
			mainParts.forEach(function (n) {
				if (n && n.classList) {
					n.classList.add('blurred');
				}
			});
			blockMedia();
		}

		function hideOverlay() {
			overlay.style.display = 'none';
			mainParts.forEach(function (n) {
				if (n && n.classList) {
					n.classList.remove('blurred');
				}
			});
			unblockMedia();
		}

		function serverVerify(method) {
			if (!cfg.ajaxUrl || !cfg.nonce) {
				return;
			}

			var body = new URLSearchParams();
			body.append('action', 'pv_age_verify');
			body.append('nonce', cfg.nonce);
			body.append('method', method);

			fetch(cfg.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: body.toString()
			}).then(function (r) {
				return r.json();
			}).then(function (json) {
				if (json && json.success && json.data && json.data.provider_url) {
					window.location.href = json.data.provider_url;
					return;
				}
				if (json && json.success) {
					setCookie('over18', 'true', 365);
					localStorage.setItem('over18', 'true');
				}
			}).catch(function () {
				setCookie('over18', 'true', 365);
				localStorage.setItem('over18', 'true');
			});
		}

		var over18 = getCookie('over18') === 'true' || localStorage.getItem('over18') === 'true';
		var consent = parseConsent(getCookie('cookiesConsent')) || localStorage.getItem('cookiesChoice');

		if (!over18 || !consent) {
			showOverlay();
		} else {
			hideOverlay();
		}

		acceptAge && acceptAge.addEventListener('click', function () {
			setCookie('over18', 'true', 365);
			localStorage.setItem('over18', 'true');
			serverVerify(cfg.mode === 'provider' ? 'provider' : 'declaration');
			if (getCookie('cookiesConsent') || localStorage.getItem('cookiesChoice')) {
				hideOverlay();
			}
		});

		verifyId && verifyId.addEventListener('click', function () {
			serverVerify('provider');
		});

		declineAge && declineAge.addEventListener('click', function () {
			window.location.href = cfg.exitUrl || 'https://www.google.com';
		});

		function saveChoice(choice) {
			setCookie('cookiesConsent', JSON.stringify(choice), 365);
			localStorage.setItem('cookiesChoice', '1');
			hideOverlay();
		}

		acceptAll && acceptAll.addEventListener('click', function () {
			saveChoice({ necessary: true, analytics: true, marketing: true });
		});

		acceptNecessary && acceptNecessary.addEventListener('click', function () {
			saveChoice({ necessary: true, analytics: false, marketing: false });
		});

		declineAll && declineAll.addEventListener('click', function () {
			saveChoice({ necessary: true, analytics: false, marketing: false });
		});

		cookieSettingsBtn && cookieSettingsBtn.addEventListener('click', function () {
			if (settingsPanel) {
				settingsPanel.style.display = settingsPanel.style.display === 'block' ? 'none' : 'block';
			}
		});

		saveSettings && saveSettings.addEventListener('click', function () {
			saveChoice({
				necessary: true,
				analytics: !!(analyticsBox && analyticsBox.checked),
				marketing: !!(marketingBox && marketingBox.checked)
			});
		});

		blockMedia();
	});
})();
