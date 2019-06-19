/**
 * Add onclick for fbuModal
 * Send feedback
 */

( function ( mw, $ ) {

	if( mw.config.get("wgIsArticle") == false || window.location.href.indexOf("veaction=edit") !== -1  ) return true;

	var wikipath = window.location.origin;

	// Add feedback icon 
	$("#firstHeading").append('<div id="ca-feedback" data-toggle="modal" data-target="#fbuModal" class="noprint mb-2"><img src="/extensions/FeedbackUs/resources/img/feedback.png" id="feedbackicon" alt="Feedback"></div>');

	$('#fbuModal').on('hide.bs.modal', function (e) { // or hide
		// clear form
		$('#FeedbackUsEmail').val('');
		$('#FeedbackUsComment').val('');
		$('#fbSuccess').removeClass("d-none").addClass("d-none");
		$('#fbError').removeClass("d-none").addClass("d-none");
		$('#asSuccess').removeClass("d-none").addClass("d-none");
		$('#asError').removeClass("d-none").addClass("d-none");
	})


	// Unlock submit button of #fbuModal form when comment filled
	$('#FeedbackUsComment').change(function( event ) {
		if( $(this).val() != '' && $("#modalSendButton").hasClass("disabled")) {
			$("#modalSendButton").removeClass("disabled");
		}
		else if( $(this).val() == '' && !$("#modalSendButton").hasClass("disabled")) {
			$("#modalSendButton").addClass("disabled");
		}
	})

	// Send form
	$('#fbuModal form').submit(function( event ) {
		event.preventDefault();

		var page_id = $( '#fbuModal' ).data('pageid');
		var rev_id = $( '#fbuModal' ).data('revid');
				
		// send feedback
		$.ajax({
			type: 'POST',
			url: wikipath + '/index.php?title=Special:FeedbackUs',
			data: 'page_id=' + page_id + '&comment=' + $( '#FeedbackUsComment' ).val() + '&email=' + $( '#FeedbackUsEmail' ).val() + 
				'&rev_id=' + rev_id + '&action=insertcomment',
			dataType: 'text',
			success: function( server_response ) {
				if(server_response == 'ok') {
					$('#fbSuccess').removeClass("d-none");
					// hide modal
					setTimeout(function() {	
						$('#fbuModal').modal('hide');
					}, 2000 );
				}
				else {
					$('#fbError').removeClass("d-none");
					$('#fbError').html( $('#fbError').html() + ': ' + server_response );
				}
			}
		});
	});


	// Handle review bar
	$( ".asStar" ).click(function() {
		var rating = $(this).data("rating");
		$.ajax({
			type: 'POST',
			url: wikipath + '/index.php?title=Special:FeedbackUs',
			data: 'page_id=' + $(this).data("pageid") + '&rev_id=' + $(this).data("revid") + '@score=' + rating + '&action=insertrating',
			dataType: 'text',
			success: function( server_response ) {
				if(server_response == 'ok') {
					displayRating(rating);
					$('#asSuccess').removeClass("d-none");
					// hide alert
					setTimeout(function() {	
						$('#asSuccess').addClass("d-none");
					}, 2000 );
				}
				else if(server_response == 'articlescores-dayips-not-today') {
					$('#asError').removeClass("d-none");
					// hide alert
					setTimeout(function() {	
						$('#asError').addClass("d-none");
					}, 2000 );
				}
				else {
					$('#fbError').removeClass("d-none");
					$('#fbError').html( $('#fbError').html() + ': ' + server_response );
					// hide alert
					setTimeout(function() {	
						$('#fbError').addClass("d-none");
					}, 2000 );
				}
			}
		});
	});
	$( ".asStar" ).mouseover(function() {
		var rating = $(this).data("rating");
		displayRating(rating);
	});
	$( ".ratingBar" ).mouseout(function() {
		var rating = $( '#fbuModal' ).data('rating');
		displayRating(rating);
	});

	// Add filter to link
	$( ".pagerItem" ).click(function(e) {
		e.preventDefault();
		if( $("#solvedSwitch").is(":checked") ) {
			var newhref = $(this).href().replace("archive", "") + "archive";
			window.location.href = newhref;
			exit;
		}
	});

	// solvedSwitch on change
	$('#solvedSwith').change(function() {
        if($(this).is(":checked")) {
			window.location.href = window.location.origin + "/w/Special:FeedbackUs/1-archive";
        }
		else window.location.href = window.location.origin + "/w/Special:FeedbackUs/1";
    });


	/**
	 * Display correct colors of stars' rating
	 * @param {int} rating: chosen rating
	 */
	function displayRating(rating) {
		$(".ratingBar span").each(function() {
			var color = "white";
			if( $(this).data("rating") <= rating ) color = "orange";
			$(this).find("img").attr("src", window.location.origin + "/extensions/FeedbackUs/resources/img/star_" + color + ".png");
		});
	}

}( mediaWiki, jQuery ) );