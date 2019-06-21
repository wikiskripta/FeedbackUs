<?php

/**
 * SpecialPage for FeedbackUs extenion
 * Called with REQUEST parameters page_id and comment,
 * adds feedback to database
 * Otherwise displays the list of commented articles
 * @ingroup Extensions
 * @author Josef Martiňák
 * @license MIT
 * @file
 */
 
 
class ArticleScores extends SpecialPage {
	function __construct() {
		parent::__construct( 'ArticleScores' );
	}

	function execute($param) {

		$this->setHeaders();
		$out = $this->getOutput();
		$config = $this->getConfig();
		$dbr = wfGetDB( DB_SLAVE );

		# URL of this wiki
		$wikiurl = rtrim( WebRequest::detectServer().dirname( $_SERVER['SCRIPT_NAME'] ), '\\' );
						
		$url = $wikiurl . '/w/Special:ArticleScores';
		$info = str_replace( '#ITEMS', $config->get("articleScoresDefaultItemsCount"), $this->msg( 'articlescores-sp-info' )->text() );
		$out->mBodytext .= "<p>$info</p>";

		/* Controls */
		$output = "<form id='ascoresMenu' class='inline-form row' method='post' action=''>\n";

		// rating
		$output .= "<div class='col'>\n";
    	$output .= "<label for='filterRating'>" . $this->msg( 'articlescores-rating' )->text() . "</label>\n";
		$output .= "<select name='filterRating' class='form-control col'>\n";
		if(isset($_POST["filterRating"])) $filterRating = $_POST["filterRating"]; else $filterRating = 5;
		for($i=1;$i<=5;$i++) {
			$output .= "<option value='$i' ";
			if($filterRating == $i) $output .= "selected";
			$output .= ">$i</option>\n";
		}
		$output .= "</select>\n";
		$output .= "</div>\n";

		// number of reviewers FROM
		$output .= "<div class='col'>\n";
    	$output .= "<label for='filterReviewersFROM'>" . $this->msg( 'articlescores-ratingsNo-from' )->text() . "</label>\n";
		$output .= "<select name='filterReviewersFROM' class='form-control col'>\n";
		if(isset($_POST["filterReviewersFROM"])) $filterReviewersFROM = $_POST["filterReviewersFROM"];
		else $filterReviewersFROM = $config->get("articleScoresDefaultReviewersCountFROM");
		for($i=1;$i<=100;$i++) {
			$output .= "<option value='$i' ";
			if($filterReviewersFROM == $i) $output .= "selected";
			$output .= ">$i</option>\n";
		}
		$output .= "</select>\n";
		$output .= "</div>\n";

		// number of reviewers TO
		$output .= "<div class='col'>\n";
    	$output .= "<label for='filterReviewersTO'>" . $this->msg( 'articlescores-ratingsNo-to' )->text() . "</label>\n";
		$output .= "<select name='filterReviewersTO' class='form-control col'>\n";
		if(isset($_POST["filterReviewersTO"])) $filterReviewersTO = $_POST["filterReviewersTO"];
		else $filterReviewersTO = $config->get("articleScoresDefaultReviewersCountTO");
		for($i=1;$i<=100;$i++) {
			$output .= "<option value='$i' ";
			if($filterReviewersTO == $i) $output .= "selected";
			$output .= ">$i</option>\n";
		}
		$output .= "</select>\n";
		$output .= "</div>\n";

		// number of items displayed
		$output .= "<div class='col'>\n";
    	$output .= "<label for='filterItemsNo'>" . $this->msg( 'articlescores-itemsNo' )->text() . "</label>\n";
		$output .= "<select name='filterItemsNo' class='form-control col'>\n";
		if(isset($_POST["filterItemsNo"])) $filterItemsNo = $_POST["filterItemsNo"];
		else $filterItemsNo = $config->get("articleScoresDefaultItemsCount");
		for($i=50;$i<=2000;$i+=50) {
			$output .= "<option value='$i' ";
			if($filterItemsNo == $i) $output .= "selected";
			$output .= ">$i</option>\n";
		}
		$output .= "</select>\n";
		$output .= "</div>\n";
		// submit
		$output .= "<button type='submit' class='btn btn-primary form-control mt-3'>" . $this->msg( 'feedbackus-send-button' )->text() . "</button>\n";
		$output .= "</form>\n";

		// SHOW LIST
		$res = $dbr->select(
			'articlescores_sum',
			array( 'page_id', 'score', 'usersCount' ),
			"score=$filterRating and usersCount>=$filterReviewersFROM and usersCount<=$filterReviewersTO",
			'__METHOD__',
			array( 'ORDER BY' => 'score DESC','LIMIT' => $config->get("articleScoresDefaultItemsCount") )
		);

		$output .= "<table class='table table-striped mt-4'>\n<thead>\n<tr>\n";
		$output .= "<th>" . $this->msg( 'articlescores-page' )->text() . "</th>\n";
		$output .= "<th>" . $this->msg( 'articlescores-score' )->text() . "</th>\n";
		$output .= "<th>" . $this->msg( 'articlescores-ratingsNo' )->text() . "</th>\n";
		$output .= "</tr>\n</thead>\n";

		$output .= "<tbody>\n";
		foreach ( $res as $row ) {
			$res2 = $dbr->selectRow(
				'page',
				array( 'page_namespace', 'page_title' ),
				array( 'page_id' => $row->page_id )
			);
			$namespace_allowed = in_array($res2->page_namespace, $config->get("namespaces")) ? true:false;
			if( $res2 && $namespace_allowed ) {
				$article = Article::newFromId( $row->page_id );
				$title = $article->getTitle();
				$output .= "<tr>\n";
				$output .= "<td><a href='$wikiurl/w/" . $title->getPrefixedDBkey() . "'>" . $title->getPrefixedDBkey() . "</a></td>\n";
				$output .= "<td>" . round( $row->score, 2 ) . "</td>\n";
				$output .= "<td>" . $row->usersCount . "</td>\n";
				$output .= "</tr>\n";
			}
		}
		$output .= "</tbody>\n<table>\n";
		$out->addHTML( $output );
	}
	
}
