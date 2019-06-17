<?php

/**
 * SpecialPage for FeedbackUs extension
 * Called with REQUEST parameters page_id and comment,
 * adds feedback to database
 * Otherwise displays the list of commented articles
 * @ingroup Extensions
 * @author Josef Martiňák
 * @license MIT
 * @file
 */


class FeedbackUs extends SpecialPage {
	function __construct() {
		parent::__construct( 'FeedbackUs', 'feedbackright' );	// "editinterface" restrict to sysops
	}

	function execute($param) {

		global $wgReadOnly;
		$this->setHeaders();
		$request = $this->getRequest();
		$out = $this->getOutput();
		$user = $this->getUser();
		$config = $this->getConfig();
		//$this->checkPermissions();

		# URL of this wiki
		$wikiurl = rtrim( WebRequest::detectServer().dirname( $_SERVER['SCRIPT_NAME'] ), '/' );

		$dbr = wfGetDB( DB_SLAVE );
		$dbw = wfGetDB( DB_MASTER );

		$page_id = $request->getInt( 'page_id' );
		$action = $request->getInt( 'action' );

		// is the article's namespace allowed?
		if ( $page_id  ) {
			$res = $dbr->selectRow(
				'page',
				array( 'page_namespace', 'page_title' ),
				array( 'page_id' => $page_id )
			);
			if( !in_array($res->page_namespace, $config->get("namespaces")) {
				$out->disable();
				header( 'Content-type: application/text; charset=utf-8' );
				echo "Error: forbidden namespace";
				exit;	
			}
		}

		/*
		$comment = $request->getVal( 'comment' );
		$rev_id = $request->getInt( 'rev_id' );
		*/

		$ret = '';
		switch($action) {

			/****************************
			 * insert comment (frontend)
			 ****************************/
			case 'insertcomment':
			$comment = $request->getVal( 'comment' );
			$email = $request->getVal( 'email' );
			if( empty ( $email ) || !preg_match( "/^[a-zA-Z0-9_.+-]+@[a-zA-Z0-9-]+\.[a-zA-Z0-9-.]+$/", $email ) ) $email = '';
			$rev_id = $request->getInt( 'rev_id' );
			if( !empty($comment) && !empty($rev_id) && !empty($page_id) ) {

				// No DB writes in readonly
				if( !empty( $wgReadOnly ) ) {
					$ret = 'Error: readonly mode';
					break;
				}

				$score = $request->getVal( 'score' );
				if( empty ( $score ) || !preg_match( "/^[0-5]$/", $score ) ) {
					$score = 0;
				}

				$res = $dbw->insert(
					'feedbackus',
					array(
						'page_id' => $page_id,
						'rev_id' => $rev_id,
						'comment' => $comment,
						'email' => $email
					)
				);
				
				if ( !$res ) { 
					$ret = 'Error: DB error';
					break;
				}

				if( $config->get("otrs") ) {
					// send comment to OTRS
					if(empty( $email )) $email = $config->get("otrsAddress");
					$subject = $this->msg( 'feedbackus-message-subject' )->plain();
					$body = $wikiurl. "/index.php?curid=" . $page_id . PHP_EOL . PHP_EOL . $comment;
					if( !$this->sendMail( $config->get("otrsAddress"), $email, $subject, $body ) ) {
						$ret = 'Error: sending mail';
					}
				}

			}
			else $ret = 'Error: insert comment';
			break;

			/****************************
			 * save article score (frontend)
			 ****************************/
			case 'insertrating':
			// No DB writes in readonly
			if( !empty( $wgReadOnly ) ) {
				$ret = 'Error: readonly mode';
				break;
			}
			$score = $request->getVal( 'score' );
			if( empty ( $score ) || !preg_match( "/^[0-5]$/", $score ) ) {
				$ret = "Error: no score set";
				break;
			}
			$ip = $_SERVER['REMOTE_ADDR'];

			// Is there a row for this article in articlescores_sum?
			// If not, insert new empty record.
			$res = $dbr->selectRow(
				'articlescores_sum',
				array('page_id'),
				array('page_id' => $page_id )
			);
			if ( !$res ) {
				$res2 = $dbw->insert(
					'articlescores_sum',
					array('page_id' => $page_id )
				);
			}

			// In case this revision has been rated before - update it.
			// Otherwise insert new record to articlescores table.
			$res = $dbr->selectRow(
				'articlescores',
				array( 'id', 'day_ips', 'last_inserted', 'scoreSum', 'usersCount' ),
				array( 'rev_page' => $page_id, 'rev_id' => $rev_id )
			);

			$dtnow = new DateTime();
			$now = $dtnow->format( 'YmdHis' );
					
			if ( $res ) {

				$dayips = $res->day_ips;
				$dbdate = new DateTime( $res->last_inserted );

				// dayips reset if recent day can't be found in db
				$newdayips = "$dayips,$ip";
				if ( $dtnow->format('Ymd') != $dbdate->format( 'Ymd' ) ) {
					
					$res2 = $dbw->update(
						'articlescores',
						array( 'day_ips' => '', 'last_inserted' => $now ),
						array( 'rev_page' => $page_id, 'rev_id' => $rev_id )
					);
					
					$dayips = '';
					$newdayips = $ip;
				}
	
				// is recent IP address allowed to score today?
				if ( strpos( ',' . $dayips . ',', ',' . $ip . ',' ) === false ||
						$dtnow->format( 'Ymd' ) != $dbdate->format( 'Ymd' ) )
				{
					// update rating (condition one ip per revision per day)
					$res2 = $dbw->update(
						'articlescores',
						array(
							'scoreSum' => $res->scoreSum + $score,
							'usersCount' => $res->usersCount + 1,
							'day_ips' => $newdayips,
							'last_inserted' => $now ),
						array('id' => $res->id )
					);
					
					if ( !$res2 ) {
						$ret = 'articlescores-error-update';
						break;
					}
				}
				else {
					$ret = 'articlescores-dayips-not-today';
					break;
				}
			}
			else {
				// insert new revision's rating
				$res2 = $dbw->insert(
					'articlescores',
					array(
						'rev_page' => $page_id,
						'rev_id' => $rev_id,
						'scoreSum' => $score,
						'usersCount' => 1,
						'day_ips' => $ip,
						'last_inserted' => $now )
				);
				if ( !$res2 ) {
					$ret = 'articlescores-error-insert';
					break;
				}
			}				
			// save new score
			$ret = FeedbackUsHooks::saveScore( $page_id );
			break;

			/****************************
			 * mark feedback as (un)solved (backend)
			 ****************************/
			case 'solvefeedback':
			// No DB writes in readonly
			if( !empty( $wgReadOnly ) ) {
				$ret = 'Error: readonly mode';
				break;
			}
			$this->checkPermissions();

			$solved = $request->getInt( 'solved' );  // feedback had been processed
			$solvedComment = $request->getVal( 'solvedComment' );
			$feedback_id = $request->getInt( 'feedback_id' );

			if( !in_array($solved, [0, 1]) || !is_numeric($feedback_id) ) {
				$ret = 'Error: Wrong input';
				break;
			}
			$this->checkPermissions();

			// update DB
			if($solved) {
				$res = $dbw->update(
					'feedbackus',
					array( 'solved_user' => $user->getName(), 'solved_timestamp' => 'NOW()', 'solved_comment' => $solvedComment ),
					array( 'id' => $feedback_id )
				);
			}
			else {
				$res = $dbw->update(
					'feedbackus',
					array( 'solved_user' => '', 'solved_timestamp' => '', 'solved_comment' => '' ),
					array( 'id' => $feedback_id )
				);
			}

			// pager
			$page = $request->getInt('fuPageNumber',1);
			if(!$page) $page=1;
				echo "<script>window.location.href='".$wikiurl . "/index.php?title=Special:FeedbackUs&fuPageNumber=$page'</script>";
				exit;
			}
			break;
		}

		if(!empty($ret)) {
			// Display the output
			$out->disable();
			header( 'Content-type: application/text; charset=utf-8' );
			echo $ret;
			exit;
		}






		/****************************
		 * show comments (backend)
		 ****************************/
TODO
		$this->checkPermissions();
		$out->mBodytext .= $this->msg( 'feedbackus-specialpage-text' )->text();
		if( $config->get("otrs") ) $out->mBodytext .= PHP_EOL . $this->msg( 'feedbackus-specialpage-otrsinfo' )->text();
		
		// prepare output table 
		$tableheader = "<table class='wikitable'>";
		$tableheader .= "<tr><th>" . $this->msg( 'feedbackus-specialpage-articlename' )->text() . "</th>";
		$tableheader .= "<th>" . $this->msg( 'feedbackus-specialpage-comments' )->text() . "</th>";
		$tableheader .= "<th>Email</th>";
		$tableheader .= "<th>" . $this->msg( 'feedbackus-specialpage-timestamp' )->text() . "</th><th></th></tr>";
		$hascontent = false;


			
			####################################
			# SHOW AT SPECIAL PAGE
			####################################
			// pager
			$page = $request->getInt('fuPageNumber',1);
			$output = "<br/><br/><form id='fuMenu' name='fuMenu' method='post' action=''>\n";
			// previous
			if($page==1) $prev = 1; 
			else $prev = $page-1;

			if($page > 1) {
				$output .= "<input type='button' onclick=";
				$output .= "\"document.getElementById('fuPageNumber').value=$prev;";
				$output .= "this.form.submit();\" ";
				$output .= "value='<&nbsp;' title='";
				$output .= $this->msg( 'feedbackus-previous' )->plain();
				$output .= "'/>&nbsp;";
			}
			else {
				$output .= "<input type='button' value='<'/>&nbsp;";
			}
			// show the number of comments
			$resp = $dbr->select(
				"feedbackus",
				array("id"),
				'comment!=""'
			);
			$nm = ceil($resp->numRows()/$config->get("pageCount"));
			for($i=1;$i<=$nm;$i++){
				if($i==$page) {
					$output .= "<input type='button' value='$i&nbsp;' style='color:red'/>";
				}
				else {
					$output .= "<input type='button' ";
					$output .= "onclick=\"document.getElementById('fuPageNumber')";
					$output .= ".value=$i;this.form.submit();\" value='$i&nbsp;'/>";
				}
			}
			// next
			if($page<$nm) {
				$next = $page+1; 
			}
			else {
				$next = $nm;
			}
			if($page < $nm) {
				$output .= "&nbsp;<input type='button' ";
				$output .= "onclick=\"document.getElementById('fuPageNumber')";
				$output .= ".value=$next;this.form.submit();\" ";
				$output .= "value='>' title='".$this->msg( 'feedbackus-next' )->plain()."'/>";
			}
			else {
				$output .= "&nbsp;<input type='button' value='>'/>";
			}
			$output .= "<input id='fuPageNumber' name='fuPageNumber' ";
			$output .= "type='hidden' value='1'/>";
			$output .= "</form><br/>\n";
			$out->addHTML( $output );
			
			
			// prepare output table 
			$output = $tableheader;
						
			// show results
			$res = $dbr->select(
				'feedbackus',
				array( 'page_id', 'comment', 'timestamp', 'email', 'id' ),
				'comment!=""',
				'__METHOD__',
				array( 'ORDER BY' => 'timestamp DESC','LIMIT' => $config->get("pageCount"), "OFFSET" => (($page-1)*$config->get("pageCount")) )
			);
			foreach ( $res as $row ) {
				if( $row->page_id == 0 ) continue;
				if( !($article = Article::newFromId( $row->page_id )) ) continue;
				$output .= "<tr style='vertical-align:top'>";
				$title = $article->getTitle();
				$output .= "<td><a href='". $wikiurl . "/index.php?title=Special:FeedbackUs&page_id=" . $row->page_id . "&detail=1'>" . preg_replace('/_/', ' ', $title->getPrefixedDBkey() ) . "</a> ";
				$output .= "<a href='". $wikiurl . "/index.php?title=" . $title->getPrefixedDBkey() . "'>&#8921</a></td>";

				$crr = explode( '$', $row->comment, 2 );
				if( sizeof( $crr ) > 1 ) {
					$opts = $this->getOptionsText( $crr[0] );
					$comm = '';
					if(sizeof($opts)>0) {
						$comm .= '<ul>';
						foreach( $opts as $o ) {
							$comm .= "<li>$o</li>";
						}
						$comm .= '</ul>';
					}
					$comm .= htmlspecialchars( $crr[1], ENT_QUOTES );
				}
				else {
					$comm = htmlspecialchars( $row->comment, ENT_QUOTES );
				}
				$output .= "<td>$comm</td>";
				$output .= "<td>" . $row->email . "</td>";
				$ts = substr( $row->timestamp, 0, 10 );
				if( $ts == '0000-00-00' ) $ts = '';
				$output .= "<td>$ts</td>";
				$hascontent = true;
				$output .= "<td><a href='".$wikiurl."/index.php?title=Special:FeedbackUs&repaired=1&feedback_id=".$row->id."&fuPageNumber=$page'>";
				$output .= $this->msg( 'feedbackus-specialpage-repaired' )->text() . "</a></td>";
				$output .= "</tr>";
			}
			$output .= "</table>";
			if( $hascontent ) {
				$out->addHTML( $output );
			}			
		/*
		else {
			#########################################################
			# SHOW DETAILS AT SPECIAL PAGE (all article's comments )
			#########################################################
			// prepare output table 
			$output = "<br/><br/><input type='button' onclick=\"history.back();return false;\" value='<<'/>".$tableheader;
						
			// pager
			$page = $request->getInt('fuPageNumber',1);
						
			// show results
			$res = $dbr->select(
				'feedbackus',
				array( 'comment', 'timestamp', 'email', 'id' ),
				array( 'page_id' => $page_id ),
				'__METHOD__',
				array( 'ORDER BY' => 'timestamp DESC' )
			);
			foreach ( $res as $row ) {
				if( $page_id == 0 ) continue;
				if( !($article = Article::newFromId( $page_id )) ) continue;
				$output .= "<tr style='vertical-align:top'>";
				$title = $article->getTitle();
				$output .= "<td><a href='". $wikiurl . "/index.php?title=" . $title->getPrefixedDBkey() . "'>" . $title->getPrefixedDBkey() . "</a></td>";

				$crr = explode( '$', $row->comment, 2 );
				if( sizeof( $crr ) > 1 ) {
					$opts = $this->getOptionsText( $crr[0] );
					$comm = '<ul>';
					foreach( $opts as $o ) {
						$comm .= "<li>$o</li>";
					}
					$comm .= '</ul>';
					$comm .= htmlspecialchars( $crr[1], ENT_QUOTES );
				}
				else {
					$comm = htmlspecialchars( $row->comment, ENT_QUOTES );;
				}
				$output .= "<td>$comm</td>";
				$output .= "<td>" . $row->email . "</td>";
				if( $config->get("otrs") ) $output .= ' (OTRS)';
				$ts = substr( $row->timestamp, 0, 10 );
				if( $ts == '0000-00-00' ) $ts = '';
				$output .= "<td>$ts</td>";
				$output .= "<td><a href='".$wikiurl."/index.php?title=Special:FeedbackUs&repaired=1&feedback_id=".$row->id."&fuPageNumber=$page'>";
				$output .= $this->msg( 'feedbackus-specialpage-repaired' )->text() . "</a></td>";
				$output .= "</tr>";
				$hascontent = true;			
			}
			$output .= "</table>";
			$output .= "<input type='button' onclick=\"history.back();return false;\" value='<<'/>";
			if( $hascontent ) {
				$out->addHTML( $output );
			}
		}
		*/
	}
	



	/**
	 * Send email
	 */
	function sendMail( $address, $from, $subject, $body ) {
		$header = "MIME-Version: 1.0\r\n";
		$header .= "Content-Type: text/plain; charset=\"utf-8\"\r\n";
		$header .= "X-Mailer: PHP\r\n";
		$header .= "From: $from\r\n";
		$header .= "Return-Path: $from\r\n";
		$header .= "Reply-To: $from\r\n";
		if( mail($address, "=?UTF-8?B?" . base64_encode($subject) . "?=", $body, $header) ) return true;
		else return false;
	}
	
	
}
