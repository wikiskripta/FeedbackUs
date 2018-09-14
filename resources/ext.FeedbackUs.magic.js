/**
 * Add onclick for Magicbox
 * Send feedback
 */

( function ( mw, $ ) {
	// send message from magic box
	$("#FeedbackUsFormMagic").show();
	
	$( '.FeedbackUsSendButtonMagic' ).click( function( event ) {
	    if( $( '#FeedbackUsCommentMagic' ).val().length > 0 ) {
		// send feedback
		var fudivider = '';
		if( window.location.href.indexOf("/index.php/") != -1 ) {
			fudivider = "/index.php/";
		}
		else if( window.location.href.indexOf("/wiki/") != -1 ) {
			fudivider = "/wiki/";
		}
		else {
			fudivider = "/w/";
		}

		var tmp = window.location.href.split( fudivider );
		var wikipath = tmp[0] + fudivider;
		$.ajax({
			type: 'POST',
			url: wikipath + 'Special:FeedbackUs',
			data: 'comment=' + $( '#FeedbackUsCommentMagic' ).val() + '&email=' + $( '#FeedbackUsEmailMagic' ).val() + '&write=1',
			dataType: 'text',
			success: function( server_response ) {
				if( server_response == 'ok' ) {
					// comment saved
					alert( mw.message( 'feedbackus-thanks' ).plain() );
					$( '#FeedbackUsCommentMagic' ).val('');
					$( '#FeedbackUsEmailMagic' ).val('');
				}
				else{
					// other error
					alert(server_response);
				}
			}
		});
	    }
	});
}( mediaWiki, jQuery ) );