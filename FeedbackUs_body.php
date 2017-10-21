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

		# URL of this wiki
		if( !defined( 'WIKIURL' ) ) {
			define( 'WIKIURL', rtrim( WebRequest::detectServer().dirname( $_SERVER['SCRIPT_NAME'] ), '\\' ) );
		}

		# Read configuration options 
		require_once( __DIR__ . '/config.php' );
		
		$page_id = $request->getInt( 'page_id' );
		$dbr = wfGetDB( DB_SLAVE );
		// FU
		$write = $request->getInt( 'write' );
		$comment = $request->getVal( 'comment' );
		$repaired = $request->getInt( 'repaired' );  // feedback had been processed
		$feedback_id = $request->getInt( 'feedback_id' );
		// AS
		$rev_id = $request->getInt( 'rev_id' );
		

		// is the article's namespace allowed?
		$namespace_allowed = true;
		if ( $page_id  ) {
			$res = $dbr->selectRow(
				'page',
				array( 'page_namespace', 'page_title' ),
				array( 'page_id' => $page_id )
			);
			if ( $res !==false && strpos( ',' . FU_NAMESPACES . ',', ',' .
					$res->page_namespace . ',') === false ) {
				$namespace_allowed = false;
			}
		}


		if ( $write==1 && $page_id>0 && $namespace_allowed && $rev_id && empty($repaired) ) {
			# ###################################
			# SEND FEEDBACK
			####################################


			// pokud je wiki v režimu $wgReadOnly, žádný zápis do DB
			if( !empty( $wgReadOnly ) ) {
				$out->disable();
				echo 'Wiki je v režimu jen pro čtení (readonly mode)';
				return true;
			}

			$email = $request->getVal( 'email' );
			if( empty ( $email ) || !preg_match( "/^[a-zA-Z0-9_.+-]+@[a-zA-Z0-9-]+\.[a-zA-Z0-9-.]+$/", $email ) ) {
				$email = '';
			}
			
			$score = $request->getVal( 'score' );
			if( empty ( $score ) || !preg_match( "/^[0-5]$/", $score ) ) {
				$score = 0;
			}
			
			$comment = $request->getVal( 'comment' );
			if( empty ( $comment ) ) {
				$comment = '';
			}
			$options = '';
			$options = $request->getVal( 'options' );

			$dbw = wfGetDB( DB_MASTER );
			
			$ret = 'ok';
			if( $options || $comment ) {
				// insert new feedback
				$res = $dbw->insert(
					'feedbackus',
					array(
						'page_id' => $page_id,
						'comment' => $options . '$' . $comment,
						'email' => $email
					)
				);
				if ( !$res ) {
					$ret = 'err';
				}
			}
			
			

			####################################
			# rate an article
			####################################
			if( $ret == 'ok' && $score>0 ) {
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
				$ok = true;
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
							$ok = false;
							$ret = 'articlescores-error-update';
						}
					}
					else {
						$ok = false;
						$ret = 'articlescores-dayips-not-today';
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
						$ok = false;
						$ret = 'articlescores-error-insert';
					}
				}				
				// save new score
				if ( $ok ) $ret = FeedbackUsHooks::saveScore( $page_id );
				
			}
						
			$lb = wfGetLBFactory();
			$lb->shutdown();
			
			// Display the output
			$out->disable();
			header( 'Content-type: application/text; charset=utf-8' );
			echo $ret;
			exit;
		}
		elseif( $write == 1 && !empty( $comment ) && empty( $page_id ) && empty($repaired) ) {
			// message from magic box
			
			$email = $request->getVal( 'email' );
			if( empty ( $email ) || !preg_match( "/^[a-zA-Z0-9_.+-]+@[a-zA-Z0-9-]+\.[a-zA-Z0-9-.]+$/", $email ) ) {
				$email = '';
			}
			$dbw = wfGetDB( DB_MASTER );
			// insert new feedback
			$res = $dbw->insert(
				'feedbackus',
				array(
					'page_id' => 0,
					'comment' => $comment,
					'email' => $email
				)
			);
			$ret = 'ok';
			if ( !$res ) {
				$ret = 'err';
			}
			
			if( FU_SEND_TO_OTRS && !empty( $email ) ) {
				// pošli zprávu do OTRS
				$subject = $this->msg( 'feedbackus-message-subject' )->plain();
				if( !$this->sendMail( FU_OTRS_ADDRESS, $email, $subject, $comment ) ) {
					$ret = 'err';
				}
			}

			$lb = wfGetLBFactory();
			$lb->shutdown();
			
			// Display the output
			$out->disable();
			header( 'Content-type: application/text; charset=utf-8' );
			echo $ret;
			exit;
		}
		elseif( $repaired == 1 && !empty($feedback_id) ) {
			####################################
			# REMOVE FEEDBACK FROM DB
			####################################
			
			if( !isset( $wgReadOnly ) ) {
				$this->checkPermissions();
				$dbw = wfGetDB( DB_MASTER );
				// remove from DB
				$res = $dbw->delete(
					'feedbackus',
					array( 'id' => $feedback_id )
				);
			}
		}

		$this->checkPermissions();
		$out->mBodytext .= $this->msg( 'feedbackus-specialpage-text' )->text();
		if( FU_SEND_TO_OTRS ) $out->mBodytext .= PHP_EOL . $this->msg( 'feedbackus-specialpage-otrsinfo' )->text();
		
		// prepare output table 
		$tableheader = "<table class='wikitable'>";
		$tableheader .= "<tr><th>" . $this->msg( 'feedbackus-specialpage-articlename' )->text() . "</th>";
		$tableheader .= "<th>" . $this->msg( 'feedbackus-specialpage-comments' )->text() . "</th>";
		$tableheader .= "<th>Email</th>";
		$tableheader .= "<th>" . $this->msg( 'feedbackus-specialpage-timestamp' )->text() . "</th><th></th></tr>";
		$hascontent = false;
		if( !$request->getInt( 'detail' ) ) {
			
			####################################
			# SHOW AT SPECIAL PAGE
			####################################
			
			// pager
			$page = $request->getInt('fuPageNumber',1);
			$output = "<br/><br/><form id='fuMenu' name='fuMenu' method='post' action=''>\n";
			// previous
			if($page==1) {
				$prev = 1; 
			}
			else {
				$prev = $page-1;
			}
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
				array("id")
			);
			$nm = ceil($resp->numRows()/FU_PAGE_COUNT);
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
				array( 'ORDER BY' => 'timestamp DESC','LIMIT' => FU_PAGE_COUNT, "OFFSET" => (($page-1)*FU_PAGE_COUNT) )
			);
			foreach ( $res as $row ) {
				$output .= "<tr style='vertical-align:top'>";
				if( $row->page_id == 0 ) {
					$output .= "<td><a href='". WIKIURL . "/index.php?title=Special:FeedbackUs&page_id=" . $row->page_id . "&detail=1'>MAGIC</a></td>";
				}
				else {
					if( !($article = Article::newFromId( $row->page_id )) ) continue;
					$title = $article->getTitle();
					$output .= "<td><a href='". WIKIURL . "/index.php?title=Special:FeedbackUs&page_id=" . $row->page_id . "&detail=1'>" . preg_replace('/_/', ' ', $title->getPrefixedDBkey() ) . "</a> ";
					$output .= "<a href='". WIKIURL . "/index.php?title=" . $title->getPrefixedDBkey() . "'>&#8921</a></td>";
				}
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
				if( FU_SEND_TO_OTRS ) $output .= ' (OTRS)';
				$ts = substr( $row->timestamp, 0, 10 );
				if( $ts == '0000-00-00' ) $ts = '';
				$output .= "<td>$ts</td>";
				$hascontent = true;
				$output .= "<td><a href='".WIKIURL."index.php?title=Special:FeedbackUs&repaired=1&feedback_id=".$row->id."'>";
				$output .= $this->msg( 'feedbackus-specialpage-repaired' )->text() . "</a></td>";
				$output .= "</tr>";
			}
			$output .= "</table>";
			if( $hascontent ) {
				$out->addHTML( $output );
			}
			
			$lb = wfGetLBFactory();
			$lb->shutdown();
		}
		else {
			####################################
			# SHOW DETAILS AT SPECIAL PAGE (all article's comments )
			####################################
			// prepare output table 
			$output = "<br/><br/><input type='button' onclick=\"history.back();return false;\" value='<<'/>".$tableheader;
						
			// show results
			$res = $dbr->select(
				'feedbackus',
				array( 'comment', 'timestamp', 'email' ),
				array( 'page_id' => $page_id ),
				'__METHOD__',
				array( 'ORDER BY' => 'timestamp DESC' )
			);
			foreach ( $res as $row ) {
				$output .= "<tr style='vertical-align:top'>";
				if( $page_id == 0 ) {
					$output .= "<td>MAGIC</td>";
				}
				else {
					if( !($article = Article::newFromId( $page_id )) ) continue;
					$title = $article->getTitle();
					$output .= "<td><a href='". WIKIURL . "/index.php?title=" . $title->getPrefixedDBkey() . "'>" . $title->getPrefixedDBkey() . "</a></td>";
				}
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
				if( FU_SEND_TO_OTRS ) $output .= ' (OTRS)';
				$ts = substr( $row->timestamp, 0, 10 );
				if( $ts == '0000-00-00' ) $ts = '';
				$output .= "<td>$ts</td>";
				$output .= "<td><a href='".WIKIURL."index.php?title=Special:FeedbackUs&repaired=1&feedback_id=".$row->id."'>";
				$output .= $this->msg( 'feedbackus-specialpage-repaired' )->text() . "</a></td>";
				$output .= "</tr>";
				$hascontent = true;			
			}
			$output .= "</table>";
			$output .= "<input type='button' onclick=\"history.back();return false;\" value='<<'/>";
			if( $hascontent ) {
				$out->addHTML( $output );
			}
			
			$lb = wfGetLBFactory();
			$lb->shutdown();
		}
	}
	
	// send email
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
	
	// get options' texts
	function getOptionsText( $options ) {
		$arr = explode(  '|', $options );
		$output = array();
		$i = 0;
		foreach( $arr as $o ) {
			if( $o !='' ) $output[$i] = $this->msg( 'feedbackus-option' . $o )->plain();
			$i++;
		}
		return $output;
	}
	
}
