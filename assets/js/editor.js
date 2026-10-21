/* global wp, bogoextEditor */
( function ( wp ) {
	'use strict';

	if ( ! wp || ! wp.plugins || ! wp.element ) {
		return;
	}

	const el = wp.element.createElement;
	const { Fragment, useState, useEffect, useCallback, useRef } = wp.element;
	const { Button, Spinner, Notice, PanelBody } = wp.components;
	const { useSelect } = wp.data;
	const apiFetch = wp.apiFetch;

	const editorApi = wp.editor || {};
	const legacyApi = wp.editPost || {};
	const PluginSidebar = editorApi.PluginSidebar || legacyApi.PluginSidebar;
	const PluginSidebarMoreMenuItem = editorApi.PluginSidebarMoreMenuItem || legacyApi.PluginSidebarMoreMenuItem;

	if ( ! PluginSidebar ) {
		return;
	}

	const i18n = ( window.bogoextEditor && window.bogoextEditor.i18n ) || {};
	const SIDEBAR = 'bogoext-translations';

	function Badge( { item } ) {
		let state = 'missing';

		if ( item.translation ) {
			state = item.translation.status === 'publish' ? 'publish' : 'draft';
		}

		return el( 'span', {
			className: `bogoext-badge is-${ state }${ item.is_current ? ' is-current' : '' }`,
			lang: item.locale.replace( /_/g, '-' ),
		}, item.code );
	}

	function Row( { item, onCreate, busy } ) {
		const t = item.translation;
		let detail;
		let actions = [];

		if ( item.is_current ) {
			detail = i18n.thisPost;
		} else if ( t ) {
			detail = `${ t.status_label } · ${ t.title || '—' }`;

			if ( t.edit_link ) {
				actions.push( el( Button, { key: 'edit', variant: 'secondary', size: 'small', href: t.edit_link }, i18n.edit ) );
			}

			if ( t.view_link ) {
				actions.push( el( Button, {
					key: 'view',
					variant: 'tertiary',
					size: 'small',
					href: t.view_link,
					target: '_blank',
					rel: 'noopener',
				}, t.status === 'publish' ? i18n.view : i18n.preview ) );
			}
		} else {
			detail = i18n.missing;

			if ( item.can_create ) {
				actions.push( el( Button, {
					key: 'create',
					variant: 'primary',
					size: 'small',
					isBusy: busy,
					disabled: busy,
					onClick: () => onCreate( item.locale ),
					description: i18n.createHint,
				}, busy ? i18n.creating : i18n.create ) );
			}
		}

		return el( 'li', { className: 'bogoext-editor-row' },
			el( Badge, { item } ),
			el( 'div', { className: 'bogoext-editor-row__text' },
				el( 'strong', null, item.name ),
				el( 'span', null, detail )
			),
			el( 'div', { className: 'bogoext-editor-row__actions' }, actions )
		);
	}

	function TranslationsPanel() {
		const { postId, status, isSaving } = useSelect( ( select ) => {
			const editor = select( 'core/editor' );
			return {
				postId: editor.getCurrentPostId(),
				status: editor.getEditedPostAttribute( 'status' ),
				isSaving: editor.isSavingPost() && ! editor.isAutosavingPost(),
			};
		}, [] );

		const [ data, setData ] = useState( null );
		const [ error, setError ] = useState( '' );
		const [ busy, setBusy ] = useState( '' );
		const wasSaving = useRef( false );

		const load = useCallback( () => {
			if ( ! postId ) {
				return;
			}

			apiFetch( { path: `/bogoext/v1/posts/${ postId }/translations` } )
				.then( ( response ) => {
					setData( response );
					setError( '' );
				} )
				.catch( ( err ) => setError( ( err && err.message ) || 'Error' ) );
		}, [ postId ] );

		useEffect( load, [ load ] );

		// Refresh after the post has been saved (e.g. first save of a new post).
		useEffect( () => {
			if ( wasSaving.current && ! isSaving ) {
				load();
			}
			wasSaving.current = isSaving;
		}, [ isSaving, load ] );

		const create = ( locale ) => {
			setBusy( locale );
			setError( '' );

			apiFetch( {
				path: `/bogo/v1/posts/${ postId }/translations/${ locale }`,
				method: 'POST',
			} )
				.then( ( response ) => {
					const created = response && response[ locale ];

					if ( created && created.edit_link ) {
						window.open( created.edit_link, '_blank', 'noopener' );
					}

					load();
				} )
				.catch( ( err ) => setError( `${ i18n.failed } ${ ( err && err.message ) || '' }` ) )
				.finally( () => setBusy( '' ) );
		};

		if ( status === 'auto-draft' ) {
			return el( PanelBody, null, el( 'p', null, i18n.saveFirst ) );
		}

		return el( PanelBody, null,
			error && el( Notice, { status: 'error', isDismissible: false }, error ),
			! data && ! error && el( 'p', null, el( Spinner ), ' ', i18n.loading ),
			data && el( 'ul', { className: 'bogoext-editor-list' },
				data.items.map( ( item ) => el( Row, {
					key: item.locale,
					item,
					busy: busy === item.locale,
					onCreate: create,
				} ) )
			)
		);
	}

	wp.plugins.registerPlugin( SIDEBAR, {
		icon: 'translation',
		render: () => el( Fragment, null,
			PluginSidebarMoreMenuItem && el( PluginSidebarMoreMenuItem, { target: SIDEBAR, icon: 'translation' }, i18n.title ),
			el( PluginSidebar, { name: SIDEBAR, title: i18n.title, icon: 'translation' },
				el( TranslationsPanel )
			)
		),
	} );
}( window.wp ) );
