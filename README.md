# FeedbackUs

Mediawiki extension.

## Description

* Version 2.1
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

* Make sure you have MediaWiki 1.36+ installed.
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

Edit config section of _extension.json_.

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

### 2.1

* "The constant DB_SLAVE/MASTER deprecated in 1.28. Use DB_REPLICA/PRIMARY instead.

#### FeedbackUs special page

* User can mark an item as "solved".
* "Solving" an item triggers sending info to OTRS.
* Solved items contain username and timestamp.

#### ArticleScores special page

* Options: rating, number of article's reviewers (with given rating), number of articles displayed

## Authors and license

* [Josef Martiňák](https://www.wikiskripta.eu/w/User:Josmart), [Petr Kajzar](https://www.wikiskripta.eu/w/User:Slepi)
* MIT License, Copyright (c) 2023 First Faculty of Medicine, Charles University
