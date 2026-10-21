/* Bogo Extended – [bogo-dropdown] */
( function () {
	'use strict';

	function close( dropdown ) {
		dropdown.classList.remove( 'is-open' );

		const button = dropdown.querySelector( '.bogoext-dropdown__button' );

		if ( button ) {
			button.setAttribute( 'aria-expanded', 'false' );
		}
	}

	document.addEventListener( 'click', ( event ) => {
		const button = event.target.closest( '.bogoext-dropdown__button' );

		document.querySelectorAll( '.bogoext-dropdown.is-open' ).forEach( ( open ) => {
			if ( ! button || ! open.contains( button ) ) {
				close( open );
			}
		} );

		if ( ! button ) {
			return;
		}

		const dropdown = button.closest( '.bogoext-dropdown' );
		const isOpen = dropdown.classList.toggle( 'is-open' );
		button.setAttribute( 'aria-expanded', isOpen ? 'true' : 'false' );
	} );

	document.addEventListener( 'keydown', ( event ) => {
		if ( event.key !== 'Escape' ) {
			return;
		}

		document.querySelectorAll( '.bogoext-dropdown.is-open' ).forEach( ( dropdown ) => {
			close( dropdown );
			const button = dropdown.querySelector( '.bogoext-dropdown__button' );

			if ( button && dropdown.contains( document.activeElement ) ) {
				button.focus();
			}
		} );
	} );
}() );
