--
-- Table structure for table `articlescores`
--

CREATE TABLE IF NOT EXISTS `articlescores` (
  `id` int(11) unsigned NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `rev_id` int(11) NOT NULL,
  `rev_page` int(11) unsigned NOT NULL,
  `scoreSum` int(11) NOT NULL DEFAULT '0',
  `usersCount` int(11) NOT NULL DEFAULT '0',
  `day_ips` text NOT NULL,
  `last_inserted` binary(19) NOT NULL
) COMMENT='revisions scoring';

CREATE INDEX rev_page ON articlescores(rev_page);
CREATE INDEX rev_id ON articlescores(rev_id);
CREATE INDEX scoreSum ON articlescores(scoreSum);
CREATE INDEX usersCount ON articlescores(usersCount);
CREATE INDEX last_inserted ON articlescores(last_inserted);