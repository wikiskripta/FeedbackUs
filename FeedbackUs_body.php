<?php

use MediaWiki\MediaWikiServices;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;
use MediaWiki\Mail\UserMailer;
use MediaWiki\Mail\MailAddress;

/**
 * Special page for FeedbackUs.
 */
class FeedbackUs extends SpecialPage {
    public function __construct() {
        parent::__construct( 'FeedbackUs', 'feedbackright' );
    }

    public function execute( $param ) {
        $this->setHeaders();

        $request = $this->getRequest();
        $out = $this->getOutput();
        $user = $this->getUser();
        $services = MediaWikiServices::getInstance();
        $config = $services->getMainConfig();
        $dbr = $services->getConnectionProvider()->getReplicaDatabase();
        $dbw = $services->getConnectionProvider()->getPrimaryDatabase();

        $pageId = $request->getInt( 'page_id' );
        $action = $request->getVal( 'action', '' );
        $script = rtrim( $config->get( 'ScriptPath' ), '/' );

        if ( $pageId ) {
            $pageRow = $dbr->newSelectQueryBuilder()
                ->select( [ 'page_namespace', 'page_title' ] )
                ->from( 'page' )
                ->where( [ 'page_id' => $pageId ] )
                ->caller( __METHOD__ )
                ->fetchRow();

            if ( !$pageRow || !in_array( (int)$pageRow->page_namespace, $config->get( 'FeedbackUsNamespaces' ), true ) ) {
                return $this->outputPlainText( 'Error: forbidden namespace' );
            }
        }

        $ret = '';
        try {
        switch ( $action ) {
            case 'insertcomment':
                if ( $this->isReadOnly() ) {
                    $ret = 'Error: readonly mode';
                    break;
                }

                $comment = trim( $request->getText( 'comment' ) );
                $email = trim( $request->getText( 'email', '' ) );
                $revId = $request->getInt( 'rev_id' );

                if ( $email !== '' && !filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
                    $email = '';
                }

                if ( $comment === '' || !$revId || !$pageId ) {
                    $ret = 'Error: insert comment';
                    break;
                }

                $dbw->newInsertQueryBuilder()
                    ->insertInto( 'feedbackus' )
                    ->row( [
                        'page_id' => $pageId,
                        'rev_id' => $revId,
                        'comment' => $comment,
                        'email' => $email,
                    ] )
                    ->caller( __METHOD__ )
                    ->execute();

                $ret = 'ok';

                if ( $config->get( 'FeedbackUsOtrs' ) ) {
                    $from = $email !== '' ? $email : $config->get( 'FeedbackUsOtrsAddress' );
                    $subject = $this->msg( 'feedbackus-message-subject' )->plain();
                    $body = $this->msg( 'feedbackus-message-label' )->plain() . PHP_EOL . PHP_EOL;
                    $body .= $this->msg( 'feedbackus-message-body' )->plain() . PHP_EOL . PHP_EOL;
                    $body .= $script . '/index.php?curid=' . $pageId . PHP_EOL;
                    $body .= $script . '/index.php?title=' . SpecialPage::getTitleFor( 'FeedbackUs' )->getPrefixedText();
                    $body .= '&page_id=' . $pageId . PHP_EOL . PHP_EOL . $comment;

                    if ( !$this->sendMail( $config->get( 'FeedbackUsOtrsAddress' ), $from, $subject, $body ) ) {
                        $ret = 'Error: sending mail';
                    }
                }
                break;

            case 'insertrating':
                if ( $this->isReadOnly() ) {
                    $ret = 'Error: readonly mode';
                    break;
                }

                $score = (int)$request->getVal( 'score' );
                $revId = $request->getInt( 'rev_id' );
                $ip = $request->getIP() ?: '';

                if ( $score < 0 || $score > 5 ) {
                    $ret = 'Error: no score set';
                    break;
                }

                if ( !$pageId || !$revId ) {
                    $ret = 'Error: missing page or revision';
                    break;
                }

                $row = $dbw->selectRow(
                    'articlescores',
                    [ 'id', 'day_ips', 'last_inserted', 'scoreSum', 'usersCount' ],
                    [ 'rev_page' => $pageId, 'rev_id' => $revId ],
                    __METHOD__
                );

                $nowTs = wfTimestampNow();
                $today = substr( $nowTs, 0, 8 );

                if ( $row ) {
                    $dayIps = (string)$row->day_ips;
                    $lastInserted = preg_replace( '/[^0-9]/', '', (string)$row->last_inserted );
                    $lastDay = substr( $lastInserted, 0, 8 );

                    if ( $lastDay !== $today ) {
                        $dayIps = '';
                    }

                    $alreadyRatedToday = $ip !== '' && strpos( ',' . $dayIps . ',', ',' . $ip . ',' ) !== false;
                    if ( $alreadyRatedToday ) {
                        $ret = 'articlescores-dayips-not-today';
                        break;
                    }

                    $newDayIps = trim( $dayIps . ',' . $ip, ',' );
                    $dbw->update(
                        'articlescores',
                        [
                            'scoreSum' => (int)$row->scoreSum + $score,
                            'usersCount' => (int)$row->usersCount + 1,
                            'day_ips' => $newDayIps,
                            'last_inserted' => $dbw->timestamp( $nowTs ),
                        ],
                        [ 'id' => (int)$row->id ],
                        __METHOD__
                    );
                } else {
                    $dbw->insert(
                        'articlescores',
                        [
                            'rev_page' => $pageId,
                            'rev_id' => $revId,
                            'scoreSum' => $score,
                            'usersCount' => 1,
                            'day_ips' => $ip,
                            'last_inserted' => $dbw->timestamp( $nowTs ),
                        ],
                        __METHOD__
                    );
                }

                $ret = (string)FeedbackUsHooks::saveScore( $pageId );
                break;

            case 'solvefeedback':
                if ( $this->isReadOnly() ) {
                    $ret = 'Error: readonly mode';
                    break;
                }

                $this->checkPermissions();

                $solved = $request->getInt( 'solved' );
                $feedbackId = $request->getInt( 'feedback_id' );
                $pagerId = max( 1, $request->getInt( 'pagerid', 1 ) );
                $filter = $request->getBool( 'filter' ) ? '-archive' : '';

                if ( !in_array( $solved, [ 0, 1 ], true ) || !$feedbackId ) {
                    $ret = 'Error: Wrong input';
                    break;
                }

                $set = $solved ? [
                    'solved_username' => $user->getName(),
                    'solved_timestamp' => $dbw->timestamp(),
                ] : [
                    'solved_username' => '',
                    'solved_timestamp' => null,
                ];

                $dbw->newUpdateQueryBuilder()
                    ->update( 'feedbackus' )
                    ->set( $set )
                    ->where( [ 'id' => $feedbackId ] )
                    ->caller( __METHOD__ )
                    ->execute();

                if ( $request->getInt( 'detail' ) === 1 ) {
                    $out->redirect( SpecialPage::getTitleFor( 'FeedbackUs' )->getLocalURL( [ 'page_id' => $pageId ] ) );
                } else {
                    $out->redirect( SpecialPage::getTitleFor( 'FeedbackUs', $pagerId . $filter )->getLocalURL() );
                }
                return;
        }

        } catch ( \Throwable $e ) {
            $this->outputPlainText( 'Error: ' . $e->getMessage() );
            return;
        }

        if ( $ret !== '' ) {
            $this->outputPlainText( $ret );
            return;
        }

        if ( !$pageId ) {
            $this->checkPermissions();
            $this->renderOverview( $param, $out, $dbr, $config );
            return;
        }

        $this->renderDetail( $pageId, $out, $dbr );
    }


