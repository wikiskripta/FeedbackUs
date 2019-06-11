# FeedbackUs

Mediawiki extension.

## Description

* Version 2.0
* Extension gives feedback regarding the articles
    * User can send messages to wiki sysops.
	* User can see and change article's rating.
* Special pages with feedback.
* Feedback popup available only for "Medik" skin (can be changed at the beginning of FeedbackUsHooks:activateFB()).

## SpecialPage

### SpecialPage:FeedbackUs

* List of commented articles.
* Only sysops can access.
* User can mark an item as "solved".
* Solved items contain username and timestamp.

### SpecialPage:ArticleScores

* Chart of reviewed articles
* Options: rating, number of article's reviewers (with given rating)

## Installation

* Make sure you have MediaWiki 1.29+ installed.
* "Medik" skin selected.
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
__"articleScoresDefaultItemsCount":__
* Number of scored articles showed at Special:ArticleScore.

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

### 2.0

* Checkboxes removed
* Stars' rating instead of dropdown.
* Direct link in the OTRS message. Feedback item has a detail page now.
* Message can be edited at _Mediawiki:feedbackus-message-subject_ and Mediawiki:feedbackus-message-body.

#### FeedbackUs special page

* User can mark an item as "solved".
* "Solving" an item triggers sending info to OTRS.
* Solved items contain username and timestamp.

#### ArticleScores special page

* Options: rating, number of article's reviewers (with given rating), number of articles displayed

## Authors and license

* [Josef Martiňák](https://www.wikiskripta.eu/w/User:Josmart)
* MIT License, Copyright (c) 2019 First Faculty of Medicine, Charles University
