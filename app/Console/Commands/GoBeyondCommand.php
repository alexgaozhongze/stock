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
        if (999 == $method) {
            echo '3', PHP_EOL;
            $this->three();
            echo PHP_EOL, '6', PHP_EOL;
            $this->six();
            echo PHP_EOL, '9', PHP_EOL;
            $this->nine();
        } else {
            call_user_func([$this, $method]);
        }
    }

    /**
     * 三日连续涨停 涨停时间第一日超50% 后两日合计超75%
     */
    public function three()
    {
        $dates      = dates(58);
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
        $sDate  = reset($params['dates']);
        $result = $db->prepare("SELECT `price`,`up`,`zt`,`zf`,`date` FROM `hsab` WHERE `code`=$params[code] AND `type`=$params[type] AND `date`>='$sDate'")->queryAll();

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

                if (0.5 <= $firstZtPre && 1 > $firstZtPre && 2 > $secondZtPre + $thirdZtPre && 1.5 <= $secondZtPre + $thirdZtPre) {
                    $price19 = $result[$key - 19]['price'];
                    $rise19 = $firstZt / $price19;
                    1.5 <= $rise19 && $chan->push([
                        'code'  => $params['code'],
                        'date'  => $firstDate,
                        'fPre'  => $firstZtPre,
                        'sPre'  => $secondZtPre,
                        'tPre'  => $thirdZtPre,
                        'rise'  => $firstZt / $price19
                    ]) && $suffice = true;
                    break;
                }
            }
        }
        !$suffice && $chan->push(false);
        $db->release();
    }

    /**
     * 三日连续涨停 涨停时间第一日超50% 后两日合计超75%
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
    public function handleSix(Channel $chan, $params)
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

                if (0.189 <= $firstZtPre && 0.999 >= $firstZtPre && 1.998 >= $secondZtPre + $thirdZtPre && 1.269 <= $secondZtPre + $thirdZtPre && $result[$key - 1]['price'] != $result[$key - 1]['zt']) {
                    $price18 = $result[$key - 18]['price'];
                    !$price18 && $price18 = $result[$key - 18]['zs'];
                    $rise18 = $firstZt / $price18;
                    1.269 <= $rise18 && $chan->push([
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
     * 三日连续涨停 涨停时间第一日超50% 后两日合计超75%
     */
    public function nine()
    {
        $dates      = dates(36);
        $codes_info = $this->getCode();

        // $codes_info = [
        //     [
        //         'code'  => 600158,
        //         'type'  => 1
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

        $sort = array_column($list, 'date');
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

                if (0.99 >= $firstZtPre && 1.98 >= $secondZtPre + $thirdZtPre && $result[$key - 1]['price'] != $result[$key - 1]['zt']) {
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