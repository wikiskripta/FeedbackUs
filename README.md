# FeedbackUs

Mediawiki extension.

## Description

* Version 1.2
* Extension gives feedback regarding the articles
    * User can send messages to wiki sysops.
	* User can see and change article's rating.
* Special page available only for sysops, can show the list and uncheck the corrected articles

## SpecialPage

SpecialPage:FeedbackUs diplays commented articles.
SpecialPage:ArticleScores diplays a chart of reviewed articles

## Installation

* Make sure you have MediaWiki 1.29+ installed.
* Download and place the extension to your /extensions/ folder.
* Add the following code to your LocalSettings.php: 

``` php
wfLoadExtension( 'FeedbackUs' );
$wgGroupPermissions['*']['feedbackus'] = false;
$wgGroupPermissions['sysop']['feedbackus'] = true;
```

* Run `maintenance/update.php`, _feedbackus_, _articlescores_ and _articlescores_sum_ tables will be added (if not exist).

## Configuration

Edit config section of _extension.json_:
__"namespaces":__
* Numbers of namespaces we want to give this kind of feedback, separated by comma.
* For example [0,2,12] allows Main, User, Help namespaces.
* Numbers of built in namespaces can be found at [Manual:Namespace](https://www.mediawiki.org/wiki/Manual:Namespace).
__"pageCount":__
* Pager. Default 50 comments on page.
__"sendToOtrs":__
* If set (=1), comments are sent to email address in __"otrsAddress"__.
__"otrsAddress":__
* OTRS email address
__"articleScoresItemsCount":__
* Pager. Number of scored articles showed at Special:FeedbackUs.

## Internationalization

This extension is available in English and Czech language. For other languages, just edit files in /i18n/ folder.

## RELEASE NOTES

### 1.1

* Manifest version 2
* MW 1.29+
* Config moved to _extensions.json_

### 1.2

* Bootstrap modals
* Magic box removed

## Authors and license

* [Josef Martiňák](https://www.wikiskripta.eu/w/User:Josmart)
* MIT License, Copyright (c) 2019 First Faculty of Medicine, Charles University
