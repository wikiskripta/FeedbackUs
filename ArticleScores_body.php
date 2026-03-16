<?php

use MediaWiki\MediaWikiServices;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;

/**
 * Special page for article scores.
 */
class ArticleScores extends SpecialPage {
    public function __construct() {
        parent::__construct( 'ArticleScores' );
    }

    public function execute( $param ) {
        $this->setHeaders();

        $request = $this->getRequest();
        $out = $this->getOutput();
        $config = MediaWikiServices::getInstance()->getMainConfig();
        $dbr = MediaWikiServices::getInstance()->getConnectionProvider()->getReplicaDatabase();

        $defaultItems = (int)$config->get( 'FeedbackUsArticleScoresDefaultItemsCount' );
        $defaultFrom = (int)$config->get( 'FeedbackUsArticleScoresDefaultReviewersCountFrom' );
        $defaultTo = (int)$config->get( 'FeedbackUsArticleScoresDefaultReviewersCountTo' );

        $filterRating = max( 1, min( 5, $request->getInt( 'filterRating', 5 ) ) );
        $filterReviewersFrom = max( 1, $request->getInt( 'filterReviewersFROM', $defaultFrom ) );
        $filterReviewersTo = max( 0, $request->getInt( 'filterReviewersTO', $defaultTo ) );
        $filterItemsNo = max( 0, $request->getInt( 'filterItemsNo', $defaultItems ) );

        $info = str_replace( '#ITEMS', (string)$defaultItems, $this->msg( 'articlescores-sp-info' )->text() );
        $out->addHTML( '<p>' . htmlspecialchars( $info, ENT_QUOTES ) . '</p>' );

        $output = "<style>
#ascoresMenu{display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;margin-bottom:1rem;max-width:100%;}
#ascoresMenu .ascores-field{display:flex;flex-direction:column;min-width:140px;max-width:100%;}
#ascoresMenu label{margin-bottom:0.25rem;}
#ascoresMenu select{max-width:100%;}
.ascores-table-wrap{max-width:100%;overflow-x:auto;}
.ascores-table{width:100%;table-layout:fixed;}
.ascores-table td,.ascores-table th{overflow-wrap:anywhere;word-break:break-word;}
.ascores-table td:first-child,.ascores-table th:first-child{width:60%;}
</style>\n";
        $output .= "<form id='ascoresMenu' method='get' action=''>\n";
        $output .= $this->buildSelect( 'filterRating', 'articlescores-rating', range( 1, 5 ), $filterRating );
        $output .= $this->buildSelect( 'filterReviewersFROM', 'articlescores-ratingsNo-from', range( 1, 100 ), $filterReviewersFrom );

        $reviewersToOptions = [ 0 => $this->msg( 'articlescores-unlimited' )->text() ];
        for ( $i = 1; $i <= 100; $i++ ) {
            $reviewersToOptions[$i] = (string)$i;
        }
        $output .= $this->buildSelect( 'filterReviewersTO', 'articlescores-ratingsNo-to', $reviewersToOptions, $filterReviewersTo );

        $itemsOptions = [ 0 => $this->msg( 'articlescores-unlimited' )->text() ];
        for ( $i = 50; $i <= 2000; $i += 50 ) {
            $itemsOptions[$i] = (string)$i;
        }
        $output .= $this->buildSelect( 'filterItemsNo', 'articlescores-itemsNo', $itemsOptions, $filterItemsNo );
        $output .= "<div class='ascores-field'><button type='submit' class='btn btn-primary'>" .
            htmlspecialchars( $this->msg( 'feedbackus-send-button' )->text(), ENT_QUOTES ) .
            "</button></div>\n";
        $output .= "</form>\n";

        $conditions = [
            $dbr->expr( 'score', '>=', $filterRating - 0.5 ),
            $dbr->expr( 'score', '<=', $filterRating + 0.49 ),
            $dbr->expr( 'usersCount', '>=', $filterReviewersFrom ),
        ];
        if ( $filterReviewersTo > 0 ) {
            $conditions[] = $dbr->expr( 'usersCount', '<=', $filterReviewersTo );
        }

        $query = $dbr->newSelectQueryBuilder()
            ->select( [ 'page_id', 'score', 'usersCount' ] )
            ->from( 'articlescores_sum' )
            ->where( $conditions )
            ->orderBy( 'score', 'DESC' )
            ->caller( __METHOD__ );

        if ( $filterItemsNo > 0 ) {
            $query->limit( $filterItemsNo );
        }

        $rows = $query->fetchResultSet();

        $output .= "<div class='ascores-table-wrap'><table class='table table-striped mt-4 ascores-table'><thead><tr>";
        $output .= '<th>' . htmlspecialchars( $this->msg( 'articlescores-page' )->text(), ENT_QUOTES ) . '</th>';
        $output .= '<th>' . htmlspecialchars( $this->msg( 'articlescores-score' )->text(), ENT_QUOTES ) . '</th>';
        $output .= '<th>' . htmlspecialchars( $this->msg( 'articlescores-ratingsNo' )->text(), ENT_QUOTES ) . '</th>';
        $output .= "</tr></thead><tbody>\n";

        $allowedNamespaces = $config->get( 'FeedbackUsNamespaces' );
        foreach ( $rows as $row ) {
            $title = Title::newFromID( (int)$row->page_id );
            if ( !$title || !in_array( $title->getNamespace(), $allowedNamespaces, true ) ) {
                continue;
            }

            $output .= '<tr>';
            $output .= '<td><a href="' . htmlspecialchars( $title->getLocalURL(), ENT_QUOTES ) . '">' . htmlspecialchars( $title->getPrefixedText(), ENT_QUOTES ) . '</a></td>';
            $output .= '<td>' . htmlspecialchars( str_replace( '.', ',', (string)round( (float)$row->score, 2 ) ), ENT_QUOTES ) . '</td>';
            $output .= '<td>' . (int)$row->usersCount . '</td>';
            $output .= '</tr>';
        }

        $output .= '</tbody></table></div>';
        $out->addHTML( $output );
    }

    private function buildSelect( string $name, string $labelMessage, array $options, int $selected ): string {
        $html = "<div class='ascores-field'>\n";
        $html .= '<label for="' . htmlspecialchars( $name, ENT_QUOTES ) . '">' . htmlspecialchars( $this->msg( $labelMessage )->text(), ENT_QUOTES ) . '</label>';
        $html .= '<select name="' . htmlspecialchars( $name, ENT_QUOTES ) . '" class="form-select">';

        foreach ( $options as $value => $label ) {
            if ( is_int( $value ) && is_int( $label ) ) {
                $value = $label;
            }
            $html .= '<option value="' . htmlspecialchars( (string)$value, ENT_QUOTES ) . '"';
            if ( (int)$value === $selected ) {
                $html .= ' selected';
            }
            $html .= '>' . htmlspecialchars( (string)$label, ENT_QUOTES ) . '</option>';
        }

        $html .= '</select></div>';
        return $html;
    }
}
