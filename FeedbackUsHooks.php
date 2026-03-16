<?php

use MediaWiki\MediaWikiServices;
use MediaWiki\SpecialPage\SpecialPage;

/**
 * Hook handlers for FeedbackUs.
 */
class FeedbackUsHooks {

    /**
     * Adds feedback widget to eligible article pages.
     */
    public static function activateFB( &$out, &$skin ) {
        if ( !$out->isArticle() ) {
            return true;
        }

        $title = $out->getTitle();
        if ( !$title || $title->isMainPage() || $skin->getSkinName() !== 'medik' ) {
            return true;
        }

        $config = MediaWikiServices::getInstance()->getMainConfig();
        $allowedNamespaces = $config->get( 'FeedbackUsNamespaces' );
        if ( !in_array( $title->getNamespace(), $allowedNamespaces, true ) ) {
            return true;
        }

        $wikiPage = $out->getWikiPage();
        if ( !$wikiPage ) {
            return true;
        }

        $pageId = $wikiPage->getId();
        $revId = (int)$out->getRevisionId();
        if ( !$pageId || !$revId ) {
            return true;
        }

        $rating = self::getRating( $pageId );
        $services = MediaWikiServices::getInstance();
        $config = $services->getMainConfig();
        $assetBase = rtrim( $config->get( 'ScriptPath' ), '/' ) . '/extensions/FeedbackUs/resources/img';

        $out->addModules( 'ext.FeedbackUs' );

        $modal = "<div id='fbuModal' class='modal fade' tabindex='-1' role='dialog' aria-hidden='true'" .
            " data-rating='" . (int)$rating . "' data-revid='" . $revId . "' data-pageid='" . (int)$pageId . "'>\n";
        $modal .= "<div class='modal-dialog modal-md'>\n";
        $modal .= "<div class='modal-content'>\n";
        $modal .= "<div class='modal-header'>\n";
        $modal .= "<div class='ratingBar'>\n";

        for ( $i = 1; $i <= 5; $i++ ) {
            $icon = $i <= $rating ? 'orange' : 'white';
            $modal .= "<span data-rating='{$i}' class='asStar'><img src='{$assetBase}/star_{$icon}.png' alt='score-{$i}'></span>\n";
        }

        $modal .= "</div>\n";
        $modal .= "<button type='button' class='close' data-bs-dismiss='modal' aria-label='Close'>\n";
        $modal .= "<span aria-hidden='true'>&times;</span>\n";
        $modal .= "</button>\n";
        $modal .= "</div>\n";

        $modal .= "<div class='modal-body'>\n<form>\n";
        $modal .= "<div class='form-group'>\n";
        $modal .= "<textarea id='FeedbackUsComment' class='form-control' placeholder='" .
            htmlspecialchars( wfMessage( 'feedbackus-message-label' )->text(), ENT_QUOTES ) .
            "' required></textarea>\n";
        $modal .= "</div>\n";
        $modal .= "<div class='form-group'>\n";
        $modal .= "<input type='email' id='FeedbackUsEmail' class='form-control' placeholder='" .
            htmlspecialchars( wfMessage( 'feedbackus-email-label' )->text(), ENT_QUOTES ) .
            "'>\n";
        $modal .= "</div>\n";
        $modal .= "<button id='modalSubmitButton' class='btn btn-primary mt-3'>" .
            htmlspecialchars( wfMessage( 'feedbackus-send-button' )->text(), ENT_QUOTES ) .
            "</button>\n";

        $modal .= "<div id='fbSuccess' class='alert alert-success d-none mt-3' role='alert'>\n" .
            htmlspecialchars( wfMessage( 'feedbackus-thanks' )->text(), ENT_QUOTES ) . "\n</div>\n";
        $modal .= "<div id='fbError' class='alert alert-danger d-none mt-3' role='alert'></div>\n";
        $modal .= "<div id='asSuccess' class='alert alert-success d-none mt-3' role='alert'>\n" .
            htmlspecialchars( wfMessage( 'articlescores-success' )->text(), ENT_QUOTES ) . "\n</div>\n";
        $modal .= "<div id='asError' class='alert alert-danger d-none mt-3' role='alert'>\n" .
            htmlspecialchars( wfMessage( 'articlescores-one-per-day' )->text(), ENT_QUOTES ) . "\n</div>\n";
        $modal .= "</form>\n</div>\n";
        $modal .= "</div>\n</div>\n</div>\n";

        $out->prependHTML( $modal );
        return true;
    }

    /**
     * Returns current stored page rating.
     */
    public static function getRating( int $pageId ): int {
        $dbr = MediaWikiServices::getInstance()->getConnectionProvider()->getReplicaDatabase();
        $row = $dbr->newSelectQueryBuilder()
            ->select( [ 'stars' ] )
            ->from( 'articlescores_sum' )
            ->where( [ 'page_id' => $pageId ] )
            ->caller( __METHOD__ )
            ->fetchRow();

        return $row ? (int)$row->stars : 0;
    }

    /**
     * Recomputes and stores page score using only existing rating rows.
     * This avoids heavy reads on page view and only runs after rating changes.
     */
    public static function saveScore( int $pageId ) {
        $dbw = MediaWikiServices::getInstance()->getConnectionProvider()->getPrimaryDatabase();

        $res = $dbw->select(
            'articlescores',
            [ 'rev_id', 'scoreSum', 'usersCount' ],
            [ 'rev_page' => $pageId ],
            __METHOD__,
            [ 'ORDER BY' => 'rev_id DESC' ]
        );

        $scoreWeightedSum = 0.0;
        $weightSum = 0.0;
        $reviewerCount = 0;
        $e = 0;

        foreach ( $res as $row ) {
            if ( !(int)$row->usersCount ) {
                continue;
            }

            $weight = pow( 0.8, $e );
            if ( $weight <= 0.0001 ) {
                break;
            }

            $revScore = (float)$row->scoreSum / (int)$row->usersCount;
            $scoreWeightedSum += $revScore * $weight;
            $weightSum += $weight;
            $reviewerCount += (int)$row->usersCount;
            $e++;
        }

        $newScore = $weightSum > 0 ? $scoreWeightedSum / $weightSum : 0.0;
        $stars = $weightSum > 0 ? (int)floor( 0.5 + $newScore ) : 0;

        $data = [
            'page_id' => $pageId,
            'score' => $newScore,
            'stars' => $stars,
            'usersCount' => $reviewerCount,
        ];

        $dbw->replace(
            'articlescores_sum',
            [ 'page_id' ],
            $data,
            __METHOD__
        );

        return $stars;
    }

    /**
     * Creates database tables.
     */
    public static function FeedbackUsUpdateSchema( $updater ) {
        if ( $updater->getDB()->getType() !== 'mysql' ) {
            return false;
        }

        $updater->addExtensionUpdate( [ 'addTable', 'feedbackus', __DIR__ . '/sql/feedbackus.sql', true ] );
        $updater->addExtensionUpdate( [ 'addTable', 'articlescores', __DIR__ . '/sql/articlescores.sql', true ] );
        $updater->addExtensionUpdate( [ 'addTable', 'articlescores_sum', __DIR__ . '/sql/articlescores_sum.sql', true ] );
        return true;
    }
}
