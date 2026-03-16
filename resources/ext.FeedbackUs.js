( function ( mw, $ ) {
	'use strict';

	if ( !mw.config.get( 'wgIsArticle' ) || mw.config.get( 'wgAction' ) === 'edit' ) {
		return;
	}

	var script = mw.util.wikiScript( 'index' );
	var scriptPath = mw.config.get( 'wgScriptPath' ) || '';
	var imageBase = scriptPath + '/extensions/FeedbackUs/resources/img';
	var $heading = $( '#firstHeading' );
	var $modal = $( '#fbuModal' );

	if ( !$heading.length || !$modal.length || $( '#ca-feedback' ).length ) {
		return;
	}

	$heading.append(
		'<div id="ca-feedback" data-bs-toggle="modal" data-bs-target="#fbuModal" class="noprint mb-2">' +
			'<img src="' + imageBase + '/feedback.png" id="feedbackicon" alt="Feedback">' +
		'</div>'
	);

	function hideAlerts() {
		$( '#fbSuccess, #fbError, #asSuccess, #asError' ).addClass( 'd-none' ).empty();
		$( '#fbSuccess' ).text( mw.message( 'feedbackus-thanks' ).text() );
		$( '#asSuccess' ).text( mw.message( 'articlescores-success' ).text() );
		$( '#asError' ).text( mw.message( 'articlescores-one-per-day' ).text() );
	}

	function displayRating( rating ) {
		$( '.ratingBar span' ).each( function () {
			var color = $( this ).data( 'rating' ) <= rating ? 'orange' : 'white';
			$( this ).find( 'img' ).attr( 'src', imageBase + '/star_' + color + '.png' );
		} );
	}

	$modal.on( 'hide.bs.modal', function () {
		$( '#FeedbackUsEmail' ).val( '' );
		$( '#FeedbackUsComment' ).val( '' );
		hideAlerts();
		displayRating( Number( $modal.data( 'rating' ) ) || 0 );
	} );

	$modal.find( 'form' ).on( 'submit', function ( event ) {
		event.preventDefault();
		hideAlerts();

		$.ajax( {
			type: 'POST',
			url: script + '?title=Special:FeedbackUs',
			data: {
				page_id: $modal.data( 'pageid' ),
				rev_id: $modal.data( 'revid' ),
				comment: $( '#FeedbackUsComment' ).val(),
				email: $( '#FeedbackUsEmail' ).val(),
				action: 'insertcomment'
			},
			dataType: 'text'
		} ).done( function ( response ) {
			if ( response === 'ok' ) {
				$( '#fbSuccess' ).removeClass( 'd-none' );
				setTimeout( function () {
					$modal.modal( 'hide' );
				}, 2000 );
				return;
			}

			$( '#fbError' ).removeClass( 'd-none' ).text( response );
		} ).fail( function () {
			$( '#fbError' ).removeClass( 'd-none' ).text( 'Error: request failed' );
		} );
	} );

	$( '.asStar' ).on( 'click', function () {
		hideAlerts();
		var rating = Number( $( this ).data( 'rating' ) );

		$.ajax( {
			type: 'POST',
			url: script + '?title=Special:FeedbackUs',
			data: {
				page_id: $modal.data( 'pageid' ),
				rev_id: $modal.data( 'revid' ),
				score: rating,
				action: 'insertrating'
			},
			dataType: 'text'
		} ).done( function ( response ) {
			if ( /^\d+$/.test( response ) ) {
				$modal.data( 'rating', Number( response ) );
				displayRating( Number( response ) );
				$( '#asSuccess' ).removeClass( 'd-none' );
				return;
			}

			if ( response === 'articlescores-dayips-not-today' ) {
				$( '#asError' ).removeClass( 'd-none' );
				return;
			}

			$( '#fbError' ).removeClass( 'd-none' ).text( response );
		} ).fail( function () {
			$( '#fbError' ).removeClass( 'd-none' ).text( 'Error: request failed' );
		} );
	} );

	$( '.asStar' ).on( 'mouseover', function () {
		displayRating( Number( $( this ).data( 'rating' ) ) || 0 );
	} );

	$( '.ratingBar' ).on( 'mouseout', function () {
		displayRating( Number( $modal.data( 'rating' ) ) || 0 );
	} );

	hideAlerts();
} )( mediaWiki, jQuery );
