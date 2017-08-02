<?php

/**
 * Config file
 * @ingroup Extensions
 * @author Josef Martiňák
 * @license MIT
 * @version 1.0
 * @file
*/

# Allowed Namespaces - http://www.mediawiki.org/wiki/Manual:Namespace
define( 'FU_NAMESPACES', '0,100,102' );	// 0-main, 2-User, 12-Help ...

# Number of diplayed comments showed on Special page
define( 'FU_PAGE_COUNT', 15 );

# If set (=1), comments with given sender email is sent to OTRS
define( 'FU_SEND_TO_OTRS', 0 );
define( 'FU_OTRS_ADDRESS', '???????' );


# Number of scored articles showed on Special:FeedbackUs
define('AS_SPECIAL_ITEMS', 100);
  