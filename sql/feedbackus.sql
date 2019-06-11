--
-- Table structure for table `feedbackus`
--

CREATE TABLE IF NOT EXISTS `feedbackus` (
  `id` int(11) unsigned NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `page_id` int(11) unsigned NOT NULL,
  `comment` text NOT NULL DEFAULT '',
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `email` varchar(255) NOT NULL DEFAULT '',
  `solved_username` varchar(255) NOT NULL DEFAULT '',
  `solved_timestamp` timestamp NULL,
  `solved_comment` varchar(255) NOT NULL DEFAULT ''
) COMMENT='Feedback';

CREATE INDEX page_id ON feedbackus(page_id);
CREATE FULLTEXT INDEX comment ON feedbackus(comment);


