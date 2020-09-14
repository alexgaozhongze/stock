-- zt.watch
select * from hsab where code in (
	select code from hsab where 
		`date` >= (SELECT MIN(`date`) FROM (SELECT `date` FROM `hsab` WHERE `date` <> CURDATE() GROUP BY `date` ORDER BY `date` DESC LIMIT 8) AS `t`) 
		and `price`=`zt` and `zf`<=0.89 and left(`name`, 1) not in ('N','*','S') and left(`code`, 3) not in (300,688)
) and `date` >= (SELECT MIN(`date`) FROM (SELECT `date` FROM `hsab` WHERE `date` <> CURDATE() GROUP BY `date` ORDER BY `date` DESC LIMIT 18) AS `t`);

-- xdr.watch
select * from hsab where left(name, 1) in ('X','D','R') and price = zt

SELECT h.code ,h.price ,h.up ,h.cje ,h.zf ,h.name ,m.*FROM hsab h 
	inner join macd m on h.code = m.code and h.`type` = m.`type` and h.zt = m.zg and m.`time` >= CURDATE() and m.cje >= 100000000
	where h.`date` = CURDATE() 
	ORDER BY m.`time` 