<?php

namespace App\Console\Commands;

use Mix\Console\CommandLine\Flag;
use Swoole\Coroutine\Channel;

/**
 * Class GoBeyondCommand
 * @package App\Console\Commands
 * @author alex <alexgaozhongze@gmail.com>
 */
class GoBeyondCommand
{

    /**
     * 主函数
     */
    public function main()
    {
        $method = Flag::string(['v', 'version'], 'one');
        call_user_func([$this, $method]);
    }

    /**
     * 获取连续涨停code
     */
    public function one()
    {
        $codes_info = $this->getCode();

        $chan = new Channel();
        foreach ($codes_info as $value) {
            $params = [
                'code'          => $value['code'],
                'type'          => $value['type']
            ];
            xgo([$this, 'handleOne'], $chan, $params);
        }

        $list = [];
        foreach ($codes_info as $date) {
            $result = $chan->pop();
            $list[] = $result;
        }

        $sort = array_column($list, 'count');
        array_multisort($sort, SORT_ASC, $list);

        shellPrint($list);
    }

    /**
     * 查询数据
     * @param Channel $chan
     * @param array $params
     */
    public function handleOne(Channel $chan, $params)
    {
        $dbPool = context()->get('dbPool');
        $db     = $dbPool->getConnection();
        $result = $db->prepare("SELECT `price`,`zt` FROM `hsab` WHERE `code`=$params[code] AND `type`=$params[type] AND `price` IS NOT NULL")->queryAll();

        $response = [];
        $count = 0;
        foreach ($result as $value) {
            if ($value['price'] == $value['zt']) {
                $count ++;
            }
        }

        $response = [
            'code' => $params['code'],
            'count' => $count
        ];

        $chan->push($response);
        $db->release();
    }

    /**
     * 获取三日连跌code
     */
    public function two()
    {
        $codes_info = $this->getCode();

        $chan = new Channel();
        foreach ($codes_info as $value) {
            $params = [
                'code'          => $value['code'],
                'type'          => $value['type']
            ];
            xgo([$this, 'handleTwo'], $chan, $params);
        }

        $list = [];
        foreach ($codes_info as $date) {
            $result = $chan->pop();
            $list[] = $result;
        }

        $sort = array_column($list, 'date');
        array_multisort($sort, SORT_DESC, $list);

        shellPrint($list);
    }

    /**
     * 查询数据
     * @param Channel $chan
     * @param array $params
     */
    public function handleTwo(Channel $chan, $params)
    {
        $dbPool = context()->get('dbPool');
        $db     = $dbPool->getConnection();
        $result = $db->prepare("SELECT `price`,`up`,`date` FROM `hsab` WHERE `code`=$params[code] AND `type`=$params[type] AND `price` IS NOT NULL ORDER BY `date` DESC")->queryAll();

        $response = [];
        $continue = 0;
        $count = 0;
        $down_continue = 0;
        foreach ($result as $value) {
            if ($value['up'] <= 0) {
                $continue ++;
                2 <= $continue && $down_continue ++;
                if (3 == $continue) {
                    break;
                }
            } else {
                $continue = 0;
            }
            $count ++;
        }

        $current_price = reset($result)['price'];
        $start_price = $value['price'];

        $response = [
            'code'      => $params['code'],
            'dpre'      => bcdiv($down_continue, $count, 2),
            'upre'      => bcdiv($current_price, $start_price, 2),
            'date'      => $value['date']
        ];

        $chan->push($response);
        $db->release();
    }

    private function getCode()
    {
        $dbPool = context()->get('dbPool');
        $db     = $dbPool->getConnection();
        $sql    = "SELECT `code`,`type` FROM `hsab` WHERE `date`=CURDATE() AND `price` IS NOT NULL AND LEFT(`name`,1) NOT IN ('*','S') AND LEFT(`code`,3) NOT IN (300,688) AND `code` NOT IN (SELECT `code` FROM `hsab` WHERE `date` >= (SELECT MIN(`date`) FROM (SELECT `date` FROM `hsab` WHERE `date` <> CURDATE() GROUP BY `date` ORDER BY `date` DESC LIMIT 99) AS `t`) AND LEFT(`name`,1)='N' GROUP BY `code`)";
        $codes_info = $db->prepare($sql)->queryAll();
        $db->release();

        return $codes_info;
    }

}