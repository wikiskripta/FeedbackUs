<?php

/**
 * All hooked functions used by FeedbackUs extension.
 * @ingroup Extensions
 * @author Josef Martiňák
 */

class FeedbackUsHooks {

	/**
	 * Place review link into actions menu 
	 * @param $out OutputPage: instance of OutputPage
	 * @param $skin Skin: instance of Skin
	 */
	public static function activateFB( &$out, &$skin ) {
		// FeedbackUs only for articles from defined namespace (Main page exluded)
		global $wgServer;
		if( !$out->isArticle() ) return true;
		$title = $out->getTitle();
		$config = $out->getConfig();
		
		$allowed = in_array($title->getNamespace(), $config->get("namespaces")) ? true:false;
		if ( !$title->isMainPage() && $allowed !== false && $skin->getSkinName() == 'medik' ) {
			// show icon
			$page_id = $out->getWikiPage()->getId();
			if( !empty( $page_id ) ) {
				// register module
				$out->addModules('ext.FeedbackUs');
				$rating = FeedbackUsHooks::getRating($out,$skin);
				if( empty( $rating ) ) $rating = 0;

				// add modal (hidden)
				$modal = "<div id='fbuModal' class='modal fade' tabindex='-1' role='dialog' aria-hidden='true' ";
				$modal .= "data-rating='$rating' data-revid='" . $out->getRevisionId() . "' data-pageid='" . $page_id . "'>\n";
				$modal .= "<div class='modal-dialog modal-md'>\n";
				$modal .= "<div class='modal-content'>\n";
				// modal content

				// header - show ratings
				$modal .= "<div class='modal-header'>\n";
				$modal .= "<div class='ratingBar'>\n";
				for($i=1;$i<=5;$i++) {
					$modal .= "<span data-rating='$i' class='asStar'><img src='$wgServer/extensions/FeedbackUs/resources/img/star_";
					if($i<=$rating) $modal .= "orange"; else $modal .= "white";
					$modal .= ".png' alt='score-$i'></span>\n";
				}
				$modal .= "</div>\n";
				$modal .= "<button type='button' class='close' data-bs-dismiss='modal' aria-label='Close'>\n";
				$modal .= "<span aria-hidden='true'>&times;</span>\n";
				$modal .= "</button>\n";
				$modal .= "</div>\n"; // end of header
				
				// body
				$modal .= "<div class='modal-body'>\n<form>\n";

				// insert textarea and field for email
				$modal .= "<div class='form-group'>\n";
				$modal .= "<textarea id='FeedbackUsComment' class='form-control' placeholder='" . wfMessage( "feedbackus-message-label" )->text() . "' required></textarea>\n";
				$modal .= "</div>\n";
				$modal .= "<div class='form-group'>\n";
				$modal .= "<input type='email' id='FeedbackUsEmail' class='form-control' placeholder='" . wfMessage( 'feedbackus-email-label' )->text() . "'>\n";
				$modal .= "</div>\n";
				
				// insert send and cancel buttons
				$modal .= "<button id='modalSubmitButton' class='btn btn-primary mt-3'>" . wfMessage( 'feedbackus-send-button' )->text() . "</button>\n";

				// alerts
				$modal .= "<div id='fbSuccess' class='alert alert-success d-none mt-3' role='alert'>\n";
				$modal .= wfMessage( 'feedbackus-thanks' )->text() . "\n";
				$modal .= "</div>\n";
				$modal .= "<div id='fbError' class='alert alert-danger d-none mt-3' role='alert'></div>\n";
				$modal .= "<div id='asSuccess' class='alert alert-success d-none mt-3' role='alert'>\n";
				$modal .= wfMessage( 'articlescores-success' )->text() . "\n";
				$modal .= "</div>\n";
				$modal .= "<div id='asError' class='alert alert-danger d-none mt-3' role='alert'>\n";
				$modal .= wfMessage( 'articlescores-one-per-day' )->text() . "\n";
				$modal .= "</div>\n";
				$modal .= "</form>\n</div>\n"; // end of body
				$modal .= "</div>\n</div>\n</div>\n";
				$out->prependHTML( $modal );
			}
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
		//$dbr = wfGetDB(DB_REPLICA);
		$conn = \MediaWiki\MediaWikiServices::getInstance()->getDBLoadBalancer();
		$dbr = $conn->getConnectionRef(DB_REPLICA);

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
		$conn = \MediaWiki\MediaWikiServices::getInstance()->getDBLoadBalancer();
		$dbr = $conn->getConnectionRef(DB_REPLICA);
		//$dbr = wfGetDB(DB_REPLICA);
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
			$conn = \MediaWiki\MediaWikiServices::getInstance()->getDBLoadBalancer();
			$dbw = $conn->getConnectionRef(DB_PRIMARY);
			//$dbw = wfGetDB(DB_PRIMARY);
			$res = $dbw->update(
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