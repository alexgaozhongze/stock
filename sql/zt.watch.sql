CREATE TABLE `hsab` (
  `code` mediumint(6) unsigned zerofill NOT NULL,
  `price` decimal(6,2) DEFAULT NULL,
  `up` decimal(5,2) DEFAULT NULL,
  `upp` decimal(5,2) DEFAULT NULL,
  `zt` decimal(6,2) DEFAULT NULL,
  `dt` decimal(6,2) DEFAULT NULL,
  `cjs` int(10) unsigned DEFAULT NULL,
  `cje` bigint(20) unsigned DEFAULT NULL,
  `zf` decimal(5,2) DEFAULT NULL,
  `zg` decimal(6,2) DEFAULT NULL,
  `zd` decimal(6,2) DEFAULT NULL,
  `jk` decimal(6,2) DEFAULT NULL,
  `zs` decimal(6,2) DEFAULT NULL,
  `lb` decimal(5,2) DEFAULT NULL,
  `hsl` decimal(4,2) DEFAULT NULL,
  `syl` decimal(7,2) DEFAULT NULL,
  `sjl` decimal(6,2) DEFAULT NULL,
  `date` date NOT NULL,
  `name` varchar(19) DEFAULT NULL,
  `type` char(1) NOT NULL,
  PRIMARY KEY (`code`,`type`,`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `macd` (
  `code` mediumint(6) unsigned zerofill NOT NULL,
  `kp` decimal(6,2) DEFAULT NULL,
  `sp` decimal(6,2) DEFAULT NULL,
  `zg` decimal(6,2) DEFAULT NULL,
  `zd` decimal(6,2) DEFAULT NULL,
  `cjl` int(11) unsigned DEFAULT NULL,
  `cje` int(11) unsigned DEFAULT NULL,
  `zf` decimal(6,2) DEFAULT NULL,
  `time` datetime NOT NULL,
  `type` char(1) NOT NULL,
  `dif` decimal(6,3) NOT NULL DEFAULT 0.000,
  `dea` decimal(6,3) NOT NULL DEFAULT 0.000,
  `macd` decimal(6,3) NOT NULL DEFAULT 0.000,
  `ema5` decimal(7,3) NOT NULL DEFAULT 0.000,
  `ema10` decimal(7,3) NOT NULL DEFAULT 0.000,
  `ema12` decimal(7,3) NOT NULL DEFAULT 0.000,
  `ema20` decimal(7,3) NOT NULL DEFAULT 0.000,
  `ema26` decimal(7,3) NOT NULL DEFAULT 0.000,
  `ema60` decimal(7,3) NOT NULL DEFAULT 0.000,
  PRIMARY KEY (`code`,`type`,`time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

select * from hsab where code in (
	select code from hsab where 
		`date` >= (SELECT MIN(`date`) FROM (SELECT `date` FROM `hsab` WHERE `date` <> CURDATE() GROUP BY `date` ORDER BY `date` DESC LIMIT 8) AS `t`) 
		and `price`=`zt` and `zf`<=0.89 and left(`name`, 1) not in ('N','*','S') and left(`code`, 3) not in (300,688)
) and `date` >= (SELECT MIN(`date`) FROM (SELECT `date` FROM `hsab` WHERE `date` <> CURDATE() GROUP BY `date` ORDER BY `date` DESC LIMIT 18) AS `t`);
