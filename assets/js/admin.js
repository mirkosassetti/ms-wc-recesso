/**
 * MS Recesso 54-bis — admin list confirmation.
 *
 * Asks for confirmation before the permanent "delete" bulk action runs, since
 * withdrawal requests are evidentiary records.
 */
( function () {
	'use strict';

	function selectedAction( form ) {
		var top = form.querySelector( 'select[name="action"]' );
		if ( top && '-1' !== top.value ) {
			return top.value;
		}
		var bottom = form.querySelector( 'select[name="action2"]' );
		return bottom ? bottom.value : '-1';
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		var form = document.querySelector( '.wrap form' );
		if ( ! form ) {
			return;
		}

		form.addEventListener( 'submit', function ( event ) {
			if ( 'delete' !== selectedAction( form ) ) {
				return;
			}
			var message = ( window.msRecessoAdmin && window.msRecessoAdmin.confirmDelete ) || 'Delete the selected requests permanently?';
			if ( ! window.confirm( message ) ) {
				event.preventDefault();
			}
		} );
	} );
} )();
