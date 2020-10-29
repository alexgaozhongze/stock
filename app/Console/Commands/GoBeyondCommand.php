<?php

namespace App\Console\Commands;

use Mix\Console\CommandLine\Flag;
use Swoole\Coroutine\Channel;
use Mix\Concurrent\Timer;

/**
 * Class GoBeyondCommand
 * @package App\Console\Commands
 * @author alex <alexgaozhongze@gmail.com>
 */
class GoBeyondCommand
{

    public function main()
    {
        $method = Flag::string(['v', 'version'], 'one');
        switch ($method) {
            case 3:
                $this->three();
                break;
            case 6:
                $this->six();
                break;
            case 9:
                $this->nine();
                break;
            case 36:
                $this->thirtySix();
                break;
            case 39:
                $this->thirtyNine();
                break;
            case 63:
                $this->sixtyThree();
                break;
            default:
                $this->three();
                $this->six();
                $this->nine();
        }
    }

    /**
     * 9days ema20 >= ema60 405times
     */
    private function three()
    {
        $codesInfo  = $this->getCode();

        // $codesInfo  = [
        //     [
        //         'code'  => 603993,
        //         'type'  => 1
        //     ]
        // ];

        $chan = new Channel();
        foreach ($codesInfo as $value) {
            $params = [
                'code'  => $value['code'],
                'type'  => $value['type'],
            ];
            xgo([$this, 'handleThree'], $chan, $params);
        }

        $list = [];
        foreach ($codesInfo as $value) {
            $result = $chan->pop();
            $list = array_merge($list, $result);
        }

        $sort = array_column($list, 'date');
        array_multisort($sort, SORT_ASC, $list);

        shellPrint($list);
    }

    public function handleThree(Channel $chan, $params)
    {
        $dbPool = context()->get('dbPool');
        $db     = $dbPool->getConnection();

        $sql    = "SELECT `ema20`,`ema60`,`time` FROM `macd` WHERE `code`=$params[code] AND `type`=$params[type] ORDER BY `time` DESC LIMIT 999";
        $result = $db->prepare($sql)->queryAll();

        $times  = [];
        foreach ($result as $key => $value) {
            if (!isset($result[$key + 432])) continue;

            $sufficeNums= 0;
            for ($i = 0; $i <= 432; $i ++) {
                $result[$key + $i]['ema20'] >= $result[$key + $i]['ema60'] && $sufficeNums ++;
            }

            $sufficeNums >= 405 && $times[] = $value['time'];
        }

        $days   = [];
        foreach ($times as $value) {
            $day= substr($value, 0, 10);
            $days[$day] = $day;
        }

        $response = [];
        foreach ($days as $value) {
            $response[] = [
                'code'  => $params['code'],
                'date'  => $value
            ];
        }

        $chan->push($response);
        $db->release();
    }

