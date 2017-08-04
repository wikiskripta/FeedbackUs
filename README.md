# FeedbackUs

Mediawiki extension.

## Description

* Version 1.0
* Extension gives feedback regarding the articles
    * User can send messages to wiki sysops.
	* User can see and change article's rating.
* Special page available only for sysops, can show the list and uncheck the corrected articles
* {{#feedme:width|height}} ... inserting message box to a page
* Former ArticleScores and Blackdot extensions are joined into this one. 
    * _articlescores_ and _articlescores_sum_ tables are still in use. 
    * _blackdot_ table should be deleted after initial import `INSERT INTO feedbackus(page_id,comment,timestamp) SELECT page_id,comment,last_comment_timestamp FROM blackdot ORDER BY id;`


## SpecialPage

SpecialPage:FeedbackUs diplays commented articles.
SpecialPage:ArticleScores diplays a chart of reviewed articles


## Installation

* Make sure you have MediaWiki 1.28+ installed.
* Download and place the extension's folder to your /extensions/ folder.
* Add the following code to your LocalSettings.php: 
```
wfLoadExtension( 'FeedbackUs' );
$wgGroupPermissions['*']['feedbackus'] = false;
$wgGroupPermissions['user']['feedbackus'] = false;
$wgGroupPermissions['sysop']['feedbackus'] = true;
```
* Run `maintenance/update.php`, _feedbackus_, _articlescores_ and _articlescores_sum_ tables will be added.


## Configuration

Open _config.php_ and set following constants:

* FU-NAMESPACES - numbers of namespaces we want to give this kind of feedback, separated by comma
    * For example define('FU-NAMESPACES', '0,2,12'); allows Main, User, Help namespaces.
    * Numbers of built in namespaces can be found at http://www.mediawiki.org/wiki/Manual:Namespace.
    * Default is 0 = Main namespace.
* FU-PAGE_COUNT - pager. Default 50 comments on page
* FU-SEND_TO_OTRS. If set (=1), comments from magic box with given sender email are sent to email address in FU-OTRS-ADDRESS


## Internationalization

This extension is available in English and Czech language. For other languages, just edit files in /i18n/ folder.


## Authors and license

* [Josef Martiňák](https://bitbucket.org/josmart/)
* MIT License, Copyright (c) 2017 First Faculty of Medicine, Charles University
