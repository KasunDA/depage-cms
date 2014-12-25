/*
    XmlDb Nodetypes Table
    -----------------------------------

    @tablename _proj_PROJECTNAME_xmlnodetypes
    @version 1.5.0-beta.1
*/
CREATE TABLE `_proj_PROJECTNAME_xmlnodetypes` (
  `nodetypeId` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pos` int(10) unsigned NOT NULL,
  `nodename` varchar(255) NOT NULL DEFAULT '',
  `name` varchar(255) NOT NULL DEFAULT '',
  `newname` varchar(255) NOT NULL DEFAULT '',
  `validparents` varchar(255) NOT NULL DEFAULT '',
  `icon` varchar(255) NOT NULL DEFAULT '',
  `xmltemplate` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`nodetypeId`)
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4;
