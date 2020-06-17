<?php

namespace App\Console\Commands;

use Swoole\Coroutine\Channel;

/**
 * Class CjhMonitorCommand
 * @package App\Console\Commands
 * @author alex <alexgaozhongze@gmail.com>
 */
class CjhMonitorCommand
{

    /**
     * 主函数
     */
    public function main()
    {
        $days = 59;
        $dates = dates($days);
        $dates[] = date('Y-m-d');

        $dbPool     = context()->get('dbPool');
        $db         = $dbPool->getConnection();
        $sql        = "SELECT `code`,`type` FROM `hsab` WHERE `date`='$dates[$days]' AND `price` IS NOT NULL AND LEFT(`name`,1) NOT IN ('N','*','S') AND LEFT(`code`,3) NOT IN (300,688)";
        $codes_info = $db->prepare($sql)->queryAll();
        $db->release();

        $chan = new Channel();
        foreach ($codes_info as $value) {
            $params = [
                'code'          => $value['code'],
                'type'          => $value['type'],
                'start_date'    => $dates[0],
                'end_date'      => $dates[$days - 1],
                'cur_date'      => $dates[$days],
                'days'          => $days
            ];
            xgo([$this, 'handle'], $chan, $params);
        }

        $list = [];
        foreach ($codes_info as $date) {
            $result = $chan->pop();
            $result && $list[] = $result;
        }

        $sort = array_column($list, 'up');
        array_multisort($sort, SORT_ASC, $list);

        shellPrint($list);
    }

    /**
     * 查询数据
     * @param Channel $chan
     */
    public function handle(Channel $chan, $params)
    {
        $dbPool = context()->get('dbPool');
        $db     = $dbPool->getConnection();
        $result = $db->prepare("SELECT `cjs`,`date` FROM `hsab` WHERE `code`=$params[code] AND `type`=$params[type] AND `date`>='$params[start_date]' AND `date`<='$params[end_date]' AND `price` IS NOT NULL")->queryAll();
        if (!$result) $chan->push([]);

        $min = min($result);
        $max = max($result);
        
        if ($params['days'] == count($result) && $params['end_date'] == $min['date']) {
            $result = $db->prepare("SELECT `code`,`up` FROM `hsab` WHERE `code`=$params[code] AND `type`=$params[type] AND `date`='$params[cur_date]'")->queryOne();
            $chan->push($result);
        } else {
            $chan->push([]);
        }

        $db->release();
    }

}