/**
 * Estimate — storefront progressive enhancement.
 *
 * The "Add to quote" links work without JavaScript (they navigate to the quote
 * page). This script upgrades them to add via AJAX so the shopper stays put, with
 * an accessible live-region announcement and a busy state. No dependencies.
 */
(function () {
	'use strict';

	var data = window.estimateData || {};

	if (!data.ajaxUrl || !data.nonce || !data.action) {
		return;
	}

	var live = document.createElement('div');
	live.setAttribute('aria-live', 'polite');
	live.className = 'screen-reader-text';
	document.addEventListener('DOMContentLoaded', function () {
		document.body.appendChild(live);
	});

	function announce(message) {
		live.textContent = '';
		window.setTimeout(function () {
			live.textContent = message;
		}, 50);
	}

	document.addEventListener('click', function (event) {
		var link = event.target.closest('.estimate-add-to-quote');

		if (!link) {
			return;
		}

		var productId = link.getAttribute('data-product-id');

		if (!productId || link.getAttribute('data-added') === '1') {
			return; // Let the link navigate (no-JS fallback / already added).
		}

		event.preventDefault();
		link.setAttribute('aria-busy', 'true');

		var body = new URLSearchParams();
		body.append('action', data.action);
		body.append('nonce', data.nonce);
		body.append('product_id', productId);

		fetch(data.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString()
		})
			.then(function (response) {
				return response.json();
			})
			.then(function (json) {
				link.removeAttribute('aria-busy');

				if (json && json.success) {
					link.setAttribute('data-added', '1');
					link.classList.add('estimate-added');
					link.textContent = data.addedLabel || link.textContent;
					announce((json.data && json.data.message) || '');
				} else {
					// On failure, fall back to navigating to the quote page.
					window.location = link.getAttribute('href');
				}
			})
			.catch(function () {
				link.removeAttribute('aria-busy');
				window.location = link.getAttribute('href');
			});
	});
})();
