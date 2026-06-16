/**
 * Estimate — storefront enhancement.
 *
 * One earned micro-interaction: when the quote request is sent, draw the
 * "tally rule" under the submit button — the under-total stroke an estimator
 * rules beneath a column of figures before writing the total. Presentation
 * only; the form submits and the server handles everything as before. With JS
 * off, the button works exactly the same, just without the drawn rule.
 */
( function () {
	'use strict';

	function onReady( fn ) {
		if ( document.readyState !== 'loading' ) {
			fn();
		} else {
			document.addEventListener( 'DOMContentLoaded', fn );
		}
	}

	onReady( function () {
		var form = document.querySelector( '.estimate-quote__form' );

		if ( ! form ) {
			return;
		}

		var slot = form.querySelector( '.estimate-quote__submit' );

		if ( ! slot ) {
			return;
		}

		form.addEventListener( 'submit', function () {
			// Mark the figures as totted up; CSS draws the rule. The browser
			// then navigates on submit as normal — no behaviour is changed.
			slot.classList.add( 'is-tallying' );
		} );
	} );
}() );
