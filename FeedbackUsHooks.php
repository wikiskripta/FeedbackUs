<?php

/**
 * All hooked functions used by FeedbackUs extension.
 * @ingroup Extensions
 * @author Josef Martiňák
 * @license MIT
 * @file
 */

class FeedbackUsHooks {

	/**
	 * Place review link into actions menu 
	 * @param $out OutputPage: instance of OutputPage
	 * @param $skin Skin: instance of Skin
	 */
	public static function activateFB( &$out, &$skin ) {
		// FeedbackUs only for articles from defined namespace (Main page exluded)
		if( !$out->isArticle() ) return true;
		$title = $out->getTitle();
		$config = $out->getConfig();
		
		$allowed = in_array($title->getNamespace(), $config->get("namespaces")) ? true:false;
		if ( !$title->isMainPage() && $allowed !== false ) {
			// show icon
			$page_id = $out->getWikiPage()->getId();
			if( !empty( $page_id ) ) {
				// register module
				$out->addModules('ext.FeedbackUs');
				$rating = FeedbackUsHooks::getRating($out,$skin);
				if( empty( $rating ) ) $rating = '00';

				// add modal (hidden)
				$modal = "<div id='fbuModal' class='modal fade bd-example-modal-md' tabindex='-1' role='dialog' aria-labelledby='feedbackModalLabel' aria-hidden='true' ";
				$modal .= "data-rating='$rating' data-revid='" . $out->getRevisionId() . "' data-pageid='" . $page_id . "'>\n";
				$modal .= "<div class='modal-dialog modal-md'>\n";		
				$modal .= "<div class='modal-content'>\n";
				// modal content
				$startitle = wfMessage( "feedbackus-$rating-startitle" )->plain();
				switch($rating) {
					case '1':
					$color = "red";
					break;
				
					case '2':
					$color = "orange";
					break;
				
					case '3':
					$color = "#4474c9";
					break;
		
					case '4':
					$color = "#558d18";
					break;
				
					case '5':
					$color = "green";
					break;

					default:
					$color = "#000000";
				}

				$modal .= wfMessage( 'articlescores-score' )->plain() . ": <span style='color:$color;'>$startitle</span>\n";
				// select ratings
				$modal .= "<select id='as_sel'>\n";
				for($i=0; $i<6; $i++ ) {
					$modal .= "<option value='$i' ";
					if(!$i) $modal .= "disabled selected";
					$modal .= ">" . wfMessage( "feedbackus-$i-startitle" ).plain() . "</option>\n";
				}
				$modal .= "</select>\n";
				// legend
				$modal .= "<div>" . wfMessage( "feedbackus-title" ).plain() . "</div>\n";
				// insert textarea and field for email
				$modal .= "<textarea id='FeedbackUsComment' placeholder='" . wfMessage( "feedbackus-message-label" ).plain() . "'></textarea>\n";
				$modal .= "<input type='text' id='FeedbackUsEmail' placeholder='" . wfMessage( 'feedbackus-email-label' ).plain() + "'>\n";
				// insert options
				$modal .= "<div>" + wfMessage( 'feedbackus-issues' ).plain() + "</div>\n";
				$modal .= "<ul id='FeedbackUsOptions'>\n";
				for($i=0; $i<3; $i++) {
					$modal .= "<li>\n<input id='fuo$i' type='checkbox' value='1'>";
					$modal .= "<label for='fuo$i'>" . wfMessage( "feedbackus-option$i" ).plain() . "</label>\n</li>\n";
				}
				$modal .= "</ul>\n";
				// insert send button (and onclick event)
				$modal .= "<button class=''>" . wfMessage( 'feedbackus-send-button' ).plain() . "</button>\n";
    			$modal .= "</div>\n</div>\n</div>\n";
				$out->prependHTML( $modal );
			}
		}
		if( preg_match( "/FeedbackUsFormMagic/", $out->mBodytext ) ) {
			// load module for magic box
			$out->addModules('ext.FeedbackUs.magic');
		}
		return true;
	}
	
