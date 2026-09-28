( function () {
	'use strict';
	const C = window.WPDR;
	if ( ! C ) {
		return;
	}

	const KEY = 'wpdr:' + C.reviewId;
	const store = {
		get( k, d ) {
			try {
				const v = window.localStorage.getItem( KEY + ':' + k );
				return v === null ? d : JSON.parse( v );
			} catch ( e ) {
				return d;
			}
		},
		set( k, v ) {
			try {
				window.localStorage.setItem( KEY + ':' + k, JSON.stringify( v ) );
			} catch ( e ) {}
		},
	};

	const accent = ( C.brand && C.brand.accent ) || '#3858e9';
	const host = document.createElement( 'div' );
	host.id = 'wpdr-root';
	host.style.cssText = 'all:initial;position:fixed;top:0;left:0;right:0;z-index:2147483647;';
	document.body.appendChild( host );
	const root = host.attachShadow( { mode: 'open' } );

	root.innerHTML = `
<style>
:host{all:initial}
*{box-sizing:border-box;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif}
.bar{display:flex;flex-wrap:wrap;align-items:center;gap:6px 10px;min-height:52px;padding:8px 12px;background:#16161a;color:#f4f4f6;font-size:14px;line-height:1.2}
.bar.live{background:#1f7a4d}
.bar.compare{background:#4b2d7f}
.logo{flex:none;width:26px;height:26px;border-radius:7px;background:${ accent };display:grid;place-items:center;font-weight:700;font-size:12px;color:#fff;overflow:hidden}
.logo img{width:100%;height:100%;object-fit:cover}
#actions{display:flex;flex-wrap:wrap;gap:6px 8px;flex:none}
.msg{flex:1;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.msg em{font-style:normal;opacity:.7}
button{font:inherit;font-size:13px;font-weight:600;border:0;border-radius:999px;padding:8px 14px;cursor:pointer;background:rgba(255,255,255,.14);color:#fff;white-space:nowrap}
button.primary{background:#fff;color:#111}
button.on{background:${ accent };color:#fff}
button:focus-visible,textarea:focus-visible,input:focus-visible{outline:2px solid #fff;outline-offset:2px}
.scrim{position:fixed;inset:0;z-index:-1}
.hl{position:fixed;pointer-events:none;border:2px solid ${ accent };background:${ accent }22;border-radius:4px;display:none;z-index:1}
.hint{position:fixed;top:60px;left:50%;transform:translateX(-50%);background:#16161a;color:#fff;padding:8px 14px;border-radius:999px;font-size:13px;display:none;box-shadow:0 6px 20px #0004}
.sheet{position:fixed;left:0;right:0;bottom:0;background:#fff;color:#1d1d1f;border-radius:18px 18px 0 0;box-shadow:0 -10px 40px #0004;padding:16px 16px 20px;max-width:560px;margin:0 auto;display:none}
.sheet h3{margin:0 0 4px;font-size:16px}
.sheet .ctx{font-size:12px;color:#6b6b70;margin-bottom:10px;max-height:2.6em;overflow:hidden}
textarea,input{width:100%;border:1px solid #c9c5bb;border-radius:10px;padding:10px;font-size:16px;color:#1d1d1f;background:#fff}
textarea{min-height:84px;resize:vertical}
.moods{display:flex;gap:6px;margin:10px 0;flex-wrap:wrap}
.moods button{background:#fff;color:#1d1d1f;border:1px solid #c9c5bb}
.moods button.sel{background:#1d1d1f;color:#fff;border-color:#1d1d1f}
.row{display:flex;gap:8px;margin-top:10px}
.row button.send{background:${ accent };color:#fff;flex:1;padding:12px}
.row button.cancel{background:#eee;color:#1d1d1f;padding:12px 16px}
.toast{position:fixed;left:50%;bottom:24px;transform:translateX(-50%);background:#16161a;color:#fff;padding:10px 16px;border-radius:999px;font-size:14px;display:none}
.list .item{border-top:1px solid #eee;padding:8px 0;font-size:14px}
.badge{font-size:11px;border-radius:999px;padding:1px 8px;background:#eee;color:#555;margin-left:6px}
.badge.ok{background:#dff3e8;color:#1f7a4d}
.err{color:#b3261e;font-size:13px;margin-top:6px}
@media (max-width:560px){.msg{white-space:normal;font-size:13px}#actions{flex:1 1 100%;order:3}#actions button{flex:1 1 auto}}
</style>
<div class="bar" part="bar">
  <span class="logo" id="logo"></span>
  <span class="msg" id="msg"></span>
  <span id="actions"></span>
</div>
<div class="hl" id="hl"></div>
<div class="hint" id="hint">Tap the part of the page you want to comment on</div>
<div class="sheet" id="sheet" role="dialog" aria-label="Leave a comment"></div>
<div class="toast" id="toast" role="status"></div>`;

	const $ = ( id ) => root.getElementById( id );
	const esc = ( s ) => String( s ).replace( /[&<>"]/g, ( c ) => ( { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[ c ] ) );
	const bar = root.querySelector( '.bar' );

	// Header content.
	const brandName = ( C.brand && C.brand.name ) || 'Review';
	if ( C.brand && C.brand.logo ) {
		const img = document.createElement( 'img' );
		img.alt = '';
		img.src = C.brand.logo;
		$( 'logo' ).appendChild( img );
	} else {
		$( 'logo' ).textContent = brandName.trim().charAt( 0 ).toUpperCase();
	}

	// Keep the page clear of the bar however tall it wraps.
	const offset = document.getElementById( 'wpdr-offset' );
	function fit() {
		if ( offset ) {
			offset.textContent = 'html{padding-top:' + bar.offsetHeight + 'px !important}html.wpdr-picking *{cursor:crosshair !important}';
		}
	}
	if ( window.ResizeObserver ) {
		new ResizeObserver( fit ).observe( bar );
	}

	const mode = C.mode;
	bar.classList.add( mode );
	if ( mode === 'live' ) {
		$( 'msg' ).textContent = 'This redesign is live.';
	} else if ( mode === 'compare' ) {
		$( 'msg' ).innerHTML = 'Showing the current live site <em>· Not the redesign</em>';
	} else {
		$( 'msg' ).innerHTML = 'Reviewing redesign <em>· ' + esc( C.expiry ) + ( C.unpublished ? ' · Includes unpublished copy' : '' ) + ' · ' + esc( brandName ) + '</em>';
	}

	fit();
	const actions = $( 'actions' );
	function addBtn( label, cls, fn ) {
		const b = document.createElement( 'button' );
		b.textContent = label;
		if ( cls ) {
			b.className = cls;
		}
		b.addEventListener( 'click', fn );
		actions.appendChild( b );
		return b;
	}

	// Compare to live: same scroll position, so the difference is easy to see.
	function go( url ) {
		try {
			window.sessionStorage.setItem( 'wpdr:scroll', JSON.stringify( { y: window.scrollY, url: location.pathname } ) );
		} catch ( e ) {}
		location.href = url;
	}
	try {
		const s = JSON.parse( window.sessionStorage.getItem( 'wpdr:scroll' ) || 'null' );
		if ( s && s.url === location.pathname ) {
			window.addEventListener( 'load', () => window.scrollTo( 0, s.y ) );
		}
		window.sessionStorage.removeItem( 'wpdr:scroll' );
	} catch ( e ) {}

	if ( mode === 'live' ) {
		addBtn( 'Visit the site', 'primary', () => {
			location.href = C.siteUrl;
		} );
		return;
	}
	if ( mode === 'compare' ) {
		addBtn( 'Back to redesign', 'primary', () => go( C.reviewUrl ) );
		return;
	}

	if ( C.canCompare ) {
		addBtn( 'Compare', '', () => go( C.compareUrl ) );
	}
	let approveBtn = null;
	if ( C.canApprove ) {
		approveBtn = addBtn( store.get( 'approved', false ) ? 'Approved' : 'Looks good', '', approve );
	}
	let commentBtn = null;
	if ( C.canComment ) {
		commentBtn = addBtn( 'Comment', 'primary', togglePick );
	}

	/* ---------------------------------------------------------------- */

	const hl = $( 'hl' );
	const hint = $( 'hint' );
	const sheet = $( 'sheet' );
	let picking = false;
	let target = null;

	function toast( text ) {
		const t = $( 'toast' );
		t.textContent = text;
		t.style.display = 'block';
		clearTimeout( toast.t );
		toast.t = setTimeout( () => ( t.style.display = 'none' ), 2600 );
	}

	function api( path, body ) {
		return fetch( C.rest + path, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json', 'X-WPDR-Token': C.token },
			credentials: 'same-origin',
			body: JSON.stringify( body ),
		} ).then( ( r ) => r.json().then( ( j ) => ( r.ok ? j : Promise.reject( j ) ) ) );
	}

	function askName() {
		return store.get( 'name', '' );
	}

	function approve() {
		if ( store.get( 'approved', false ) ) {
			toast( 'You already said this looks good.' );
			return;
		}
		let name = askName();
		if ( ! name ) {
			name = ( window.prompt( 'Your name, so they know who approved?' ) || '' ).trim();
			if ( ! name ) {
				return;
			}
			store.set( 'name', name );
		}
		api( '/approve', { name } )
			.then( () => {
				store.set( 'approved', true );
				approveBtn.textContent = 'Approved';
				toast( 'Thanks. They have been told it looks good to you.' );
			} )
			.catch( ( e ) => toast( ( e && e.message ) || 'Could not send that.' ) );
	}

	function blockEl( el ) {
		return ( el.closest && el.closest( '[data-dr-block]' ) ) || el;
	}

	function togglePick() {
		setPicking( ! picking );
	}
	function setPicking( on ) {
		picking = on;
		document.documentElement.classList.toggle( 'wpdr-picking', on );
		hint.style.top = bar.offsetHeight + 8 + 'px';
		hint.style.display = on ? 'block' : 'none';
		commentBtn.classList.toggle( 'on', on );
		commentBtn.textContent = on ? 'Cancel' : 'Comment';
		if ( ! on ) {
			hl.style.display = 'none';
		}
	}

	function inUi( e ) {
		return e.composedPath && e.composedPath().indexOf( host ) !== -1;
	}
	function outline( el ) {
		const r = el.getBoundingClientRect();
		Object.assign( hl.style, { display: 'block', top: r.top + 'px', left: r.left + 'px', width: r.width + 'px', height: r.height + 'px' } );
	}
	document.addEventListener( 'pointermove', ( e ) => {
		if ( ! picking || inUi( e ) ) {
			return;
		}
		const t = document.elementFromPoint( e.clientX, e.clientY );
		if ( t && t !== document.documentElement && t !== document.body ) {
			outline( blockEl( t ) );
		}
	}, true );
	document.addEventListener( 'click', ( e ) => {
		if ( ! picking || inUi( e ) ) {
			return;
		}
		e.preventDefault();
		e.stopPropagation();
		const t = blockEl( e.target );
		setPicking( false );
		outline( t );
		openSheet( t );
	}, true );
	document.addEventListener( 'keydown', ( e ) => {
		if ( e.key === 'Escape' && ( picking || sheet.style.display === 'block' ) ) {
			setPicking( false );
			closeSheet();
		}
	} );

	function viewport() {
		const w = window.innerWidth;
		return w <= 600 ? 'phone' : w <= 1024 ? 'tablet' : 'desktop';
	}

	function openSheet( el ) {
		target = {
			path: el.getAttribute && el.getAttribute( 'data-dr-block' ),
			snippet: ( el.textContent || '' ).replace( /\s+/g, ' ' ).trim().slice( 0, 140 ),
		};
		let mood = '';
		sheet.innerHTML = `
			<h3>${ target.path ? 'Comment on this part' : 'Comment on the page' }</h3>
			<div class="ctx">${ esc( target.snippet || 'Anywhere on the page' ) }</div>
			<textarea id="txt" maxlength="1000" placeholder="What do you think? One line is plenty."></textarea>
			<div class="moods">
				<button data-m="love">Love it</button><button data-m="off">Feels off</button><button data-m="unreadable">Can't read it</button>
			</div>
			<input id="nm" placeholder="Your name" value="${ esc( askName() ) }" autocomplete="name">
			<div class="err" id="err"></div>
			<div class="row"><button class="cancel" id="cancel">Cancel</button><button class="send" id="send">Send</button></div>`;
		sheet.style.display = 'block';
		const sr = sheet;
		sr.querySelectorAll( '.moods button' ).forEach( ( b ) =>
			b.addEventListener( 'click', () => {
				mood = mood === b.dataset.m ? '' : b.dataset.m;
				sr.querySelectorAll( '.moods button' ).forEach( ( x ) => x.classList.toggle( 'sel', x.dataset.m === mood ) );
			} )
		);
		sr.querySelector( '#cancel' ).addEventListener( 'click', closeSheet );
		sr.querySelector( '#send' ).addEventListener( 'click', () => {
			const text = sr.querySelector( '#txt' ).value.trim();
			const name = sr.querySelector( '#nm' ).value.trim();
			if ( ! text ) {
				sr.querySelector( '#err' ).textContent = 'Write a few words first.';
				return;
			}
			store.set( 'name', name );
			const btn = sr.querySelector( '#send' );
			btn.disabled = true;
			btn.textContent = 'Sending';
			api( '/feedback', {
				text,
				name,
				mood,
				post_id: C.postId,
				path: target.path || '',
				snippet: target.snippet,
				viewport: viewport(),
				compare: false,
			} )
				.then( ( res ) => {
					const mine = store.get( 'mine', [] );
					mine.push( { id: res.id, text, at: Date.now() } );
					store.set( 'mine', mine.slice( -50 ) );
					closeSheet();
					toast( 'Sent. Thank you.' );
					renderMine();
				} )
				.catch( ( e ) => {
					btn.disabled = false;
					btn.textContent = 'Send';
					sr.querySelector( '#err' ).textContent = ( e && e.message ) || 'Could not send that. Try again.';
				} );
		} );
		sr.querySelector( '#txt' ).focus();
	}
	function closeSheet() {
		setPicking( false );
		sheet.style.display = 'none';
		hl.style.display = 'none';
	}

	// "Your comments": shows which ones the team has addressed.
	let mineBtn = null;
	let mineOpen = false;
	function renderMine() {
		const mine = store.get( 'mine', [] );
		if ( ! mine.length ) {
			return;
		}
		if ( ! mineBtn ) {
			mineBtn = document.createElement( 'button' );
			mineBtn.addEventListener( 'click', () => {
				mineOpen = ! mineOpen;
				drawMine();
			} );
			actions.insertBefore( mineBtn, actions.firstChild );
		}
		const done = mine.filter( ( m ) => m.resolved ).length;
		mineBtn.textContent = 'Yours (' + mine.length + ')' + ( done ? ' · ' + done + ' addressed' : '' );
	}
	function drawMine() {
		if ( ! mineOpen ) {
			sheet.style.display = 'none';
			return;
		}
		const mine = store.get( 'mine', [] );
		sheet.innerHTML = '<h3>Your comments</h3><div class="list">' + mine.slice().reverse().map( ( m ) => `<div class="item">${ esc( m.text ) }<span class="badge ${ m.resolved ? 'ok' : '' }">${ m.resolved ? 'Addressed' : 'Sent' }</span></div>` ).join( '' ) + '</div><div class="row"><button class="cancel" id="close">Close</button></div>';
		sheet.style.display = 'block';
		sheet.querySelector( '#close' ).addEventListener( 'click', () => {
			mineOpen = false;
			drawMine();
		} );
	}
	( function refresh() {
		const mine = store.get( 'mine', [] );
		if ( ! mine.length ) {
			return;
		}
		renderMine();
		api( '/feedback/status', { ids: mine.map( ( m ) => m.id ) } )
			.then( ( st ) => {
				mine.forEach( ( m ) => {
					if ( st[ m.id ] ) {
						m.resolved = st[ m.id ].resolved;
					}
				} );
				store.set( 'mine', mine );
				renderMine();
			} )
			.catch( () => {} );
	} )();
} )();
