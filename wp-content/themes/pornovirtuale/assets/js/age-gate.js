// Age gate 18+ per siti adult: overlay bloccante, confermato in localStorage
(function () {
	'use strict';

	var cfg = window.pvAgeGate || {};
	var KEY = cfg.storageKey || 'pv_age_ok';
	var SITE = cfg.siteName || 'Pornovirtuale.com';
	var EXIT_URL = cfg.exitUrl || 'https://www.google.com';

	try {
		if (localStorage.getItem(KEY) === '1') {
			return;
		}
	} catch (e) {
		// localStorage non disponibile: si mostra comunque il gate
	}

	var ov = document.createElement('div');
	ov.id = 'age-gate';
	ov.setAttribute('role', 'dialog');
	ov.setAttribute('aria-modal', 'true');
	ov.setAttribute('aria-label', 'Verifica di eta');
	ov.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.94);z-index:9999;display:flex;align-items:center;justify-content:center;font-family:Roboto,sans-serif;color:#fff;text-align:center;padding:20px';

	ov.innerHTML =
		"<div style='max-width:520px'>" +
		"<h1 style='font-family:Orbitron,sans-serif;color:#FF3C5B;margin-bottom:12px'>" + SITE + "</h1>" +
		"<p style='margin-bottom:8px'>Il sito contiene contenuti espliciti riservati a maggiorenni.</p>" +
		"<p style='margin-bottom:18px;font-size:.9em;color:#aaa'>Confermando dichiari di avere almeno 18 anni e di essere in possesso di un documento d'identita valido.</p>" +
		"<button id='ag-yes' style='padding:12px 28px;background:#FF3C5B;color:#fff;border:0;border-radius:8px;font-weight:600;cursor:pointer;margin-right:8px'>Ho 18 anni o piu</button>" +
		"<a id='ag-no' href='" + EXIT_URL + "' style='padding:12px 28px;background:#2B2B3C;color:#fff;border-radius:8px;text-decoration:none;display:inline-block'>Esci</a>" +
		"</div>";

	function show() {
		document.body.appendChild(ov);

		var yes = document.getElementById('ag-yes');
		if (yes) {
			yes.addEventListener('click', function () {
				try {
					localStorage.setItem(KEY, '1');
				} catch (e) {}
				ov.remove();
			});
		}
	}

	if (document.body) {
		show();
	} else {
		document.addEventListener('DOMContentLoaded', show);
	}
})();