    private function isReadOnly(): bool {
        return MediaWikiServices::getInstance()->getReadOnlyMode()->isReadOnly();
    }

    private function renderOverview( $param, $out, $dbr, $config ): void {
        $pagerId = 1;
        $archive = false;
        if ( is_string( $param ) && preg_match( '/^[0-9]+(?:-archive)?$/', $param ) ) {
            $parts = explode( '-', $param );
            $pagerId = max( 1, (int)$parts[0] );
            $archive = isset( $parts[1] ) && $parts[1] === 'archive';
        }

        $pageCount = max( 1, (int)$config->get( 'FeedbackUsPageCount' ) );
        $cond = $archive
            ? [ $dbr->expr( 'solved_username', '!=', '' ), $dbr->expr( 'comment', '!=', '' ) ]
            : [ 'solved_username' => '', $dbr->expr( 'comment', '!=', '' ) ];

        $count = (int)$dbr->newSelectQueryBuilder()
            ->select( [ 'COUNT(*) AS cnt' ] )
            ->from( 'feedbackus' )
            ->where( $cond )
            ->caller( __METHOD__ )
            ->fetchField();

        $out->addWikiMsg( 'feedbackus-specialpage-text' );
        if ( $config->get( 'FeedbackUsOtrs' ) ) {
            $out->addHTML( '<p>' . htmlspecialchars( $this->msg( 'feedbackus-specialpage-otrsinfo' )->text(), ENT_QUOTES ) . '</p>' );
        }

        $toggleUrl = SpecialPage::getTitleFor( 'FeedbackUs', $archive ? '1' : '1-archive' )->getLocalURL();
        $output = "<div class='custom-control custom-switch mt-2'>\n";
        $output .= "<input type='checkbox' class='custom-control-input' id='solvedSwitch' onchange=\"window.location.href='" . htmlspecialchars( $toggleUrl, ENT_QUOTES ) . "'\"";
        if ( $archive ) {
            $output .= ' checked';
        }
        $output .= ">\n<label class='custom-control-label' for='solvedSwitch'>" .
            htmlspecialchars( $this->msg( 'feedbackus-specialpage-show-solved' )->text(), ENT_QUOTES ) .
            "</label>\n</div>\n";

        if ( $count > 0 ) {
            $pages = (int)ceil( $count / $pageCount );
            $output .= $this->buildPager( $pagerId, $pages, $archive );

            $rows = $dbr->newSelectQueryBuilder()
                ->select( [ 'page_id', 'comment', 'timestamp', 'email', 'id', 'solved_username', 'solved_timestamp' ] )
                ->from( 'feedbackus' )
                ->where( $cond )
                ->orderBy( 'timestamp', 'DESC' )
                ->limit( $pageCount )
                ->offset( ( $pagerId - 1 ) * $pageCount )
                ->caller( __METHOD__ )
                ->fetchResultSet();

            $output .= "<table class='table table-striped'>\n<thead><tr>";
            $output .= '<th>' . htmlspecialchars( $this->msg( 'feedbackus-specialpage-articlename' )->text(), ENT_QUOTES ) . '</th>';
            $output .= '<th>' . htmlspecialchars( $this->msg( 'feedbackus-specialpage-comments' )->text(), ENT_QUOTES ) . '</th>';
            $output .= '<th>Email</th>';
            $output .= '<th>' . htmlspecialchars( $this->msg( 'feedbackus-specialpage-timestamp' )->text(), ENT_QUOTES ) . '</th>';
            $output .= '<th></th>';
            $output .= "</tr></thead><tbody>\n";

            foreach ( $rows as $row ) {
                $title = Title::newFromID( (int)$row->page_id );
                if ( !$title ) {
                    continue;
                }

                $output .= '<tr>';
                $output .= '<td>';
                $output .= '<a href="' . htmlspecialchars( $title->getLocalURL(), ENT_QUOTES ) . '">' . htmlspecialchars( $title->getPrefixedText(), ENT_QUOTES ) . '</a><br>';
                $output .= '<a href="' . htmlspecialchars( SpecialPage::getTitleFor( 'FeedbackUs' )->getLocalURL( [ 'page_id' => (int)$row->page_id ] ), ENT_QUOTES ) . '" target="_blank" rel="noopener">detail</a>';
                $output .= '</td>';
                $output .= '<td>' . htmlspecialchars( (string)$row->comment, ENT_QUOTES ) . '</td>';
                $output .= '<td>' . htmlspecialchars( (string)$row->email, ENT_QUOTES ) . '</td>';
                $output .= '<td>' . htmlspecialchars( substr( (string)$row->timestamp, 0, 10 ), ENT_QUOTES ) . '</td>';
                $output .= '<td>';

                if ( $archive ) {
                    $userTitle = Title::makeTitle( NS_USER, (string)$row->solved_username );
                    $output .= htmlspecialchars( $this->msg( 'feedbackus-specialpage-solved-by' )->text(), ENT_QUOTES ) . ': ';
                    if ( $userTitle ) {
                        $output .= '<a href="' . htmlspecialchars( $userTitle->getLocalURL(), ENT_QUOTES ) . '">' . htmlspecialchars( (string)$row->solved_username, ENT_QUOTES ) . '</a>';
                    } else {
                        $output .= htmlspecialchars( (string)$row->solved_username, ENT_QUOTES );
                    }
                    $output .= ' (' . htmlspecialchars( substr( (string)$row->solved_timestamp, 0, 10 ), ENT_QUOTES ) . ', ';
                    $output .= '<a class="solvedButton" href="' . htmlspecialchars( SpecialPage::getTitleFor( 'FeedbackUs' )->getLocalURL( [
                        'feedback_id' => (int)$row->id,
                        'pagerid' => $pagerId,
                        'filter' => 1,
                        'solved' => 0,
                        'action' => 'solvefeedback',
                        'page_id' => (int)$row->page_id,
                    ] ), ENT_QUOTES ) . '">' . htmlspecialchars( $this->msg( 'feedbackus-specialpage-mark-as-nonsolved' )->text(), ENT_QUOTES ) . '</a>)';
                } else {
                    $output .= '<a class="solvedButton" href="' . htmlspecialchars( SpecialPage::getTitleFor( 'FeedbackUs' )->getLocalURL( [
                        'feedback_id' => (int)$row->id,
                        'pagerid' => $pagerId,
                        'solved' => 1,
                        'action' => 'solvefeedback',
                        'page_id' => (int)$row->page_id,
                    ] ), ENT_QUOTES ) . '">' . htmlspecialchars( $this->msg( 'feedbackus-specialpage-mark-as-solved' )->text(), ENT_QUOTES ) . '</a>';
                }

                $output .= '</td></tr>';
            }

            $output .= "</tbody></table>\n";
        }

        $out->addHTML( $output );
    }

