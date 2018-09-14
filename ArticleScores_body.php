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
						
		####################################
		# show best ratings
		####################################

		// defaults
		if ( empty( $param ) || !preg_match( '/^best|worst|star[1-5]$/', $param ) ) {
			$param = 'best';
		}

		switch( $param ){
			case 'best':
			$res = $dbr->select(
				'articlescores_sum',
				array( 'page_id', 'score', 'usersCount' ),
				'',
				'__METHOD__',
				array( 'ORDER BY' => 'score DESC','LIMIT' => $config->get("articleScoresItemsCount") )
			);
			$title = $config->get("articleScoresItemsCount") . ' '. $this->msg( 'articlescores-best' )->text();
			break;

			case 'worst':
			$res = $dbr->select(
				'articlescores_sum',
				array( 'page_id', 'score', 'usersCount' ),
				'',
				'__METHOD__',
				array( 'ORDER BY' => 'score', 'LIMIT' => $config->get("articleScoresItemsCount") )
			);
			$title = $config->get("articleScoresItemsCount") . ' '. $this->msg( 'articlescores-worst' )->text();
			break;

			default:
			$res = $dbr->select(
				'articlescores_sum',
				array( 'page_id', 'score', 'usersCount' ),
				array( 'stars' => $param[4] ),
				'__METHOD__',
				array( 'ORDER BY' => 'score DESC', 'LIMIT' => $config->get("articleScoresItemsCount") )
			);
			$title = $this->msg( 'articlescores-score' )->text() . ' ' . $param[4];
			break;
		}

		$out->mBodytext .= "<h1>$title</h1>";
		//$out->mBodytext .= $this->msg( 'articlescores-desc' )->text() . '<br/>';
		$info = str_replace( '#ITEMS', $config->get("articleScoresItemsCount"), $this->msg( 'articlescores-sp-info' )->text() );
		$out->mBodytext .= "$info<br/><br/>";

		$output = "<form id='ascoresMenu' name='ascoresMenu' method='get' action=''>\n";

		// get url of the extension
		$url = $wikiurl . '/index.php?title=Special:ArticleScores';

		$output .= "<select name='ascoresDDmenu.' onchange='location.href=\"$url/\" +";
		$output .= "this.options[this.selectedIndex].value'>\n";
		$output .= "<option value='best' ";
		if ( $param=='best' ) {
			$output .= "selected='selected'";
		}
		$output .= '>' . $config->get("articleScoresItemsCount") . ' '. $this->msg( 'articlescores-best' )->text() . "</option>\n";
		$output .= "<option value='worst' ";
		if ( $param=='worst' ) {
			$output .= "selected='selected'";
		}
		$output .= '>'. $config->get("articleScoresItemsCount") . ' '.$this->msg('articlescores-worst')->text()."</option>\n";
		for ( $i=1; $i<6; $i++ ) {
			$output .= "<option value='star$i' ";
			if ( $param == "star$i" ) {
				$output .= "selected='selected'";
			}
			$output .= '>' . $this->msg( 'articlescores-score' )->text() . " $i</option>\n";
		}
		$output .= "</select>\n";
		$output .= "</form>\n";
		$out->mBodytext .= $output . '<br/>';

		// prepare output table 
		$output = "{| class='wikitable sortable'\n";
		$output .= '! ' . $this->msg( 'articlescores-page' )->text() . ' !! ';
		$output .= $this->msg( 'articlescores-score' )->text() . ' !! ';
		$output .= $this->msg( 'articlescores-ratingsNo' )->text() . "\n";
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
				$output .= "|-\n";
				$output .= '|[[' . $title->getPrefixedDBkey() . "]]  || align='center'|";
				$output .= round( $row->score, 2 ) . ' || ';
				$output .= "align='center'|" . $row->usersCount . "\n";
			}
		}
		$output .= "|}\n";
		$out->addWikiText( $output );
	}
	
}
