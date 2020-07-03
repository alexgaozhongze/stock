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
     * 涨停天数
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
        foreach ($codes_info as $code) {
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
     * 三日连跌后续
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
        foreach ($codes_info as $code) {
            $result = $chan->pop();
            $result && $list[] = $result;
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
        $drpre = bcdiv($down_continue, $count, 2);

        if (0.09 >= $drpre) {
            $response = [
                'code'  => $params['code'],
                'dpre'  => $drpre,
                'upre'  => bcdiv($current_price, $start_price, 2),
                'pirce' => $current_price,
                'date'  => $value['date']
            ];
    
            $chan->push($response);
        } else {
            $chan->push([]);
        }

        $db->release();
    }

    /**
     * 六日连涨后续
     */
    public function three()
    {
        $codes_info = $this->getCode();

        $chan = new Channel();
        foreach ($codes_info as $value) {
            $params = [
                'code'          => $value['code'],
                'type'          => $value['type']
            ];
            xgo([$this, 'handleThree'], $chan, $params);
        }

        $list = [];
        foreach ($codes_info as $code) {
            $result = $chan->pop();
            $result && $list[] = $result;
        }

        $sort = array_column($list, 'date');
        array_multisort($sort, SORT_ASC, $list);

        shellPrint($list);
    }

    /**
     * 查询数据
     * @param Channel $chan
     * @param array $params
     */
    public function handleThree(Channel $chan, $params)
    {
        $dbPool = context()->get('dbPool');
        $db     = $dbPool->getConnection();
        $result = $db->prepare("SELECT `price`,`up`,`zt`,`zg`,`date` FROM `hsab` WHERE `code`=$params[code] AND `type`=$params[type] AND `price` IS NOT NULL ORDER BY `date` DESC")->queryAll();

        foreach ($result as $key => $value) {
            $stopKey = $key + 6;
            if (isset($result[$stopKey])) {
                $stopPrice = $result[$stopKey]['price'];
                $nowPrice = $value['price'];
                if (0.189 <= ($nowPrice - $stopPrice) / $stopPrice) {
                    $allUp = true;
                    $hasUpStop = false;
                    for ($i = 0; $i <= 5; $i ++) {
                        $checkKey = $key + $i;
                        0 >= $result[$checkKey]['up'] && $allUp = false;
                        $result[$checkKey]['price'] == $result[$checkKey]['zt'] && $hasUpStop = true;
                    }
                    $allUp && !$hasUpStop && $chan->push([
                        'code' => $params['code'],
                        'date' => $value['date']
                    ]);
                }
            }
        }

        $db->release();
    }

    /**
     * 连涨后续
     */
    public function four()
    {
        $codes_info = $this->getCode();

        $chan = new Channel();
        foreach ($codes_info as $value) {
            $params = [
                'code'          => $value['code'],
                'type'          => $value['type']
            ];
            xgo([$this, 'handleFour'], $chan, $params);
        }

        $list = [];
        foreach ($codes_info as $code) {
            $result = $chan->pop();
            $list = array_merge($list, $result);
        }

        $sort = array_column($list, 'times');
        array_multisort($sort, SORT_ASC, $list);

        shellPrint($list);
    }

    /**
     * 查询数据
     * @param Channel $chan
     * @param array $params
     */
    public function handleFour(Channel $chan, $params)
    {
        $dbPool = context()->get('dbPool');
        $db     = $dbPool->getConnection();
        $result = $db->prepare("SELECT `price`,`up`,`zt`,`zf`,`date` FROM `hsab` WHERE `code`=$params[code] AND `type`=$params[type] AND `price` IS NOT NULL")->queryAll();

        $response = [];
        $hasStart = false;
        $startCodeInfo = [
            'code' => $params['code']
        ];
        $nowPrice = end($result)['price'];
        foreach ($result as $value) {
            if (0 < $value['up'] && $value['price'] != $value['zt'] && $value['zf'] >= $value['up']) {
                if ($hasStart) {
                    $startCodeInfo['times'] ++;
                    $startCodeInfo['sumUp'] += $value['up'];
                    $startCodeInfo['zfDiffSumUp'] += $value['zf'] - $value['up'];
                    $startCodeInfo['upAvg'] = bcdiv($startCodeInfo['sumUp'], $startCodeInfo['times'], 2);
                    $startCodeInfo['zfDiffAvg'] = bcdiv($startCodeInfo['zfDiffSumUp'], $startCodeInfo['times'], 2);
                } else {
                    $hasStart = true;
                    $startCodeInfo['times'] = 1;
                    $startCodeInfo['sumUp'] = $value['up'];
                    $startCodeInfo['upAvg'] = 0;
                    $startCodeInfo['zfDiffSumUp'] = 0;
                    $startCodeInfo['zfDiffAvg'] = 0;
                    $startCodeInfo['price'] = $value['price'];
                    $startCodeInfo['ePrice'] = 0;
                    $startCodeInfo['nPrice'] = $nowPrice;
                    $startCodeInfo['date'] = $value['date'];
                }
            } else {
                if ($hasStart) {
                    $startCodeInfo['ePrice'] = $value['price'];
                    $response[] = $startCodeInfo;
                    $hasStart = false;
                }
            }
        }

        $chan->push($response);
        $db->release();
    }

    /**
     * 涨停第二天触及跌停未跌停收盘
     */
    public function five()
    {
        $codes_info = $this->getCode();

        $chan = new Channel();
        foreach ($codes_info as $value) {
            $params = [
                'code'          => $value['code'],
                'type'          => $value['type']
            ];
            xgo([$this, 'handleFive'], $chan, $params);
        }

        $list = [];
        foreach ($codes_info as $code) {
            $result = $chan->pop();
            $result && $list[] = $result;
        }

        $sort = array_column($list, 'date');
        array_multisort($sort, SORT_ASC, $list);

        shellPrint($list);
    }

    /**
     * 查询数据
     * @param Channel $chan
     * @param array $params
     */
    public function handleFive(Channel $chan, $params)
    {
        $dbPool = context()->get('dbPool');
        $db     = $dbPool->getConnection();
        $result = $db->prepare("SELECT `price`,`up`,`zt`,`dt`,`zf`,`zd`,`date` FROM `hsab` WHERE `code`=$params[code] AND `type`=$params[type] AND `price` IS NOT NULL")->queryAll();

        $hasZt = false;
        foreach ($result as $value) {
            if ($value['price'] == $value['zt']) {
                $hasZt = true;
            } else if ($hasZt && $value['price'] <> $value['dt'] && $value['zd'] == $value['dt']) {
                $chan->push([
                    'code' => $params['code'],
                    'date' => $value['date']
                ]);
                $hasZt = false;
            } else {
                $hasZt = false;
            }
        }

        $db->release();
    }

    /**
     * 连续九天上涨未涨停
     */
    public function six()
    {
        $codes_info = $this->getCode();

        $chan = new Channel();
        foreach ($codes_info as $value) {
            $params = [
                'code'  => $value['code'],
                'type'  => $value['type'],
                'sDate' => '2020-06-03',
                'eDate' => '2020-06-15'
            ];
            xgo([$this, 'handleSix'], $chan, $params);
        }

        $list = [];
        foreach ($codes_info as $code) {
            $result = $chan->pop();
            $result && $list[] = $result;
        }

        $sort = array_column($list, 'sumUp');
        array_multisort($sort, SORT_ASC, $list);

        shellPrint($list);
    }

    /**
     * 查询数据
     * @param Channel $chan
     * @param array $params
     */
    public function handleSix(Channel $chan, $params)
    {
        $dbPool = context()->get('dbPool');
        $db     = $dbPool->getConnection();
        $result = $db->prepare("SELECT `price`,`up`,`zt`,`zg` FROM `hsab` WHERE `code`=$params[code] AND `type`=$params[type] AND `date`>='$params[sDate]' AND `date`<='$params[eDate]'")->queryAll();

        $suffice = true;
        $sumUp = 0;
        foreach ($result as $value) {
            if (0 >= $value['up'] || $value['price'] == $value['zt'] || $value['zg'] == $value['zt']) {
                $suffice = false;
            }
            $sumUp += $value['up'];
        }

        $suffice && $chan->push([
            'code' => $params['code'],
            'sumUp' => $sumUp
        ]);

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