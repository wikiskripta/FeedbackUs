/**
 * Add onclick for FeedbackUsLink
 * Send feedback
 * @ingroup Extensions
 * @author Josef Martiňák
 * @license MIT
 * @version 1.0
 * @file
 */


( function ( mw, $ ) {

	if( mw.config.get("wgIsArticle") == false || window.location.href.indexOf("veaction=edit") !== -1  ) return true;

	var content = $( '#FeedbackUsLink' ).text();
	var tmp = $( '#FeedbackUsLink' ).attr( 'alt' ).split("@");
	var wikipath = window.location.origin;

	//$( '#FeedbackUsLink' ).remove();
	
	if( mw.config.get("skin") == "wisky" ) {
		// Add link to Wisky skin
		$("<li id='ca-feedback'><a>" + content + "</a></li>").insertAfter("#ca-talk");
	}
	else {
		// Add link to other skins
		var fbusIcon = wikipath + "/extensions/FeedbackUs/feedbackIcon30.png";
		$("#firstHeading").append("<div id='ca-feedback' style='position: absolute;top:25px;right:300px;'><a><img src='" + fbusIcon + "' title='"+content+"' alt=''/></a></div>");
	}
	/*
	$("<a><img src='" + wikipath + "/extensions/FeedbackUs/star_orange.png' title='"+content+"' alt='"+content+"'/></a>").insertBefore("#map_edit_img");
	$("#firstHeading").append("<a><img src='" + wikipath + "/extensions/FeedbackUs/star_orange.png' title='"+content+"' alt='"+content+"'/></a>");
	$("#contentSub").append("<a><img src='" + wikipath + "/extensions/FeedbackUs/star_orange.png' title='"+content+"' alt='"+content+"'/></a>");
	*/

	
	// Bind click handler
	$("#ca-feedback > a").click( function ( e ) {
		e.preventDefault();
		
		var page_id = $( '#FeedbackUsLink' ).attr( 'class' ).substr( 3 );
		var content = $( '#FeedbackUsLink' ).text();
		var tmp = $( '#FeedbackUsLink' ).attr( 'alt' ).split("@");
		var rating = tmp[0];
		var rev_id = tmp[1];
		
		if( $( '#FeedbackUsForm').length <= 0 ) {
			var position = $( this ).position();
			// create box
			if( mw.config.get("skin") == "wisky" ) {
				$( '#bodyContent' ).after( "<div id='FeedbackUsForm'></div>" );
				$( '#FeedbackUsForm' ).css( 'left', 2 );
				$( '#FeedbackUsForm' ).css( 'top', 80 );
				$( '#FeedbackUsForm' ).css( 'height', 400 );
				$( '#FeedbackUsForm' ).css( 'font-size', '0.8rem' );
				$( '#FeedbackUsCancelButton' ).css( 'font-size', '0.8rem' );
			}
			else {
				$( this ).after( "<div id='FeedbackUsForm'></div>" );
				/*
				$( '#FeedbackUsForm' ).css( 'left', position.left - 305  );
				$( '#FeedbackUsForm' ).css( 'top', position.bottom - 3 );
				*/
			}

			// insert cancel button (and onclick event)
			$( '#FeedbackUsForm' ).append( "<div id='FeedbackUsCancelButton'>" + mw.message( 'feedbackus-cancel-button' ).plain() + "</div>" );

			// insert ratings
			var startitle;
			var color;
			if( rating == 0 || rating == '' ) {
				startitle = mw.message( 'feedbackus-00-startitle' ).plain();
				color = "#ffffff";
			}
			else {
				startitle = mw.message( 'feedbackus-' + rating + '-startitle' ).plain();
			}
			
			switch(rating) {
				case '1':
				color = "red";
				break;
				
				case '2':
				color = "orange";
				break;
				
				case '3':
				color = "#a0994a";
				break;
		
				case '4':
				color = "#558d18";
				break;
				
				case '5':
				color = "green";
				break;
			}
			
			/*
			if( rating > 0 ) {
				for( var i=0; i<rating; i++ ) {
					$( '#FeedbackUsForm' ).append( "<img id='as_star' src='" + wikipath + "/extensions/FeedbackUs/star_white.png' alt='" + startitle + "'/>" );
				}
				$( '#FeedbackUsForm' ).append(" ");
			}
			*/
			
			//$( '#FeedbackUsForm' ).append( "<img src='https://img.shields.io/badge/" + mw.message( 'articlescores-score' ).plain() + "-" + startitle + "-" + color + ".svg?style=flat-square' alt='" + mw.message( 'articlescores-score' ).plain() + "'/>" );
			//var selectbox = "<br><br><select id='as_sel'>";
			
			var selectbox = mw.message( 'articlescores-score' ).plain() + ":<div style='padding:3px;margin-left:8px;border-radius:2px;display:inline;background-color:" + color + ";'>" + startitle + "</div><select id='as_sel'>";
			for( var i=0; i<6; i++ ) {
				selectbox += "<option value='" + i + "' ";
				if(!i) selectbox += "disabled selected";
				selectbox += ">" + mw.message( 'feedbackus-' + i + '-startitle' ).plain() + "</option>";	
			}
			selectbox += "</select>";
			$( '#FeedbackUsForm' ).append( selectbox );
			//$( '#as_sel' ).selectmenu();
			
			// insert legend
			$( '#FeedbackUsForm' ).append( "<p id='FeedbackUsFrameLegend'>" + mw.message( 'feedbackus-title' ).plain() );
			// insert textarea and field for email
			$( '#FeedbackUsForm' ).append( "<textarea id='FeedbackUsComment' placeholder='"
									+ mw.message( 'feedbackus-message-label' ).plain() + "' style='font-size:1rem !important;'></textarea>" );
			$( '#FeedbackUsForm' ).append( "<input type='text' id='FeedbackUsEmail' placeholder='"
									+ mw.message( 'feedbackus-email-label' ).plain() + "'  style='font-size:1rem !important;'/>" );
			// insert options
			var fuOptions = '';
			for( var i=0; i<3; i++ ) {
				fuOptions += "<li><input class='fuo' id='fuo" + i + "' type='checkbox' value='1'/>";
				fuOptions += "<label for='fuo" + i + "'>" + mw.message( 'feedbackus-option' + i ).plain() + "</label></li>";
			}
			var optionTitle = "<span id='FeedbackUsOptionsTitle'>" + mw.message( 'feedbackus-issues' ).plain() + "</span>";
			$( '#FeedbackUsForm' ).append( optionTitle + "<ul id='FeedbackUsOptions' style='margin-top:5px;'>" + fuOptions + "</ul>" );
			
			// insert send button (and onclick event)
			/*
			var saveButton = new OO.ui.ButtonWidget( {
			  label: mw.message( 'feedbackus-send-button' ).plain(),
			  flags:'constructive',
			  classes: ['FeedbackUsSendButton']
			} );    
			$( '#FeedbackUsForm' ).append( saveButton.$element );
			*/
			$( '#FeedbackUsForm' ).append( "<button class='FeedbackUsSendButton'>" + mw.message( 'feedbackus-send-button' ).plain() + "</button>" );
						
			// cancel
			$( '#FeedbackUsCancelButton' ).click(function() {
				$( '#FeedbackUsForm' ).animate({
					opacity: '0'
				}, 300, function() {
					// Animation complete.
					$( '#FeedbackUsCancelButton' ).off('click');
					$( '.FeedbackUsSendButton' ).off('click');
					$( '#FeedbackUsForm' ).remove();
				});
			});
			// send

				//$( '#FeedbackUsSendButton' ).button().click( function( event ) {
				$( '.FeedbackUsSendButton' ).click( function( event ) {
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
						if( server_response == 'ok' || $.isNumeric(server_response) || server_response == 'articlescores-dayips-not-today' ) {
							// comment saved
							$( '.FeedbackUsSendButton' ).off('click');
							$( '#FeedbackUsCancelButton' ).off('click');
							$( '#FeedbackUsForm' ).html("");
							$( '#FeedbackUsForm' ).css( 'height', '50' );
							$( '#FeedbackUsForm' ).append( '<h3>' + mw.message( 'feedbackus-thanks' ).plain() + '</h3>');
							/*
							$( '#FeedbackUsComment' ).remove();
							$( '#FeedbackUsFrameLegend' ).remove();
							$( '#FeedbackUsCancelButton' ).remove();
							$( '.FeedbackUsSendButton' ).remove();
							$( '#FeedbackUsEmail' ).remove();
							$( '#FeedbackUsOptionsTitle' ).remove();
							$( '#FeedbackUsOptions' ).remove();
							*/
							if( $.isNumeric(server_response) ) {
								// došlo k hodnocení, aktualizuj hvězdičky
								$( '#FeedbackUsLink' ).attr("alt", server_response + "@" + rev_id + "@" + wikipath);
							}
							
							if( server_response == 'articlescores-dayips-not-today' ) {
								$( '#FeedbackUsForm' ).css( 'height', '120' );
								$( '#FeedbackUsForm' ).append( mw.message( 'articlescores-one-per-day' ).plain() );
							}
							
							setTimeout(function() {
								$( '#FeedbackUsForm' ).animate({
									opacity: '0'
								}, 500, function() {
									// Animation complete
									$( '#FeedbackUsForm' ).remove();
								});
							}, 2000 );
						}
						else if( server_response == 'articlescores-error-insert' || server_response == 'articlescores-error-update' ) {
							alert(server_response);
						}
						else{
							// other error
							alert(server_response);
						}
					}
				});
			});
			// display box
			$('#FeedbackUsForm').animate( {'opacity': '1'}, 500 );
		}
	});

}( mediaWiki, jQuery ) );