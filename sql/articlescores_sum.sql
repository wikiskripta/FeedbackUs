--
-- Table structure for table `articlescores_sum`
--

CREATE TABLE IF NOT EXISTS `articlescores_sum` (
  `page_id` int(11) unsigned NOT NULL PRIMARY KEY DEFAULT '0',
  `score` float NOT NULL DEFAULT '0',
  `stars` tinyint(4) NOT NULL DEFAULT '0',
  `usersCount` int(11) unsigned NOT NULL
) COMMENT='final score';

CREATE INDEX page_id ON articlescores_sum(page_id);
CREATE INDEX score ON articlescores_sum(score);
CREATE INDEX stars ON articlescores_sum(stars);
CREATE INDEX usersCount ON articlescores_sum(usersCount);