    private function renderDetail( int $pageId, $out, $dbr ): void {
        $title = Title::newFromID( $pageId );
        if ( !$title ) {
            $out->addHTML( '<p>Page not found.</p>' );
            return;
        }

        $output = '<h3><a href="' . htmlspecialchars( SpecialPage::getTitleFor( 'FeedbackUs' )->getLocalURL(), ENT_QUOTES ) . '" title="HOME">&laquo;</a> ';
        $output .= '<a href="' . htmlspecialchars( $title->getLocalURL(), ENT_QUOTES ) . '">' . htmlspecialchars( $title->getPrefixedText(), ENT_QUOTES ) . '</a></h3>';
        $output .= "<table class='table table-striped'><thead><tr>";
        $output .= '<th>' . htmlspecialchars( $this->msg( 'feedbackus-specialpage-comments' )->text(), ENT_QUOTES ) . '</th>';
        $output .= '<th>Email</th>';
        $output .= '<th>' . htmlspecialchars( $this->msg( 'feedbackus-specialpage-timestamp' )->text(), ENT_QUOTES ) . '</th>';
        $output .= '<th></th>';
        $output .= "</tr></thead><tbody>\n";

        $rows = $dbr->newSelectQueryBuilder()
            ->select( [ 'comment', 'timestamp', 'email', 'id', 'solved_username', 'solved_timestamp' ] )
            ->from( 'feedbackus' )
            ->where( [ 'page_id' => $pageId ] )
            ->orderBy( 'timestamp', 'DESC' )
            ->caller( __METHOD__ )
            ->fetchResultSet();

        foreach ( $rows as $row ) {
            $output .= '<tr>';
            $output .= '<td>' . htmlspecialchars( (string)$row->comment, ENT_QUOTES ) . '</td>';
            $output .= '<td>' . htmlspecialchars( (string)$row->email, ENT_QUOTES ) . '</td>';
            $output .= '<td>' . htmlspecialchars( substr( (string)$row->timestamp, 0, 10 ), ENT_QUOTES ) . '</td>';
            $output .= '<td>';
            if ( (string)$row->solved_username !== '' ) {
                $userTitle = Title::makeTitle( NS_USER, (string)$row->solved_username );
                $output .= htmlspecialchars( $this->msg( 'feedbackus-specialpage-solved-by' )->text(), ENT_QUOTES ) . ': ';
                if ( $userTitle ) {
                    $output .= '<a href="' . htmlspecialchars( $userTitle->getLocalURL(), ENT_QUOTES ) . '">' . htmlspecialchars( (string)$row->solved_username, ENT_QUOTES ) . '</a>';
                } else {
                    $output .= htmlspecialchars( (string)$row->solved_username, ENT_QUOTES );
                }
                $output .= ' (' . htmlspecialchars( substr( (string)$row->solved_timestamp, 0, 10 ), ENT_QUOTES ) . ', ';
                $output .= '<a class="solvedButton" href="' . htmlspecialchars( SpecialPage::getTitleFor( 'FeedbackUs' )->getLocalURL( [
                    'feedback_id' => (int)$row->id,
                    'solved' => 0,
                    'action' => 'solvefeedback',
                    'detail' => 1,
                    'page_id' => $pageId,
                ] ), ENT_QUOTES ) . '">' . htmlspecialchars( $this->msg( 'feedbackus-specialpage-mark-as-nonsolved' )->text(), ENT_QUOTES ) . '</a>)';
            } else {
                $output .= '<a class="solvedButton" href="' . htmlspecialchars( SpecialPage::getTitleFor( 'FeedbackUs' )->getLocalURL( [
                    'feedback_id' => (int)$row->id,
                    'solved' => 1,
                    'action' => 'solvefeedback',
                    'detail' => 1,
                    'page_id' => $pageId,
                ] ), ENT_QUOTES ) . '">' . htmlspecialchars( $this->msg( 'feedbackus-specialpage-mark-as-solved' )->text(), ENT_QUOTES ) . '</a>';
            }
            $output .= '</td></tr>';
        }

        $output .= '</tbody></table>';
        $out->addHTML( $output );
    }

