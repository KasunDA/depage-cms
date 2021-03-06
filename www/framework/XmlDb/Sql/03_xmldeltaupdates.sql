/*
    XmlDb XML Delta Updates Table
    -----------------------------------

    @tablename _xmldeltaupdates
    @connection _xmldocs
    @version 1.5.0-beta.1
*/
CREATE TABLE `_xmldeltaupdates` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `node_id` int(11) DEFAULT NULL,
  `doc_id` int(11) unsigned NOT NULL,
  `depth` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `doc_id` (`doc_id`),
  CONSTRAINT `_xmldeltaupdates_ibfk_2` FOREIGN KEY (`doc_id`) REFERENCES `_xmldocs` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4;