	# Gets article's rating
	# @param $out OutputPage: instance of OutputPage
	# @param $skin Skin: instance of Skin
	public static function getRating(&$out,&$skin) {

		$rev_id = $out->getRevisionId();
		$rev_page = $out->getWikiPage()->getId();
		
		
		// get article's rating
		$dbr = wfGetDB(DB_SLAVE);
		$res = $dbr->selectRow(
			"articlescores_sum",
			array("stars","usersCount"),
			"page_id=$rev_page"
		);

		if(!$res){
			$score = 0;
			$rnumber = 0;
		}
		else {
			$score = $res->stars;
			$rnumber = $res->usersCount;
		}


		// is this revision rated? If not, recount the score
		$res = $dbr->selectRow(
			"page",
			array("page_latest"),
			array("page_id" => $rev_page)
		);
		$latest_revision = $res->page_latest;
		$res = $dbr->selectRow(
			"articlescores",
			array("id"),
			array("rev_id" => $latest_revision,"rev_page" => $rev_page)
		);
		if(!$res){
			//recount score and save
			return FeedbackUsHooks::saveScore($rev_page);
		}
		else {
			return $score;
		}
	}


	# Count and save new score
	# @param $rev_page: id of the page
	# Returns recent score (rounded)
	public static function saveScore($rev_page) {
		$dbr = wfGetDB(DB_SLAVE);
		$res = $dbr->select(
			"revision",
			array("rev_id"),
			array("rev_page" => $rev_page),
			"__METHOD__",
			array("ORDER BY" => "rev_id DESC")
		);
		$sc = 0;
		$rnumber = 0;
		//$w=1;
		$e = 0;
		$wSum = 0;
		foreach($res as $row){
			// has this revision been rated?
			$weight = pow(0.8,$e);
			$res2 = $dbr->selectRow(
				"articlescores",
				array("scoreSum","usersCount"),
				array("rev_id" => $row->rev_id)
			);
			if($res2){
				// revision has been rated
				if($res2->usersCount) {
					$revScore = $res2->scoreSum/$res2->usersCount;
					$wSum = $wSum + $weight;
				}
				else {
					$revScore = 0;
				}
				$sc = $sc + $revScore * $weight;
				$rnumber = $rnumber + $res2->usersCount;	// sum of ratings
			}

			if($weight>0.0001) {
				$e++;
			}
		}
		if($wSum) {
			$stars = floor(0.5+$sc/$wSum);	// number of stars (rounded rating)
			// save recent score to feedbackus_sum
			$dbrmaster = wfGetDB(DB_MASTER);
			$res = $dbrmaster->update(
				"articlescores_sum",
				array("score" => $sc/$wSum,"stars" => $stars,"usersCount" => $rnumber),
				array("page_id" => $rev_page)
			);
			$ret = $stars;
		}
		else {
			$ret = false;
		}
		return $ret;
	}
	
	
	public static function efFeedbackUs_Setup( &$parser ) {
		$parser->setFunctionHook( 'feedme', 'FeedbackUsHooks::efFeedbackUs_Render' );
		return true;
	}

	public static function efFeedbackUs_Render( &$parser, $width=300, $height=400 ) {
		$output = "<div id='FeedbackUsFormMagic' style='width:".$width."px;display:none;'>";
		$output .= "<textarea id='FeedbackUsCommentMagic' style='width:".$width."px;height:".$height."px' placeholder='";
		$output .= wfMessage( 'feedbackus-message-label-magic' )->plain() . "'></textarea>";
		$output .= "<input type='text' id='FeedbackUsEmailMagic' placeholder='";
		$output .= wfMessage( 'feedbackus-email-label' )->plain() . "'/>";
		$output .= "<button class='FeedbackUsSendButtonMagic'>" . wfMessage( 'feedbackus-send-button' )->plain() . "</button>";
		$output .= "</div>";
		return $parser->insertStripItem( $output, $parser->mStripState );
	}

	# create or upgrade new tables
	public static function FeedbackUsUpdateSchema( DatabaseUpdater $updater ) {

		if( $updater->getDB()->getType() == 'mysql' ) {
			$updater->addExtensionUpdate( array( 'addTable', 'feedbackus', __DIR__ . '/sql/feedbackus.sql', true ) );
			$updater->addExtensionUpdate( array( 'addTable', 'articlescores', __DIR__ . '/sql/articlescores.sql', true ) );
			$updater->addExtensionUpdate( array( 'addTable', 'articlescores_sum', __DIR__ . '/sql/articlescores_sum.sql', true ) );
			return true;
		}
		else {
			return false;
		}
	}

}