    /**
     * 前三日连续触及涨停 涨停时间均值高于0.63 振幅均值低于9.63 当日竞价成交收高于333333或分时成交高于9且09:36:36之前触及涨停未下跌
     */
    private function six()
    {
        $dates      = dates(36);
        $codes_info = $this->getCode();

        $chan = new Channel();
        foreach ($codes_info as $value) {
            $params = [
                'code'  => $value['code'],
                'type'  => $value['type'],
                'dates' => $dates
            ];
            xgo([$this, 'handleSix'], $chan, $params);
        }

        $list = [];
        foreach ($codes_info as $value) {
            $result = $chan->pop();
            $list = array_merge($list, $result);
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
    public function handleSix(Channel $chan, $params)
    {
        $dbPool = context()->get('dbPool');
        $db     = $dbPool->getConnection();

        $sDate  = reset($params['dates']);
        $eDate  = end($params['dates']);

        $sql    = "SELECT `price`,`zt`,`zg`,`zf`,`date` FROM `hsab` WHERE `code`=$params[code] AND `type`=$params[type] AND `date` BETWEEN '$sDate' AND '$eDate'";
        $result = $db->prepare($sql)->queryAll();

        $response = [];
        foreach ($params['dates'] as $key => $value) {
            if (isset($result[$key + 2])) {
                $upStops = [];
                for ($i = $key; $i <= $key + 2; $i ++) {
                    if ($result[$i]['zg'] == $result[$i]['zt']) {
                        $upStops[] = $result[$i];
                    }
                }

                if (3 == $countUpStop = count($upStops)) {
                    $fscjZtPreSum   = 0;
                    $zfSum          = 0;
                    foreach ($upStops as $usKey => $usValue) {
                        $fscj  = 'fscj_' . date('Ymd', strtotime($usValue['date']));
                        $sql   = "SELECT `price` FROM `$fscj` WHERE `code`=$params[code] AND `type`=$params[type]";
                        $list  = $db->prepare($sql)->queryAll();

                        $fscjCount      = 0;
                        $fscjZtCount    = 0;
                        foreach ($list as $firstValue) {
                            $fscjCount ++;
                            $firstValue['price'] == $usValue['zt'] && $fscjZtCount ++;
                        }
                        $fscjZtPre  = bcdiv($fscjZtCount, $fscjCount, 3);

                        $fscjZtPreSum   += $fscjZtPre;
                        $zfSum          += $usValue['zf'];

                        $usValue['ztPre']   = $fscjZtPre;
                        $upStops[$usKey]    = $usValue;
                    }

                    $ztPre  = bcdiv($fscjZtPreSum, $countUpStop, 3);
                    $zfPre  = bcdiv($zfSum, $countUpStop, 3);
                    if (0.63 <= $ztPre && 9.63 >= $zfPre) {
                        $dateKey    = array_search(end($upStops)['date'], $params['dates']);
                        $nextDay    = isset($params['dates'][$dateKey + 1]) ? $params['dates'][$dateKey + 1] : date('Y-m-d');

                        $nextFscj   = 'fscj_' . date('Ymd', strtotime($nextDay));
                        $sql   = "SELECT `price`,`num`,`up`,`bs` FROM `$nextFscj` WHERE `code`=$params[code] AND `type`=$params[type] AND `time`<='09:36:36'";
                        $list  = $db->prepare($sql)->queryAll();

                        $sum    = 0;
                        $sumNum = 0;
                        $sumCje = 0;
                        $priceAvg   = 0.00;
                        $sufficeSum = 0;
                        $hasFloor   = false;
                        $hasUpStop  = false;
                        foreach ($list as $key => $lValue) {
                            $lValue['up'] <= 0 && $hasFloor = true;
                            $lValue['up'] >= 9.63 && $hasUpStop = true;
                            if (4 == $lValue['bs']) {
                                $sum ++;
                                $sumNum += $lValue['num'];
                                $cje    = bcmul($lValue['price'], $lValue['num']);
                                $sumCje += $cje;
    
                                $priceAvg = bcdiv($sumCje, $sumNum, 3);
                                $priceAvg >= $lValue['price'] && $sufficeSum ++;
                            }
                        }
                        if ((333333 <= $sumNum || 9 <= $sum) && !$hasFloor && $hasUpStop) {
                            $response[] = [
                                'code'  => $params['code'],
                                'date'  => end($upStops)['date'],
                                'tPre'  => $ztPre,
                                'fPre'  => $zfPre,
                                'fjsm'  => $sum,
                                'fjds'  => $sufficeSum
                            ];
                        }
                    }
                }
            }
        }

        $response = array_unique($response, SORT_REGULAR);
        $chan->push($response);
        $db->release();
    }

    /**
     * 涨停且5分钟成交额上亿
     */

    private function nine()
    {
        $codes_info = $this->getCode(" AND `zg`=`zt`");

        $chan = new Channel();
        foreach ($codes_info as $value) {
            xgo([$this, 'handleNine'], $chan, $value);
        }

        $list = [];
        foreach ($codes_info as $value) {
            $result = $chan->pop();
            $result && $list[] = $result;
        }

        $sort = array_column($list, 'time');
        array_multisort($sort, SORT_ASC, $list);

        shellPrint($list);

        $timer = new Timer();
        $timer->tick(153999, function () use ($codes_info, &$list) {
            $preList    = $list;

            $codes_info = $this->getCode(" AND `zg`=`zt`");

            $chan = new Channel();
            foreach ($codes_info as $value) {
                xgo([$this, 'handleNine'], $chan, $value);
            }
    
            $list = [];
            foreach ($codes_info as $value) {
                $result = $chan->pop();
                $result && $list[] = $result;
            }
    
            $sort = array_column($list, 'time');
            array_multisort($sort, SORT_ASC, $list);
    
            if ($preList != $list) shellPrint($list);
        });

        Timer::new()->after(18999999, function () use ($timer) {
            $timer->clear();
        });
    }

    /**
     * 查询数据
     * @param Channel $chan
     * @param array $params
     */
    public function handleNine(Channel $chan, $params)
    {
        $return = [];

        $dbPool = context()->get('dbPool');
        $db     = $dbPool->getConnection();

        $fscj   = 'fscj_' . date('Ymd');
        $sql    = "SELECT `price`,`num`,`time` FROM `$fscj` WHERE `code`=$params[code] AND `type`=$params[type] AND `time`>='09:30:00'";
        $list   = $db->prepare($sql)->queryAll();

        $curZtCje    = 0;
        foreach ($list as $key => $value) {
            if ($params['zt'] == $value['price']) {
                if (!isset($list[$key + 9])) continue;

                for ($i=1; $i<=9; $i++) {
                    if ($params['zt'] != $list[$key+$i]['price']) continue 2;
                }

                $curZtCje = $value['price'] * 100 * $value['num'];
                
                $i = 1;
                while (isset($list[$key - $i]) && strtotime($value['time']) - strtotime($list[$key - $i]['time']) <= 200) {
                    $curZtCje += $list[$key - $i]['price'] * 100 * $list[$key - $i]['num'];
                    $i++;
                }

                $i = 1;
                while (isset($list[$key + $i]) && strtotime($list[$key + $i]['time']) - strtotime($value['time']) <= 100) {
                    $curZtCje += $list[$key + $i]['price'] * 100 * $list[$key + $i]['num'];
                    $i++;
                }

                break;
            }
        }
        if (100000000 > $curZtCje) goto end;
        $curZtTime  = $value['time'];

        $sql    = "SELECT `zt`,`date` FROM `hsab` WHERE `code`=$params[code] AND `type`=$params[type] AND `date`=(SELECT MAX(`date`) FROM `hsab` WHERE `date`<>CURDATE()) AND `price`=`zt`";
        $info   = $db->prepare($sql)->queryOne();
        $cte    = 0;
        if ($info) {
            $cte    = 1;
            $fscj   = 'fscj_' . date('Ymd', strtotime($info['date']));
            $sql    = "SELECT `price`,`num`,`time` FROM `$fscj` WHERE `code`=$params[code] AND `type`=$params[type] AND `time`>='09:30:00'";
            $list   = $db->prepare($sql)->queryAll();

            $preZtCje    = 0;
            foreach ($list as $key => $value) {
                if ($info['zt'] == $value['price']) {
                    $preZtCje = $value['price'] * 100 * $value['num'];
                    
                    $i = 1;
                    while (isset($list[$key - $i]) && strtotime($value['time']) - strtotime($list[$key - $i]['time']) <= 200) {
                        $preZtCje += $list[$key - $i]['price'] * 100 * $list[$key - $i]['num'];
                        $i++;
                    }

                    $i = 1;
                    while (isset($list[$key + $i]) && strtotime($list[$key + $i]['time']) - strtotime($value['time']) <= 100) {
                        $preZtCje += $list[$key + $i]['price'] * 100 * $list[$key + $i]['num'];
                        $i++;
                    }

                    break;
                }
            }

            if (100000000 > $preZtCje) goto end;
            if ($preZtCje > $curZtCje) goto end;
        }

        $return = [
            'code'  => $params['code'],
            'price' => $params['price'],
            'up'    => $params['up'],
            'cjs'   => number_format($params['cjs']),
            'cje'   => number_format($params['cje']),
            'cjl'   => number_format($curZtCje),
            'cte'   => $cte,
            'time'  => $curZtTime
        ];

        end:
        $chan->push($return);
        $db->release();
    }

    /**
     * 三日连续涨停
     */
    private function thirtySix()
    {
        $dates      = dates(36);
        $eDate      = end($dates);
        $sDate      = reset($dates);
        $codesInfo  = $this->getCode();

        $chan = new Channel();
        foreach ($codesInfo as $value) {
            $params = [
                'code'  => $value['code'],
                'type'  => $value['type'],
                'sDate' => $sDate,
                'eDate' => $eDate
            ];
            xgo([$this, 'handleThirtySix'], $chan, $params);
        }

        $list = [];
        foreach ($codesInfo as $value) {
            $result = $chan->pop();
            $list = array_merge($list, $result);
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
    public function handleThirtySix(Channel $chan, $params)
    {
        $dbPool = context()->get('dbPool');
        $db     = $dbPool->getConnection();

        $sql    = "SELECT `price`,`zt`,`date` FROM `hsab` WHERE `code`=$params[code] AND `type`=$params[type] AND `date` BETWEEN '$params[sDate]' AND '$params[eDate]'";
        $list   = $db->prepare($sql)->queryAll();

        $response = [];
        foreach ($list as $key => $value) {
            if (!isset($list[$key - 2])) continue;
            for ($i = 0; $i < 3; $i ++) {
                if ($list[$key - $i]['price'] != $list[$key - $i]['zt']) continue 2;
            }

            $response[] = [
                'code'  => $params['code'],
                'date'  => $value['date']
            ];
        }
        
        $chan->push($response);
        $db->release();
    }

    /**
     * 最低
     */
    private function thirtyNine()
    {
        $codesInfo  = $this->getCode();

        $chan = new Channel();
        foreach ($codesInfo as $value) {
            $params = [
                'code'  => $value['code'],
                'type'  => $value['type']
            ];
            xgo([$this, 'handleThirtyNine'], $chan, $params);
        }

        $list = [];
        foreach ($codesInfo as $value) {
            $result = $chan->pop();
            $list[] = $result;
        }

        $sort = array_column($list, 'days');
        array_multisort($sort, SORT_ASC, $list);

        shellPrint($list);
    }

    /**
     * 查询数据
     * @param Channel $chan
     * @param array $params
     */
    public function handleThirtyNine(Channel $chan, $params)
    {
        $dbPool = context()->get('dbPool');
        $db     = $dbPool->getConnection();

        $sql    = "SELECT `zd` FROM `hsab` WHERE `code`=$params[code] AND `type`=$params[type] ORDER BY `date` DESC";
        $list   = $db->prepare($sql)->queryAll();

        $response = [];
        $curInfo= reset($list);
        $curZd  = $curInfo['zd'];

        for ($i = 0; $i < count($list); $i ++) {
            if ($curZd > $list[$i]['zd']) break;
        }

        $response = [
            'code'  => $params['code'],
            'days'  => $i
        ];
        
        $chan->push($response);
        $db->release();
    }

    /**
     * 单日最高涨幅超5%
     */
    private function sixtyThree()
    {
        $dates      = dates(36);
        $eDate      = end($dates);
        $sDate      = reset($dates);
        $codesInfo  = $this->getCode();

        $chan = new Channel();
        foreach ($codesInfo as $value) {
            $params = [
                'code'  => $value['code'],
                'type'  => $value['type'],
                'sDate' => $sDate,
                'eDate' => $eDate
            ];
            xgo([$this, 'handleSixtyThree'], $chan, $params);
        }

        $list = [];
        foreach ($codesInfo as $value) {
            $result = $chan->pop();
            $list = array_merge($list, $result);
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
    public function handleSixtyThree(Channel $chan, $params)
    {
        $dbPool = context()->get('dbPool');
        $db     = $dbPool->getConnection();

        $sql    = "SELECT `price`,`zt`,`date` FROM `hsab` WHERE `code`=$params[code] AND `type`=$params[type] AND `date` BETWEEN '$params[sDate]' AND '$params[eDate]' AND `price` IS NOT NULL";
        $list   = $db->prepare($sql)->queryAll();

        $response = [];
        foreach ($list as $key => $value) {
            if ($value['price'] != $value['zt']) continue;

            $response[] = [
                'code'  => $params['code'],
                'date'  => $value['date']
            ];
        }
        
        $chan->push($response);
        $db->release();
    }

    private function getCode($and = '')
    {
        $dbPool = context()->get('dbPool');
        $db     = $dbPool->getConnection();
        $sql    = "SELECT `code`,`price`,`up`,`zt`,`cjs`,`cje`,`type` FROM `hsab` WHERE `date`=(SELECT MAX(`date`) FROM `hsab`) AND `price` IS NOT NULL AND LEFT(`name`,1) NOT IN ('*','S') AND LEFT(`code`,3) NOT IN (300,688) AND `code` NOT IN (SELECT `code` FROM `hsab` WHERE `date` >= (SELECT MIN(`date`) FROM (SELECT `date` FROM `hsab` WHERE `date` <> (SELECT MAX(`date`) FROM `hsab`) GROUP BY `date` ORDER BY `date` DESC LIMIT 99) AS `t`) AND LEFT(`name`,1)='N' GROUP BY `code`)";
        $sql    .= $and;
        $codesInfo  = $db->prepare($sql)->queryAll();
        $db->release();

        return $codesInfo;
    }

}