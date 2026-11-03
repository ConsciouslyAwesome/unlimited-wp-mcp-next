/**
 * UWNext Draft Preview — keeps navigation in preview mode by
 * appending the preview token to same-origin links and form actions.
 *
 * Skips elements marked with data-uwnext-no-preview so the banner's own
 * "View Live Site" / "End Preview" buttons can opt out of the rewrite.
 *
 * Expects uwnextPreview to be localized via wp_localize_script with:
 *   token — preview token string
 *   param — query param name (uwnext_preview)
 */
(function () {
	'use strict';

	var config = window.uwnextPreview;
	if ( ! config || ! config.token ) {
		return;
	}

	var token  = config.token;
	var param  = config.param || 'uwnext_preview';
	var origin = location.origin;

	document.body.classList.add( 'uwnext-preview-active' );

	function addTokenToUrl( href ) {
		if ( ! href || href.startsWith( '#' ) || href.startsWith( 'mailto:' ) || href.startsWith( 'tel:' ) || href.startsWith( 'javascript:' ) ) {
			return href;
		}
		try {
			var url = new URL( href, origin );
			if ( url.origin !== origin ) {
				return href;
			}
			if ( url.searchParams.has( param ) ) {
				return href;
			}
			url.searchParams.set( param, token );
			return url.toString();
		} catch ( e ) {
			return href;
		}
	}

	function isExempt( el ) {
		return el && el.hasAttribute && el.hasAttribute( 'data-uwnext-no-preview' );
	}

	document.querySelectorAll( 'a[href]:not([data-uwnext-no-preview])' ).forEach( function ( a ) {
		a.href = addTokenToUrl( a.getAttribute( 'href' ) );
	} );

	document.querySelectorAll( 'form[action]:not([data-uwnext-no-preview])' ).forEach( function ( f ) {
		f.action = addTokenToUrl( f.getAttribute( 'action' ) );
	} );

	var observer = new MutationObserver( function ( mutations ) {
		mutations.forEach( function ( m ) {
			m.addedNodes.forEach( function ( node ) {
				if ( node.nodeType !== 1 ) {
					return;
				}
				if ( node.tagName === 'A' && node.href && ! isExempt( node ) ) {
					node.href = addTokenToUrl( node.getAttribute( 'href' ) );
				}
				var links = node.querySelectorAll ? node.querySelectorAll( 'a[href]:not([data-uwnext-no-preview])' ) : [];
				links.forEach( function ( a ) {
					a.href = addTokenToUrl( a.getAttribute( 'href' ) );
				} );
			} );
		} );
	} );
	observer.observe( document.body, { childList: true, subtree: true } );
} )();
