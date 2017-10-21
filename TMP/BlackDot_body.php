<?php

/**
 * SpecialPage for BlackDot extenion
 * Called with REQUEST parameters page_id and comment,
 * adds feedback to database
 * Otherwise displays the list of commented articles
 */

class BlackDot extends SpecialPage {
	function __construct() {
		parent::__construct( 'BlackDot', 'blackdotright' );	// "editinterface" restrict to sysops
	}


	function execute($param) {

		global $wgReadOnly;
	
		$this->setHeaders();
		$request = $this->getRequest();
		$out = $this->getOutput();
		$user = $this->getUser();


		// read posts
		$page_id = $request->getInt( 'page_id' );
		$repaired = $request->getInt( 'repaired' );
		$comment = $request->getVal( 'comment' );
		if( empty ( $comment ) ) {
			$comment = '';
		}
		$dbr = wfGetDB( DB_SLAVE );

		// is the article's namespace allowed?
		$namespace_allowed = true;
		if ( $page_id  ) {
			$res = $dbr->selectRow(
				'page',
				array( 'page_namespace', 'page_title' ),
				array( 'page_id' => $page_id )
			);
			if ( $res !==false && strpos( ',' . BD_NAMESPACES . ',', ',' .
					$res->page_namespace . ',') === false ) {
				$namespace_allowed = false;
			}
		}

		if ( $page_id && $namespace_allowed && !$repaired) {

			# ###################################
			# SEND FEEDBACK
			####################################
			
			// pokud je wiki v režimu $wgReadOnly, žádný zápis do DB
			if( isset( $wgReadOnly ) ) {
				$out->disable();
				echo 'Wiki je v režimu jen pro čtení (readonly mode)';
				return true;
			}
			
			$dbw = wfGetDB( DB_MASTER );
			// insert new feedback
			//$dbw->begin();
			$res = $dbw->insert(
				'blackdot',
				array(
					'page_id' => $page_id,
					'comment' => $comment
				)
			);
			//$dbw->commit();
			$ret = 1;
			if ( !$res ) {
				$ret = 0;
			}
			// Display the output
			$out->disable();
			header( 'Content-type: application/text; charset=utf-8' );
			echo $ret;
			exit;
		}
		elseif( $repaired && $page_id ) {
			####################################
			# REMOVE BLACK DOTED ARTICLE FROM DB
			####################################
			
			// pokud je wiki v režimu $wgReadOnly, žádný zápis do DB
			if( !isset( $wgReadOnly ) ) {
				$this->checkPermissions();
				$dbw = wfGetDB( DB_MASTER );
				// remove from DB
				//$dbw->begin();
				$res = $dbw->delete(
					'blackdot',
					array( 'page_id' => $page_id )
				);
				//$dbw->commit();
			}
		}
		
		
		####################################
		# SHOW BLACK DOTED ARTICLES
		####################################

		$this->checkPermissions();
		$res = $dbr->select(
			'blackdot',
			array( 'page_id', 'comment', 'last_comment_timestamp' ),
			'',
			'__METHOD__',
			//array( 'ORDER BY' => 'score DESC','LIMIT' => BD_SPECIAL_ITEMS )
			array( 'ORDER BY' => 'id DESC' )
		);

		$out->mBodytext .= $this->msg( 'blackdot-specialpage-text' )->text();
		// prepare array (page_id, comments separated by HR)
		$arr = array();	// comments
		$timestamp = array();	// last comment timestamp
		$counts = array();	// number of clicks
		foreach ( $res as $row ) {
			if( array_key_exists( 'id' . $row->page_id, $arr ) ) {
				$counts['id' . $row->page_id]++;
				if( !empty( $row->comment ) ) {
					$arr['id' . $row->page_id] .= '###' . $row->comment;
				}
				if( empty($timestamp['id' . $row->page_id]) || $row->last_comment_timestamp > $timestamp['id' . $row->page_id] ) {
					$timestamp['id' . $row->page_id] = $row->last_comment_timestamp;
				}
			}
			else {
				$counts['id' . $row->page_id] = 1;
				$arr['id' . $row->page_id] = $row->comment;
				$timestamp['id' . $row->page_id] = $row->last_comment_timestamp;
			}
		}
		
		arsort( $counts, SORT_NUMERIC );
			
		// prepare output table 
		$output = "{| class='wikitable sortable'\n";
		$output .= "|-\n";
		$output .= "!" . $this->msg( 'blackdot-specialpage-articlename' )->text();
		$output .= "!!class='unsortable'|" . $this->msg( 'blackdot-specialpage-comments' )->text();
		$output .= "!!" . $this->msg( 'blackdot-specialpage-reviewcount' )->text();
		$output .= "!!" . $this->msg( 'blackdot-specialpage-timestamp' )->text();
		$output .= "!!class='unsortable'|". $this->msg( 'blackdot-specialpage-repaired-legend' )->text() . " \n";
		$hascontent = false;
		
		foreach ( $counts as $key => $value ) {
			$key = substr( $key, 2);
			if( !($article = Article::newFromId( $key )) ) continue;
			$title = $article->getTitle();
			$output .= "|- valign='top'\n";
			$comments = htmlspecialchars( $arr['id' . $key], ENT_QUOTES );
			$comments = trim( $comments, '#' );
			$comments = str_replace( '###', '<hr/>', $comments );
			$ts = substr( $timestamp['id' . $key], 0, 10 );
			if( $ts == '0000-00-00' ) $ts = '';
			$repaired = "[" . WIKIURL . "/index.php?title=Special:BlackDot&repaired=1&page_id=$key ";
			$repaired .= $this->msg( 'blackdot-specialpage-repaired' )->text() . "]";
			$output .= "|[[" . $title->getPrefixedDBkey() . "]]".PHP_EOL;
			$output .= "|$comments".PHP_EOL;
			$output .= "|$value".PHP_EOL;
			$output .= "|$ts".PHP_EOL;
			$output .= "|$repaired".PHP_EOL;
			$hascontent = true;
		}
		$output .= "|}\n";
		if( $hascontent ) {
			$out->addWikiText( $output );
		}
		//return true;
	}

}
