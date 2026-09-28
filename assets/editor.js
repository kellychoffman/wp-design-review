( function ( wp ) {
	const { registerPlugin } = wp.plugins;
	const { createElement: el, useState, useEffect, useCallback } = wp.element;
	const { SelectControl, TextControl, ToggleControl, Button, Notice, Spinner } = wp.components;
	const { useSelect } = wp.data;
	const apiFetch = wp.apiFetch;
	const Panel = ( wp.editor && wp.editor.PluginDocumentSettingPanel ) || ( wp.editPost && wp.editPost.PluginDocumentSettingPanel );
	const cfg = window.WPDR_EDITOR || { looks: [] };
	if ( ! Panel ) {
		return;
	}

	const PILL = { active: '#1f7a4d', live: '#3858e9', expired: '#6b6b70', ended: '#6b6b70', revoked: '#b3261e' };

	function Pill( { status } ) {
		return el( 'span', { style: { background: PILL[ status ] || '#6b6b70', color: '#fff', borderRadius: 999, padding: '1px 8px', fontSize: 11, textTransform: 'capitalize' } }, status );
	}

	function ReviewRow( { r, onRevoke } ) {
		const [ copied, setCopied ] = useState( false );
		const copy = () => {
			( navigator.clipboard ? navigator.clipboard.writeText( r.url ) : Promise.reject() )
				.catch( () => {
					const t = document.createElement( 'textarea' );
					t.value = r.url;
					document.body.appendChild( t );
					t.select();
					document.execCommand( 'copy' );
					t.remove();
				} )
				.finally( () => {
					setCopied( true );
					setTimeout( () => setCopied( false ), 1500 );
				} );
		};
		const live = r.status === 'active' || r.status === 'live';
		return el( 'div', { style: { border: '1px solid #ddd', borderRadius: 6, padding: 10, marginBottom: 8 } },
			el( 'div', { style: { display: 'flex', gap: 8, alignItems: 'center', flexWrap: 'wrap' } },
				el( Pill, { status: r.status } ),
				el( 'span', { style: { fontSize: 12, color: '#555' } }, r.expiry + ( r.look ? ' · ' + r.look : '' ) )
			),
			el( 'div', { style: { fontSize: 12, color: '#555', margin: '6px 0' } },
				r.views + ( r.views === 1 ? ' open · ' : ' opens · ' ) + r.open + ( r.open === 1 ? ' open comment' : ' open comments' ) + ( r.approvals ? ' · ' + r.approvals + ' approved' : '' ) + ( r.password ? ' · password' : '' ) ),
			live && el( 'div', { style: { display: 'flex', gap: 6 } },
				el( Button, { variant: 'secondary', size: 'small', onClick: copy }, copied ? 'Copied' : 'Copy link' ),
				r.status === 'active' && el( Button, { variant: 'tertiary', isDestructive: true, size: 'small', onClick: () => onRevoke( r.id ) }, 'Revoke' )
			)
		);
	}

	function ShareReview() {
		const { postId, status, isDirty } = useSelect( ( select ) => {
			const ed = select( 'core/editor' );
			return {
				postId: ed.getCurrentPostId(),
				status: ed.getCurrentPostAttribute( 'status' ),
				isDirty: ed.isEditedPostDirty(),
			};
		}, [] );
		const isPublished = status === 'publish';
		const [ reviews, setReviews ] = useState( null );
		const [ busy, setBusy ] = useState( false );
		const [ error, setError ] = useState( '' );
		const [ look, setLook ] = useState( '' );
		const [ days, setDays ] = useState( '7' );
		const [ can, setCan ] = useState( 'comment' );
		const [ password, setPassword ] = useState( '' );

		const load = useCallback( () => {
			apiFetch( { path: '/wp-design-review/v1/reviews?post_id=' + postId } ).then( setReviews ).catch( () => setReviews( [] ) );
		}, [ postId ] );
		useEffect( load, [ load ] );

		const create = () => {
			setBusy( true );
			setError( '' );
			apiFetch( {
				path: '/wp-design-review/v1/reviews',
				method: 'POST',
				data: { post_id: postId, look, days: parseInt( days, 10 ), can, password, unpublished: ! isPublished },
			} )
				.then( () => {
					setPassword( '' );
					load();
				} )
				.catch( ( e ) => setError( e.message || 'Something went wrong.' ) )
				.finally( () => setBusy( false ) );
		};
		const revoke = ( id ) => {
			apiFetch( { path: '/wp-design-review/v1/reviews/' + id + '/revoke', method: 'POST' } ).then( load );
		};

		const lookOptions = [ { label: 'This page as it is now', value: '' } ].concat( cfg.looks.map( ( l ) => ( { label: 'With look: ' + l, value: l } ) ) );

		return el( Panel, { name: 'wpdr-share', title: 'Share review', className: 'wpdr-share' },
			el( 'p', { style: { marginTop: 0, color: '#555' } }, 'Send a link. They see the real page, not the editor.' ),
			isDirty && el( Notice, { status: 'warning', isDismissible: false }, 'Save your changes first. The link shows what is saved.' ),
			reviews === null && el( Spinner ),
			reviews && reviews.map( ( r ) => el( ReviewRow, { key: r.id, r, onRevoke: revoke } ) ),
			el( 'hr' ),
			el( 'strong', null, 'New link' ),
			el( SelectControl, { label: 'Show', value: look, options: lookOptions, onChange: setLook, __nextHasNoMarginBottom: true } ),
			el( SelectControl, { label: 'Expires in', value: days, options: [ '1', '3', '7', '14', '30' ].map( ( d ) => ( { label: d + ( d === '1' ? ' day' : ' days' ), value: d } ) ), onChange: setDays, __nextHasNoMarginBottom: true } ),
			el( SelectControl, { label: 'Visitors can', value: can, options: [ { label: 'View only', value: 'view' }, { label: 'Comment', value: 'comment' }, { label: 'Comment and approve', value: 'approve' } ], onChange: setCan, __nextHasNoMarginBottom: true } ),
			el( TextControl, { label: 'Password (optional)', value: password, onChange: setPassword, autoComplete: 'off', __nextHasNoMarginBottom: true } ),
			isPublished
				? el( 'p', { style: { fontSize: 12, color: '#555' } }, 'Published copy only. Unsaved or draft changes to this page will not appear.' )
				: el( 'p', { style: { fontSize: 12, color: '#8a4b00' } }, 'This page is not published. The link will show its unpublished copy to anyone who has it. The review bar says so.' ),
			error && el( Notice, { status: 'error', isDismissible: false }, error ),
			el( Button, { variant: 'primary', onClick: create, isBusy: busy, disabled: busy, style: { marginTop: 8 } }, 'Share review' )
		);
	}

	registerPlugin( 'wp-design-review', { render: ShareReview } );
} )( window.wp );
