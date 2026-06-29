/**
 * GIF to WebM — front-end fallback.
 *
 * Replaces the 1.0 footer script, which looked for element IDs the shortcode
 * never output and therefore did nothing. This version finds every WebM video
 * the shortcode renders and falls back to its sibling GIF if the WebM cannot
 * play (unsupported codec, network error, etc.).
 */
( function () {
	'use strict';

	function wire( video ) {
		var wrap = video.closest ? video.closest( '.bannerVideo' ) : video.parentNode;
		if ( ! wrap ) {
			return;
		}
		var img = wrap.querySelector( 'img.bannerGif' );
		if ( ! img ) {
			return; // No GIF fallback available — leave the video as-is.
		}

		var settled = false;

		function showImage() {
			if ( settled ) {
				return;
			}
			settled = true;
			video.style.display = 'none';
			img.style.display = '';
		}

		function showVideo() {
			settled = true;
			img.style.display = 'none';
			video.style.display = '';
		}

		// A <source> that fails to load fires a non-bubbling "error" on the
		// <source> element; a capturing listener on the <video> still receives
		// it. The <video> itself also fires "error" when no source is playable.
		video.addEventListener( 'error', showImage, true );
		video.addEventListener( 'loadeddata', showVideo );

		// Safety net for browsers that stay silent on an unsupported source.
		window.setTimeout( function () {
			// readyState < 2 = HAVE_CURRENT_DATA not reached.
			// networkState 3 = NETWORK_NO_SOURCE.
			if ( video.readyState < 2 || video.networkState === 3 ) {
				showImage();
			}
		}, 2500 );
	}

	function init() {
		var videos = document.querySelectorAll( '.bannerVideo video.bannerGif' );
		for ( var i = 0; i < videos.length; i++ ) {
			wire( videos[ i ] );
		}
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
