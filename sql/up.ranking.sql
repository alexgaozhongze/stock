SELECT `b`.* FROM (
	SELECT * FROM (
		SELECT `code`,`type`,SUM(`up`) AS `sup` FROM `hsab` WHERE `date` >= (SELECT MIN(`date`) FROM (SELECT `date` FROM `hsab` WHERE `date` <> CURDATE() GROUP BY `date` ORDER BY `date` DESC LIMIT 18) AS `t`) GROUP BY `code` ORDER BY `sup` DESC
	) AS `a` WHERE `sup` >= 18.99
) AS `a` LEFT JOIN `hsab` AS `b` ON `a`.`code`=`b`.`code` AND `b`.`date` >= (SELECT MIN(`date`) FROM (SELECT `date` FROM `hsab` WHERE `date` <> CURDATE() GROUP BY `date` ORDER BY `date` DESC LIMIT 18) AS `t`)