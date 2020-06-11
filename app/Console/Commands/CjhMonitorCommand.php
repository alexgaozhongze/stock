<?php

namespace App\Console\Commands;

use Swoole\Coroutine\Channel;
use Mix\Database\Pool\ConnectionPool;
use Mix\Concurrent\Timer;

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
        // 持续定时
        $timer = new Timer();
        $timer->tick(19988, function () {
            if ((time() >= strtotime('09:15:00') && time() <= strtotime('11:30:00')) || (time() >= strtotime('13:00:00') && time() <= strtotime('15:00:00'))) {
                $this->timermain();
            }
        });
    }

    public function timermain()
    {
        $dates = dates(18);
        $dates[] = date('Y-m-d');

        $dbPool     = context()->get('dbPool');
        $db         = $dbPool->getConnection();
        $sql        = "SELECT `code`,`type` FROM `hsab` WHERE `date`='$dates[18]' AND `price` IS NOT NULL AND LEFT(`name`,1) NOT IN ('N','*','S') AND LEFT(`code`,3) NOT IN (300,688) AND `code` NOT IN (
                            SELECT `code` FROM `hsab` WHERE `price`=`zt` AND `date`>='$dates[0]' AND `date`<='$dates[17]' GROUP BY `code`)
                            AND IF (`price`>=58.99, CEIL(`price`)>=FlOOR(`zg`), ROUND(`price`, 1)=ROUND(`zg`, 1))";
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
        $result = $db->prepare("SELECT `cjs`,`date` FROM `hsab` WHERE `code`=$params[code] AND `type`=$params[type] AND `date`>='$params[start_date]' AND `date`<='$params[end_date]'")->queryAll();
        if (!$result) $chan->push([]);

        $min = min($result);
        $max = max($result);
        
        if (strtotime($params['middle_date']) <= strtotime($min['date'])) {
            $result = $db->prepare("SELECT `cjs`,`up`,`zf` FROM `hsab` WHERE `code`=$params[code] AND `type`=$params[type] AND `date`='$params[cur_date]'")->queryOne();

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
                $pre = bcdiv($num_forecast, $max['cjs'], 2);

                $sql = "SELECT COUNT(*) AS `count` FROM `cm` WHERE `code`=$params[code] AND `time`>=CURDATE()";
                $cm_exists = $db->prepare($sql)->queryOne();

                $chan->push([
                    'code'  => $params['code'],
                    'cjs'   => $result['cjs'],
                    'cjsf'  => $num_forecast,
                    'cjsm'  => $max['cjs'],
                    'up'    => $result['up'],
                    'zf'    => $result['zf'],
                    'pre'   => $pre,
                    'count' => $cm_exists['count']
                ]);

                $time = date('Y-m-d H:i:s');
                $sql = "INSERT INTO `cm` (`code`, `up`, `zf`, `pre`, `time`) VALUES ($params[code], $result[up], $result[zf], $pre, '$time')";
                $db->prepare($sql)->execute();

            } else {
                $chan->push([]);
            }
        } else {
            $chan->push([]);
        }

        $db->release();
    }

}