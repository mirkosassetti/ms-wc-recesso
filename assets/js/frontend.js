/**
 * MS Recesso 54-bis — minimal vanilla JS progressive enhancement.
 *
 * - Moves focus to a validation-error notice (only) so screen-reader and
 *   keyboard users are taken to the problem. Headings are never focused, to
 *   avoid a focus outline appearing on the title at page load.
 * - Prevents accidental double submission of the confirmation form.
 */
( function () {
	'use strict';

	function focusError() {
		var target = document.querySelector( '.ms-recesso-notice--error' );
		if ( ! target ) {
			return;
		}
		if ( ! target.hasAttribute( 'tabindex' ) ) {
			target.setAttribute( 'tabindex', '-1' );
		}
		target.focus( { preventScroll: false } );
	}

	function guardConfirm() {
		var form = document.querySelector( '.ms-recesso__confirm-form' );
		if ( ! form ) {
			return;
		}
		form.addEventListener( 'submit', function () {
			var button = form.querySelector( 'button[type="submit"]' );
			if ( button ) {
				button.disabled = true;
				button.classList.add( 'is-busy' );
			}
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		focusError();
		guardConfirm();
	} );
} )();
