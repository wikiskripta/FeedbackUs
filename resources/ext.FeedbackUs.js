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
		$('#fbuModal').find(".form-check-input").prop("checked", false);
		$('#as_sel option:eq(0)').prop('selected', true);
		$('#fbuModal').find(".alert-success").addClass("d-none");
		$('#fbuModal').find(".alert-danger").addClass("d-none");
	})

	// send form
	$('#fbuModal form').submit(function( event ) {
		event.preventDefault();

		var page_id = $( '#fbuModal' ).data('pageid');
		var rev_id = $( '#fbuModal' ).data('revid');
				
		// send feedback
		var fuo = '';
		$( '.fuo' ).each(function () {
			if( this.checked ) fuo += this.id.substr(3) + "|";
		});
		if( fuo != '' ) fuo = fuo.substring(0, fuo.length - 1);

		$.ajax({
			type: 'POST',
			url: wikipath + '/index.php?title=Special:FeedbackUs',
			data: 'page_id=' + page_id + '&comment=' + $( '#FeedbackUsComment' ).val() + '&email=' + $( '#FeedbackUsEmail' ).val() + 
				'&options=' + fuo + '&write=1&score=' + $("#as_sel").val() + '&rev_id=' + rev_id,
			dataType: 'text',
			success: function( server_response ) {
				if( $.isNumeric(server_response) ) {
					// update rating
					$('#fbuModal').find(".alert-success").removeClass("d-none");
					$('#fbuModal .modal-title>span').text(mw.msg('feedbackus-' + server_response + '-startitle'));
					var color;
					switch(server_response) {
						case '1':
						color = "red";
						break;
					
						case '2':
						color = "orange";
						break;
					
						case '3':
						color = "#4474c9";
						break;
			
						case '4':
						color = "#558d18";
						break;
					
						case '5':
						color = "green";
						break;
	
						default:
						color = "#000000";
					}
					$('#fbuModal .modal-header').css("background-color", color);
					$( '#fbuModal' ).data('rating', server_response);
				}
				else if( server_response == 'articlescores-dayips-not-today' ) {
					$('#fbuModal').find(".alert-danger").removeClass("d-none");
				}
				else if(server_response == 'not-rated') {
					$('#fbuModal').find(".alert-success").removeClass("d-none");
				}
				else alert("Neznámá chyba: " + server_response);

				// hide modal
				setTimeout(function() {	
					$('#fbuModal').modal('hide'); 
				}, 2000 );
			}
		});		
	});

}( mediaWiki, jQuery ) );