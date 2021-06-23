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

		global $wgReadOnly, $wgServer;
		$this->setHeaders();
		$request = $this->getRequest();
		$out = $this->getOutput();
		$user = $this->getUser();
		$config = $this->getConfig();

		# URL of this wiki
		$wikiurl = $wgServer;

		$conn = \MediaWiki\MediaWikiServices::getInstance()->getDBLoadBalancer();

		$dbr = $conn->getConnectionRef(DB_REPLICA);
		$dbw = $conn->getConnectionRef(DB_PRIMARY);

		//$dbr = wfGetDB( DB_REPLICA );
		//$dbw = wfGetDB( DB_PRIMARY );

		$page_id = $request->getInt( 'page_id' );
		$action = $request->getVal( 'action', '' );

		// is the article's namespace allowed?
		if ( $page_id  ) {
			$res = $dbr->selectRow(
				'page',
				array( 'page_namespace', 'page_title' ),
				array( 'page_id' => $page_id )
			);
			if( !in_array($res->page_namespace, $config->get("namespaces"))) {
				$out->disable();
				header( 'Content-type: application/text; charset=utf-8' );
				echo "Error: forbidden namespace";
				return true;
			}
		}

		$ret = '';
		switch($action) {

			/****************************
			 * insert comment (frontend)
			 ****************************/
			case 'insertcomment':
			$comment = $request->getVal( 'comment' );
			$email = $request->getVal( 'email', '' );
			if( empty ( $email ) || !preg_match( "/^[a-zA-Z0-9_.+-]+@[a-zA-Z0-9-]+\.[a-zA-Z0-9-.]+$/", $email ) ) $email = '';
			$rev_id = $request->getInt( 'rev_id' );

			if( !empty($comment) && !empty($rev_id) && !empty($page_id) ) {

				// No DB writes in readonly
				if( !empty( $wgReadOnly ) ) {
					$ret = 'Error: readonly mode';
					break;
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

				$ret = "ok";
				if( $config->get("otrs") ) {
					// send comment to OTRS
					if(empty( $email )) $email = $config->get("otrsAddress");
					$subject = $this->msg( 'feedbackus-message-subject' )->plain();
					$body = $this->msg( 'feedbackus-message-label' )->plain() . PHP_EOL . PHP_EOL . $this->msg( 'feedbackus-message-body' )->plain() . PHP_EOL . PHP_EOL;
					$body .= $wikiurl . "/index.php?curid=" . $page_id . PHP_EOL;
					$body .= $wikiurl . "/w/Special:FeedbackUs/?page_id=" . $page_id . PHP_EOL . PHP_EOL . $comment;
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
			$rev_id = $request->getInt( 'rev_id' );
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

			$solved = $_GET['solved'];  // feedback had been processed
			$feedback_id = $_GET['feedback_id'];
			if(!empty($_GET['pagerid'])) $pagerID = $_GET['pagerid']; else $pagerID = 1;
			if(!empty($_GET['filter'])) $filter = "-archive"; else $filter = '';

			if( !in_array($solved, [0, 1]) || !is_numeric($feedback_id) ) {
				$ret = 'Error: Wrong input';
				break;
			}

			// update DB
			if($solved) {
				$res = $dbw->update(
					'feedbackus',
					array( 'solved_username' => $user->getName(), 'solved_timestamp' => date('Y-m-d H:i:s',time()) ),
					array( 'id' => $feedback_id )
				);
				$body = $this->msg( 'feedbackus-message-label-solved' )->plain();
			}
			else {
				$res = $dbw->update(
					'feedbackus',
					array( 'solved_username' => '', 'solved_timestamp' => '' ),
					array( 'id' => $feedback_id )
				);
				$body = $this->msg( 'feedbackus-message-label' )->plain();
			}
			if( $config->get("otrs") ) {
				// send comment to OTRS
				if(empty( $email )) $email = $config->get("otrsAddress");
				$subject = $this->msg( 'feedbackus-message-subject' )->plain() . PHP_EOL;
				$body .= PHP_EOL . PHP_EOL . $this->msg( 'feedbackus-message-body' )->plain() . PHP_EOL . PHP_EOL . $wikiurl. "/index.php?curid=" . $page_id . PHP_EOL;
				$body .= $wikiurl. "/w/Special:FeedbackUs/?page_id=" . $page_id;
				if( !$this->sendMail( $config->get("otrsAddress"), $email, $subject, $body ) ) {
					$ret = 'Error: sending mail';
				}
			}
			// refresh page
			if( $request->getInt('detail') == 1 ) {
				echo "<script>window.location.href='$wikiurl/w/Special:FeedbackUs/?page_id=" . $page_id . "'</script>";
			}
			else {
				echo "<script>window.location.href='$wikiurl/w/Special:FeedbackUs/$pagerID$filter'</script>";
			}
			return true;
			break;
		
		}

		if(!empty($ret)) {
			// Display the output
			$out->disable();
			header( 'Content-type: application/text; charset=utf-8' );
			echo $ret;
			return true;
		}



		if(!$page_id) {

			/****************************
			 * show comments (backend)
			 ****************************/
			$this->checkPermissions();	
			$filter = '';
			if ( empty( $param ) || !preg_match( '/^[0-9]*(-archive)*$/', $param ) ) $pagerID = 1;
			else {
				$arr = explode("-", $param);
				$pagerID = $arr[0];
				if(sizeof($arr) > 1) $filter = "-archive";
			}

			$out->mBodytext .= $this->msg( 'feedbackus-specialpage-text' )->text();
			if( $config->get("otrs") ) $out->mBodytext .= PHP_EOL . $this->msg( 'feedbackus-specialpage-otrsinfo' )->text();

			// filter
			$output = "<div class='custom-control custom-switch mt-2'>\n";
			$onchangeCode = 'if($(this).is(":checked")) {window.location.href = window.location.origin + "/w/Special:FeedbackUs/1-archive";} else window.location.href = window.location.origin + "/w/Special:FeedbackUs/1"';
			$output .= "<input type='checkbox' class='custom-control-input' id='solvedSwitch' onchange='$onchangeCode' ";
			if($filter) $output .= "checked";
			$output .= ">\n";
			$output .= "<label class='custom-control-label' for='solvedSwitch'>" . $this->msg( 'feedbackus-specialpage-show-solved' )->text() . "</label>\n";
			$output .= "</div>\n";

			// pager
			$output .= "<nav aria-label='pager'>\n";
			$output .= "<ul class='pagination mt-3 ml-0'>\n";	

			// previous
			if($pagerID==1) $prev = 1; 
			else $prev = $pagerID-1;
			$output .= "<li class='page-item' ";
			if($pagerID <= 1) $output .= "disabled";
			$output .= "><a class='page-link' href='$wikiurl/w/Special:FeedbackUs/$prev$filter'>" . $this->msg( 'feedbackus-previous' )->plain() . "</a></li>\n";

			// pager numbers
			if($filter) $cond = "comment!='' and solved_username!=''"; else $cond = "comment!='' and solved_username=''";
			$resp = $dbr->select(
				"feedbackus",
				array("id"),
				$cond
			);

			if(!$resp->numRows()) return true;

			$nm = ceil($resp->numRows()/$config->get("pageCount")); // number of pages
			for($i=1;$i<=$nm;$i++){
				if($i==$pagerID) {
					$output .= "<li class='page-item active'><span class='page-link'>$i</span></li>\n";
				}
				else {
					$output .= "<li class='page-item'><a class='page-link' href='$wikiurl/w/Special:FeedbackUs/$i$filter'>$i</a></li>\n";
				}
			}

			// next
			if($pagerID < $nm) $next = $pagerID + 1; 
			else $next = $nm;
			$output .= "<li class='page-item' ";
			if($pagerID >= $nm) $output .= "disabled";
			$output .= "><a class='page-link' href='$wikiurl/w/Special:FeedbackUs/$next$filter'>" . $this->msg( 'feedbackus-next' )->plain() . "</a></li>\n";

			$output .= "</ul>\n</nav>\n";

			// prepare output table
			$output .= "<table class='table table-striped'>\n<thead>\n<tr>\n";
			$output .= "<th>" . $this->msg( 'feedbackus-specialpage-articlename' )->text() . "</th>\n";
			$output .= "<th>" . $this->msg( 'feedbackus-specialpage-comments' )->text() . "</th>\n";
			$output .= "<th>Email</th>\n";
			$output .= "<th>" . $this->msg( 'feedbackus-specialpage-timestamp' )->text() . "</th>\n<th></th>\n";
			$output .= "</tr>\n</thead>\n<tbody>\n";

			// show results
			$res = $dbr->select(
				'feedbackus',
				array( 'page_id', 'comment', 'timestamp', 'email', 'id', 'solved_username', 'solved_timestamp' ),
				$cond,
				'__METHOD__',
				array( 'ORDER BY' => 'timestamp DESC','LIMIT' => $config->get("pageCount"), "OFFSET" => (($pagerID-1)*$config->get("pageCount")) )
			);
			foreach ( $res as $row ) {
				if( $row->page_id == 0 ) continue;
				if( !($article = Article::newFromId( $row->page_id )) ) continue;
				$output .= "<tr>\n";
				$title = $article->getTitle();
				$output .= "<td>\n";
				$output .= "<a href='$wikiurl/w/" . $title->getPrefixedDBkey() . "'>" . $title->getPrefixedDBkey() . "</a><br>";
				$output .= "<a href='$wikiurl/w/Special:FeedbackUs/?page_id=" . $row->page_id . "' target='_blank'>detail</a>";
				$output .= "</td>\n";
				$output .= "<td>" . htmlspecialchars( $row->comment, ENT_QUOTES ) . "</td>\n";
				$output .= "<td>" . $row->email . "</td>\n";
				$ts = substr( $row->timestamp, 0, 10 );
				if( $ts == '0000-00-00' ) $ts = '';
				$output .= "<td>$ts</td>\n";
				$output .= "<td>\n";
				if($filter) {
					$filter = ltrim($filter,'-');
					$output .= $this->msg( 'feedbackus-specialpage-solved-by' )->text() . ": <a href='$wikiurl/w/User:" . $row->solved_username . "'>" . $row->solved_username . "</a> (" . substr( $row->solved_timestamp, 0, 10 );
					$output .= ", <a class='solvedButton' href='$wikiurl/w/Special:FeedbackUs/?feedback_id=" . $row->id . "&pagerid=$pagerID&filter=$filter&solved=0&action=solvefeedback&page_id=" . $row->page_id . "'>" . $this->msg( 'feedbackus-specialpage-mark-as-nonsolved' )->text() . ")</a>";
				}
				else {
					$output .= "<a class='solvedButton' href='$wikiurl/w/Special:FeedbackUs/?feedback_id=" . $row->id . "&pagerid=$pagerID&filter=$filter&solved=1&action=solvefeedback&page_id=" . $row->page_id . "'>" . $this->msg( 'feedbackus-specialpage-mark-as-solved' )->text() . "</a>";
				}
				$output .= "</td>\n";
				$output .= "</tr>\n";
			}
			$output .= "</tbody>\n</table>\n";
			$out->addHTML( $output );
		}
		else {
			/****************************
			 * show comment's details (backend)
			 ****************************/
			// get info about page_id
			$article = Article::newFromId( $page_id );
			$title = $article->getTitle();
			$output = "<h3><a href='$wikiurl/w/Special:FeedbackUs' title='HOME'>&laquo;</a> <a href='$wikiurl/w/" . $title->getPrefixedDBkey() . "'>" . $title->getPrefixedDBkey() . "</a></h3>\n";

			// prepare output table 
			$output .= "<table class='table table-striped'>\n<thead>\n<tr>\n";
			$output .= "<th>" . $this->msg( 'feedbackus-specialpage-comments' )->text() . "</th>\n";
			$output .= "<th>Email</th>\n";
			$output .= "<th>" . $this->msg( 'feedbackus-specialpage-timestamp' )->text() . "</th>\n<th></th>\n";
			$output .= "</tr>\n</thead>\n<tbody>\n";

			// show results
			$res = $dbr->select(
				'feedbackus',
				array( 'rev_id', 'comment', 'timestamp', 'email', 'id', 'solved_username', 'solved_timestamp' ),
				array('page_id' => $page_id),
				'__METHOD__',
				array( 'ORDER BY' => 'timestamp DESC' )
			);
			foreach ( $res as $row ) {
				$output .= "<tr>\n";
				$output .= "<td>" . htmlspecialchars( $row->comment, ENT_QUOTES ) . "</td>\n";
				$output .= "<td>" . $row->email . "</td>\n";
				$ts = substr( $row->timestamp, 0, 10 );
				if( $ts == '0000-00-00' ) $ts = '';
				$output .= "<td>$ts</td>\n";
				$output .= "<td>\n";
				if($row->solved_username) {
					$output .= $this->msg( 'feedbackus-specialpage-solved-by' )->text() . ": <a href='$wikiurl/w/User:" . $row->solved_username . "'>" . $row->solved_username . "</a> (" . substr( $row->solved_timestamp, 0, 10 );
					$output .= ", <a class='solvedButton' href='$wikiurl/w/Special:FeedbackUs/?feedback_id=" . $row->id . "&solved=0&action=solvefeedback&detail=1&page_id=" . $page_id . "'>" . $this->msg( 'feedbackus-specialpage-mark-as-nonsolved' )->text() . ")</a>";
				}
				else {
					$output .= "<a class='solvedButton' href='$wikiurl/w/Special:FeedbackUs/?feedback_id=" . $row->id . "&solved=1&action=solvefeedback&detail=1&page_id=" . $page_id . "'>" . $this->msg( 'feedbackus-specialpage-mark-as-solved' )->text() . "</a>";
				}
				$output .= "</td>\n";
				$output .= "</tr>\n";
			}
			$output .= "</tbody>\n</table>\n";
			$out->addHTML( $output );
		}
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
