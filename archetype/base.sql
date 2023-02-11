CREATE TABLE `_resources`
(
    `id`       int          NOT NULL AUTO_INCREMENT,
    `resource` varchar(255) NOT NULL,
    `locked`   tinyint(1) DEFAULT '1',
    `rowcount` bigint       NOT NULL DEFAULT '0',
    PRIMARY KEY (`id`),
    UNIQUE KEY `i_res1` (`resource`)
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8 ROW_FORMAT=COMPRESSED COMMENT='Resource list';