    private function buildPager( int $current, int $pages, bool $archive ): string {
        $filterSuffix = $archive ? '-archive' : '';
        $html = "<nav aria-label='pager'><ul class='pagination mt-3 ms-0'>";

        $prev = max( 1, $current - 1 );
        $next = min( $pages, $current + 1 );

        $html .= '<li class="page-item' . ( $current <= 1 ? ' disabled' : '' ) . '">';
        $html .= '<a class="page-link" href="' . htmlspecialchars( SpecialPage::getTitleFor( 'FeedbackUs', $prev . $filterSuffix )->getLocalURL(), ENT_QUOTES ) . '">' . htmlspecialchars( $this->msg( 'feedbackus-previous' )->plain(), ENT_QUOTES ) . '</a></li>';

        for ( $i = 1; $i <= $pages; $i++ ) {
            if ( $i === $current ) {
                $html .= '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
            } else {
                $html .= '<li class="page-item"><a class="page-link" href="' . htmlspecialchars( SpecialPage::getTitleFor( 'FeedbackUs', $i . $filterSuffix )->getLocalURL(), ENT_QUOTES ) . '">' . $i . '</a></li>';
            }
        }

        $html .= '<li class="page-item' . ( $current >= $pages ? ' disabled' : '' ) . '">';
        $html .= '<a class="page-link" href="' . htmlspecialchars( SpecialPage::getTitleFor( 'FeedbackUs', $next . $filterSuffix )->getLocalURL(), ENT_QUOTES ) . '">' . htmlspecialchars( $this->msg( 'feedbackus-next' )->plain(), ENT_QUOTES ) . '</a></li>';
        $html .= '</ul></nav>';

        return $html;
    }

    private function outputPlainText( string $text ): void {
        $out = $this->getOutput();
        if ( method_exists( $out, 'clearHTML' ) ) {
            $out->clearHTML();
        }
        if ( method_exists( $out, 'setArticleBodyOnly' ) ) {
            $out->setArticleBodyOnly( true );
        }
        $out->disable();

        $response = $this->getRequest()->response();
        $response->header( 'Content-Type: text/plain; charset=utf-8' );
        $response->header( 'Cache-Control: no-cache, no-store, must-revalidate' );

        echo $text;
    }

    private function sendMail( string $address, string $from, string $subject, string $body ): bool {
        $status = UserMailer::send(
            new MailAddress( $address ),
            new MailAddress( $from ),
            $subject,
            $body,
            [ 'contentType' => 'text/plain; charset=utf-8' ]
        );

        return $status->isOK();
    }
}
