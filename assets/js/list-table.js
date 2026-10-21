/* global wp, bogoextListTable */
( function () {
	'use strict';

	const i18n = ( window.bogoextListTable && window.bogoextListTable.i18n ) || {};

	document.addEventListener( 'click', async ( event ) => {
		const button = event.target.closest( 'button.bogoext-badge[data-post][data-locale]' );

		if ( ! button || button.disabled ) {
			return;
		}

		event.preventDefault();

		const postId = parseInt( button.dataset.post, 10 );
		const locale = button.dataset.locale;

		if ( ! postId || ! /^[a-z]{2,3}(?:_[A-Za-z0-9]+)*$/.test( locale ) ) {
			return;
		}

		button.disabled = true;
		button.classList.add( 'is-busy' );
		button.setAttribute( 'title', i18n.creating || '' );

		try {
			const response = await wp.apiFetch( {
				path: `/bogo/v1/posts/${ postId }/translations/${ locale }`,
				method: 'POST',
			} );

			const created = response && response[ locale ];

			if ( ! created || ! created.edit_link ) {
				throw new Error( 'empty response' );
			}

			const link = document.createElement( 'a' );
			link.href = created.edit_link;
			link.className = 'bogoext-badge is-draft is-new';
			link.textContent = button.textContent;
			link.title = i18n.draft || '';
			link.lang = button.lang;
			button.replaceWith( link );

			window.open( created.edit_link, '_blank', 'noopener' );
		} catch ( error ) {
			button.disabled = false;
			button.classList.remove( 'is-busy' );
			button.classList.add( 'is-error' );
			button.setAttribute( 'title', ( error && error.message ) ? `${ i18n.failed } (${ error.message })` : i18n.failed );
		}
	} );
}() );
