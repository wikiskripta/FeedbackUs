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
		$out->mBodytext .= "<h1>" . $this->msg( 'articlescores-score' )->text() . "</h1>";
		$info = str_replace( '#ITEMS', $config->get("articleScoresItemsCount"), $this->msg( 'articlescores-sp-info' )->text() );
		$out->mBodytext .= "<p>$info</p>";

		/* Controls */
		$output = "<form id='ascoresMenu' name='ascoresMenu' method='get' action=''>\n";

		$output .= "<div class='row no-gutters mb-4'>\n";

		// rating
		$output .= "<div class='col'>\n";
		$output .= "<div class='form-group'>\n";
    	$output .= "<label for='filterRating'>" . $this->msg( 'articlescores-rating' )->text() . "</label>\n";
		$output .= "<select name='filterRating' class='form-control'>\n";
		$filterRating = $request->getInt( 'filterRating', 5 );
		for($i=5;$i>0;$i--) {
			$output .= "<option value='$i' ";
			if($filterRating == $i) echo  "selected";
			$output .= ">$</option>\n";
		}
		$output .= "</select>\n";
  		$output .= "</div>\n";

		$output .= "</div>\n";

		// number of reviewers
		$output .= "<div class='col'>\n";
		$output .= "<div class='form-group'>\n";
    	$output .= "<label for='filterReviewersFROM'>" . $this->msg( 'articlescores-ratingNo-from' )->text() . "</label>\n";
		$output .= "<select name='filterReviewersFROM' class='form-control'>\n";
		if($request->getInt( 'filterReviewersFROM')) $filterReviewersFROM = $request->getInt('filterReviewersFROM');
		else $filterReviewersFROM = $config->get("articleScoresDefaultReviewersCountFROM");
		for($i=1;$i<=5;$i++) {
			$output .= "<option value='$i' ";
			if($filterReviewersFROM == $i) echo  "selected";
			$output .= ">$</option>\n";
		}
		$output .= "</select>\n";
		$output .= "</div>\n";

		// number of reviewers TO
		$output .= "<div class='col'>\n";
		$output .= "<div class='form-group'>\n";
    	$output .= "<label for='filterReviewersTO'>" . $this->msg( 'articlescores-ratingNo-to' )->text() . "</label>\n";
		$output .= "<select name='filterReviewersTO' class='form-control'>\n";
		if($request->getInt( 'filterReviewersTO')) $filterReviewersTO = $request->getInt('filterReviewersTO');
		else $filterReviewersTO = $config->get("articleScoresDefaultReviewersCountTO");
		for($i=1;$i<=5;$i++) {
			$output .= "<option value='$i' ";
			if($filterReviewersTO == $i) echo  "selected";
			$output .= ">$</option>\n";
		}
		$output .= "</select>\n";
		$output .= "</div>\n";

		// number of items displayed
		$output .= "<div class='col'>\n";
		$output .= "<div class='form-group'>\n";
    	$output .= "<label for='filterItemsNo'>" . $this->msg( 'articlescores-rating' )->text() . "</label>\n";
		$output .= "<select name='filterItemsNo' class='form-control'>\n";
		if($request->getInt( 'filterItemsNo')) $filterItemsNo = $request->getInt('filterItemsNo');
		else $filterItemsNo = $config->get("articleScoresDefaultItemsCount");
		for($i=50;$i<=2000;$i+50) {
			$output .= "<option value='$i' ";
			if($filterItemsNo == $i) echo  "selected";
			$output .= ">$</option>\n";
		}
		$output .= "</select>\n";
		$output .= "</div>\n";

		// submit
		$output .= "<div class='col'>\n";
		$output .= "<button type='submit' class='btn btn-primary'>" . $this->msg( 'feedbackussendbutton' )->text() . "</button>\n";
		$output .= "</div>\n";

		$output .= "</div>\n";


		// SHOW LIST
		$res = $dbr->select(
			'articlescores_sum',
			array( 'page_id', 'score', 'usersCount' ),
			'score>=$filterRating and usersCount>=$filterReviewersFROM and usersCount<=$filterReviewersTO',
			'__METHOD__',
			array( 'ORDER BY' => 'score DESC','LIMIT' => $config->get("articleScoresItemsCount") )
		);

		$output .= "<div class='row no-gutters font-weight-bold mb-4'>\n";
		$output .= "<div class='col'>" . $this->msg( 'articlescores-page' )->text() . "</div>\n";
		$output .= "<div class='col'>" . $this->msg( 'articlescores-score' )->text() . "</div>\n";
		$output .= "<div class='col'>" . $this->msg( 'articlescores-ratingsNo' )->text() . "</div>\n";
		$output .= "</div>\n";

		$output .= "<div class='row no-gutters font-weight-bold mb-4'>\n";
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
				$output .= "<div class='col'><a href='$wikiurl/w/" . $title->getPrefixedDBkey() . "'>" . $title->getPrefixedDBkey() . "</a></div>\n";
				$output .= "<div class='col'>" . round( $row->score, 2 ) . "</div>\n";
				$output .= "<div class='col'>" . $row->usersCount . "</div>\n";
			}
		}
		$output .= "</div>\n";
		$out->addHTML( $output );
	}
	
}
