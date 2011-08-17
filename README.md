#HighChat
81.laのために作られたチャット．81chat．81->High．

##テーブル構成例

###DB_LOG_TABLE
    CREATE TABLE IF NOT EXISTS `testchat` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `date` int(11) NOT NULL,
      `name` text COLLATE utf8_unicode_ci NOT NULL,
      `comment` text COLLATE utf8_unicode_ci NOT NULL,
      `ip` int(11) unsigned NOT NULL,
      PRIMARY KEY (`id`)
    ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

###DB_USER_TABLE
    CREATE TABLE IF NOT EXISTS `testchatuser` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `name` text COLLATE utf8_unicode_ci NOT NULL,
      `last` int(11) NOT NULL,
      `ip` int(11) unsigned NOT NULL,
      `ua` text COLLATE utf8_unicode_ci NOT NULL,
       PRIMARY KEY (`id`)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci ;

###DB_ROM_TABLE
    CREATE TABLE IF NOT EXISTS `testchatrom` (
      `ip` int(10) unsigned NOT NULL,
      `ua` varbinary(1000) NOT NULL,
      `last` int(11) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=binary;

なんでInnoDBとMyISAMに分かれてるかは知らね
