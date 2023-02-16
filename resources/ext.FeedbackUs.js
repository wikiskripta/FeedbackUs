/**
 * Javascript for extension
 * @ingroup Extensions
 * @author Josef Martiňák
 */

( function ( mw, $ ) {

	if( mw.config.get("wgIsArticle") == false || window.location.href.indexOf("veaction=edit") !== -1  ) return true;

	var wikipath = window.location.origin;

	// Add feedback icon 
	$("#firstHeading").append('<div id="ca-feedback" data-toggle="modal" data-target="#fbuModal" class="noprint mb-2"><img src="/extensions/FeedbackUs/resources/img/feedback.png" id="feedbackicon" alt="Feedback"></div>');

	$('#fbuModal').on('hide.bs.modal', function (e) {
		// clear form
		$('#FeedbackUsEmail').val('');
		$('#FeedbackUsComment').val('');
		$('#fbSuccess').removeClass("d-none").addClass("d-none");
		$('#fbError').removeClass("d-none").addClass("d-none");
		$('#asSuccess').removeClass("d-none").addClass("d-none");
		$('#asError').removeClass("d-none").addClass("d-none");
	})

	// Send modal form
	$('#fbuModal form').submit(function( event ) {
		event.preventDefault();

		var page_id = $( '#fbuModal' ).data('pageid');
		var rev_id = $( '#fbuModal' ).data('revid');
				
		// send feedback
		var data = {'page_id': page_id, 'comment': $( '#FeedbackUsComment' ).val(), 'email': $( '#FeedbackUsEmail' ).val(), 'rev_id': rev_id, 'action': 'insertcomment'};
		$.ajax({
			type: 'POST',
			url: wikipath + '/index.php?title=Special:FeedbackUs',
			data: data,
			dataType: 'text',
			contentType: 'application/x-www-form-urlencoded; charset=UTF-8',
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
					$('#fbError').html( server_response );
					// hide alert
					setTimeout(function() {	
						$('#fbError').addClass("d-none");
					}, 2000 );
				}
			}
		});
	});


	// Handle review bar
	$( ".asStar" ).click(function() {
		var rating = $(this).data("rating");
		var data = {"page_id": $("#fbuModal").data("pageid"), "rev_id": $("#fbuModal").data("revid"), "score": rating, "action": "insertrating"};
		$.ajax({
			type: 'POST',
			url: wikipath + '/index.php?title=Special:FeedbackUs',
			data: data,
			dataType: 'text',
			contentType: 'application/x-www-form-urlencoded; charset=UTF-8',
			success: function( server_response ) {
				if( server_response.match(/^[0-9]+$/) != null ) {
					displayRating(server_response);
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
					$('#fbError').html( server_response );
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

}( mediaWiki, jQuery ) );


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
