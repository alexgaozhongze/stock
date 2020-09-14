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
            default:
                echo '3', PHP_EOL;
                $this->three();
                echo PHP_EOL, '6', PHP_EOL;
                $this->six();
                echo PHP_EOL, '9', PHP_EOL;
                $this->nine();
        }
    }

    /**
     * 三日连续涨停 第一日涨停时间在0.189-0.999之间 后两日合计涨停时间在1.269-1.998之间
     */
    public function three()
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
            xgo([$this, 'handleThree'], $chan, $params);
        }

        $list = [];
        foreach ($codes_info as $value) {
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
        $sDate  = reset($params['dates']);
        $result = $db->prepare("SELECT `price`,`up`,`zt`,`zf`,`zs`,`date` FROM `hsab` WHERE `code`=$params[code] AND `type`=$params[type] AND `date`>='$sDate'")->queryAll();

        $suffice = false;
        array_pop($params['dates']);
        foreach ($params['dates'] as $key => $value) {
            if (18 >= $key) continue;
            if ($result[$key]['price'] == $result[$key]['zt'] && $result[$key + 1]['price'] == $result[$key + 1]['zt'] && $result[$key + 2]['price'] == $result[$key + 2]['zt']) {
                $firstDate  = $value;
                $firstZt    = $result[$key]['zt'];
                
                $firstFscj  = 'fscj_' . date('Ymd', strtotime($firstDate));
                $firstSql   = "SELECT `price` FROM `$firstFscj` WHERE `code`=$params[code] AND `type`=$params[type]";
                $firstList  = $db->prepare($firstSql)->queryAll();

                $firstCount = $firstZtCount = 0;
                foreach ($firstList as $firstValue) {
                    $firstCount ++;
                    $firstValue['price'] == $firstZt && $firstZtCount ++;
                }
                $firstZtPre = $firstZtCount / $firstCount;

                $secondDate = $result[$key + 1]['date'];
                $secondZt   = $result[$key + 1]['zt'];

                $secondFscj  = 'fscj_' . date('Ymd', strtotime($secondDate));
                $secondSql   = "SELECT `price` FROM `$secondFscj` WHERE `code`=$params[code] AND `type`=$params[type]";
                $secondList  = $db->prepare($secondSql)->queryAll();

                $secondCount = $secondZtCount = 0;
                foreach ($secondList as $secondValue) {
                    $secondCount ++;
                    $secondValue['price'] == $secondZt && $secondZtCount ++;
                }
                $secondZtPre = $secondZtCount / $secondCount;

                $thirdDate  = $result[$key + 2]['date'];
                $thirdZt    = $result[$key + 2]['zt'];

                $thirdFscj  = 'fscj_' . date('Ymd', strtotime($thirdDate));
                $thirdSql   = "SELECT `price` FROM `$thirdFscj` WHERE `code`=$params[code] AND `type`=$params[type]";
                $thirdList  = $db->prepare($thirdSql)->queryAll();

                $thirdCount = $thirdZtCount = 0;
                foreach ($thirdList as $thirdValue) {
                    $thirdCount ++;
                    $thirdValue['price'] == $thirdZt && $thirdZtCount ++;
                }
                $thirdZtPre = $thirdZtCount / $thirdCount;

                if (0.189 <= $firstZtPre && 0.999 >= $firstZtPre && 1.998 >= $secondZtPre + $thirdZtPre && 1.269 <= $secondZtPre + $thirdZtPre) {
                    $price18 = $result[$key - 18]['price'];
                    !$price18 && $price18 = $result[$key - 18]['zs'];
                    $rise18 = $firstZt / $price18;
                    1.35 <= $rise18 && $chan->push([
                        'code'  => $params['code'],
                        'date'  => $firstDate,
                        'fPre'  => $firstZtPre,
                        'sPre'  => $secondZtPre,
                        'tPre'  => $thirdZtPre,
                        'rise'  => $firstZt / $price18
                    ]) && $suffice = true;
                    break;
                }
            }
        }
        
        !$suffice && $chan->push(false);
        $db->release();
    }

    /**
     * 前三日连续触及涨停 涨停时间均值高于0.63 振幅均值低于9.63 当日竞价成交收高于333333或分时成交高于9且09:36:36之前触及涨停未下跌
     */
    public function six()
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
     * macd 30日最低后 ema5>ema10>ema20 搞
     */

    public function nine()
    {
        $dates      = dates(9);
        $codes_info = $this->getCode();

        // $codes_info = [
        //     [
        //         'code'  => 2617,
        //         'type'  => 2
        //     ]
        // ];

        $chan = new Channel();
        foreach ($codes_info as $value) {
            $params = [
                'code'  => $value['code'],
                'type'  => $value['type'],
                'dates' => $dates
            ];
            xgo([$this, 'handleNine'], $chan, $params);
        }

        $list = [];
        foreach ($codes_info as $value) {
            $result = $chan->pop();
            $result && $list[] = $result;
        }

        $sort = array_column($list, 'pre');
        array_multisort($sort, SORT_ASC, $list);

        shellPrint($list);
    }

    /**
     * 查询数据
     * @param Channel $chan
     * @param array $params
     */
    public function handleNine(Channel $chan, $params)
    {
        $dbPool = context()->get('dbPool');
        $db     = $dbPool->getConnection();

        $sDate  = reset($params['dates']);

        $sql    = "SELECT `zt` FROM `hsab` WHERE `code`=$params[code] AND `type`=$params[type] AND `date`=CURDATE()";
        $sql    = "SELECT `zt` FROM `hsab` WHERE `code`=$params[code] AND `type`=$params[type] AND `date`='2020-09-09'";
        $result = $db->prepare($sql)->queryAll();

        foreach ($result as $key => $value) {
            $fscj   = 'fscj_' . date('Ymd');
            $fscj   = 'fscj_20200909';
            $sql    = "SELECT SUM(`num`) AS `snum` FROM `$fscj` WHERE `code`=$params[code] AND `type`=$params[type] AND `price`=$value[zt]";
            $info   = $db->prepare($sql)->queryOne();

            if (100000000 <= $info['snum'] * $value['zt'] * 100) {
                echo $params['code'], PHP_EOL;
            }

        }

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