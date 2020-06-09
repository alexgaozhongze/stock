<?php

namespace App\Console\Commands;

use Swoole\Coroutine\Channel;
use Mix\Database\Pool\ConnectionPool;

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
        $dates = dates(18);
        $dates[] = date('Y-m-d');

        $dbPool     = context()->get('dbPool');
        $db         = $dbPool->getConnection();
        $sql        = "SELECT `code`,`type` FROM `hsab` WHERE `date`=CURDATE() AND `price` IS NOT NULL AND LEFT(`name`,1) NOT IN ('N','*','S') AND LEFT(`code`,3) NOT IN (300,688) AND `code` NOT IN (
                            SELECT `code` FROM `hsab` WHERE `price`=`zt` AND `date`>='$dates[0]' AND `date`<>'$dates[18]' GROUP BY `code`)";
        $codes_info = $db->prepare($sql)->queryAll();
        $db->release();

        $chan = new Channel();
        foreach ($codes_info as $value) {
            $params = [
                'code'          => $value['code'],
                'type'          => $value['type'],
                'start_date'    => $dates[0],
                'middle_date'   => $dates[9],
                'end_date'      => $dates[17],
                'cur_date'      => $dates[18]
            ];
            xgo([$this, 'handle'], $chan, $params);
        }

        $list = [];
        foreach ($codes_info as $date) {
            $result = $chan->pop();
            $result && $list[] = $result;
        }

        $pres = array_column($list, 'pre');
        array_multisort($pres, SORT_DESC, $list);

        var_export($list);
    }

    /**
     * 查询数据
     * @param Channel $chan
     */
    public function handle(Channel $chan, $params)
    {
        $dbPool = context()->get('dbPool');
        $db     = $dbPool->getConnection();
        $result = $db->prepare("SELECT `cjs`,`date` FROM `hsab` WHERE `code`=$params[code] AND `type`=$params[type] AND `date`>='$params[start_date]' AND `date`<='$params[end_date]'")->queryAll();
        if (!$result) $chan->push([]);

        $min = min($result);
        $max = max($result);
        
        if (strtotime($params['middle_date']) <= strtotime($min['date'])) {
            $result = $db->prepare("SELECT `cjs`,`up` FROM `hsab` WHERE `code`=$params[code] AND `type`=$params[type] AND `date`='$params[cur_date]'")->queryOne();

            if (strtotime('13:00:00') >= time()) {
                if (strtotime('11:30:00') <= time()) {
                    $time_diff = 7200;
                } else {
                    $time_diff = time() - strtotime('09:30:00');
                }
            } else {
                if (strtotime('15:00:00') <= time()) {
                    $time_diff = 14400;
                } else {
                    $time_diff = time() - strtotime('13:00:00') + 7200;
                }
            }

            $num_forecast = ceil($result['cjs'] * 14400 / $time_diff);
            
            if ($num_forecast >= $max['cjs']) {
                $chan->push([
                    'code'              => $params['code'],
                    'cjs'               => $result['cjs'],
                    'cjs_forecast'      => $num_forecast,
                    'cjs_history_max'   => $max['cjs'],
                    'up'                => $result['up'],
                    'pre'               => bcdiv($num_forecast, $max['cjs'], 2)
                ]);
            } else {
                $chan->push([]);
            }
        } else {
            $chan->push([]);
        }

        $db->release();
    }

}