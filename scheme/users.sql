CREATE TABLE /*_*/bitid_users (
  `uoi_bitid` varbinary(255) NOT NULL,
  `uoi_user` int(5) unsigned NOT NULL,
  `uoi_user_registration` binary(14) DEFAULT NULL,
  PRIMARY KEY (`uoi_bitid`),
  KEY `user_bitid_user` (`uoi_user`)
);
