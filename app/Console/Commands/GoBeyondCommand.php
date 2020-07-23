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

    public function zero()
    {
        echo date('Y-m-d H:i:s'), PHP_EOL;
        $this->one();
        $this->two();
        $this->three();
        echo PHP_EOL;
    }

    /**
     * XDR日及前或后日连续涨停
     */
    public function one()
    {
        $dbPool = context()->get('dbPool');
        $db     = $dbPool->getConnection();

        $codes_info = $db->prepare("SELECT `code`,`type`,`date` FROM `hsab` WHERE LEFT(`name`,1) IN ('X','D','R') AND `price`=`zt`")->queryAll();

        $chan = new Channel();
        foreach ($codes_info as $value) {
            $params = [
                'code'  => $value['code'],
                'type'  => $value['type'],
                'date'  => $value['date']
            ];
            xgo([$this, 'handleOne'], $chan, $params);
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
    public function handleOne(Channel $chan, $params)
    {
        $dbPool = context()->get('dbPool');
        $db     = $dbPool->getConnection();
        $preDateInfo = $db->prepare("SELECT `date` FROM `hsab` WHERE `date`<'$params[date]' ORDER BY `date` DESC")->queryOne();

        $preDate = $preDateInfo['date'];
        $result = $db->prepare("SELECT `price`,`zt` FROM `hsab` WHERE `code`=$params[code] AND `type`=$params[type] AND `price`=`zt` AND `date`='$preDate'")->queryAll();
        if ($result) {
            $chan->push([
                'code' => $params['code'],
                'date' => $params['date']
            ]);
        } else {
            $chan->push(false);
        }
        $db->release();
    }

    /**
     * 连续振幅超9.99且最低未触及跌停
     */
    public function two()
    {
        $startDate = date('Y-m-d');
        $codes_info = $this->getCode();

        $chan = new Channel();
        foreach ($codes_info as $value) {
            $params = [
                'code'  => $value['code'],
                'type'  => $value['type'],
                'sDate' => $startDate
            ];
            xgo([$this, 'handleTwo'], $chan, $params);
        }

        $list = [];
        foreach ($codes_info as $code) {
            $result = $chan->pop();
            $result && $list[] = $result;
        }

        $sort = array_column($list, 'continue');
        array_multisort($sort, SORT_ASC, $list);

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
        $result = $db->prepare("SELECT `price`,`up`,`dt`,`zd`,`zf`,`date` FROM `hsab` WHERE `code`=$params[code] AND `type`=$params[type] AND `date`<='$params[sDate]' ORDER BY `date` DESC LIMIT 9")->queryAll();

        $continue       = 0;
        $currentPrice   = 0.00;
        $currentUp      = 0.00;
        $currentZd      = 0.00;
        $currentZf      = 0.00;
        foreach ($result as $value) {
            if ($params['sDate'] == $value['date']) {
                $currentPrice   = $value['price'];
                $currentUp      = $value['up'];
                $currentZd      = $value['zd'];
                $currentZf      = $value['zf'];
                continue;
            }
    
            if (9.99 > $value['zf'] || $value['zd'] == $value['dt']) {
                break;
            }

            $continue ++;
        }

        if ($continue >= 3) {
            $chan->push([
                'code'      => $params['code'],
                'continue'  => $continue,
                'price'     => $currentPrice,
                'zd'        => $currentZd,
                'up'        => $currentUp,
                'zf'        => $currentZf
            ]);
        } else {
            $chan->push(false);
        }

        $db->release();
    }

    /**
     * 最高达到九十九个交易日新高
     */
    public function three()
    {
        $date = Flag::string(['e', 'date'], date('Y-m-d'));
        $codes_info = $this->getCode();

        $chan = new Channel();
        foreach ($codes_info as $value) {
            $params = [
                'code'  => $value['code'],
                'type'  => $value['type'],
                'stDe'  => $date
            ];
            xgo([$this, 'handleThree'], $chan, $params);
        }

        $list = [];
        foreach ($codes_info as $code) {
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
     * @param array $params
     */
    public function handleThree(Channel $chan, $params)
    {
        $dbPool = context()->get('dbPool');
        $db     = $dbPool->getConnection();
        $result = $db->prepare("SELECT `zg` FROM `hsab` WHERE `code`=$params[code] AND `type`=$params[type] AND `date`<='$params[stDe]' AND `price` IS NOT NULL LIMIT 99")->queryAll();

        $maxZg = 0.00;
        foreach ($result as $value) {
            $value['zg'] >= $maxZg && $maxZg = $value['zg'];
        }
        if ($value['zg'] >= $maxZg) {
            $info = $db->prepare("SELECT `price`,`up`,`zt`,`zf`,`zg` FROM `hsab` WHERE `code`=$params[code] AND `type`=$params[type] AND `date`=CURDATE()")->queryOne();
            if ($info['price'] == $info['zg'] && $info['price'] != $info['zt']) {
                $chan->push([
                    'code'  => $params['code'],
                    'pirce' => $info['price'],
                    'up'    => $info['up'],
                ]);
            } else {
                $chan->push(false);
            }
        } else {
            $chan->push(false);
        }

        $db->release();
    }

    /**
     * 连涨后续
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
            $list = array_merge($list, $result);
        }

        $sort = array_column($list, 'times');
        array_multisort($sort, SORT_ASC, $list);

        foreach ($list as $key => $value) {
            unset($value['zfDiffSumUp']);
            $list[$key] = $value;
        }

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
    public function six()
    {
        $codes_info = $this->getCode();

        $chan = new Channel();
        foreach ($codes_info as $value) {
            $params = [
                'code'          => $value['code'],
                'type'          => $value['type']
            ];
            xgo([$this, 'handleSix'], $chan, $params);
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
    public function handleSix(Channel $chan, $params)
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
    public function seven()
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
            xgo([$this, 'handleSeven'], $chan, $params);
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
    public function handleSeven(Channel $chan, $params)
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

    /**
     * 涨停加第二日触及跌停
     */
    public function eight()
    {
        $dbPool = context()->get('dbPool');
        $db     = $dbPool->getConnection();

        $codes_info = $this->getCode();

        $chan = new Channel();
        foreach ($codes_info as $value) {
            $params = [
                'code'  => $value['code'],
                'type'  => $value['type']
            ];
            xgo([$this, 'handleEight'], $chan, $params);
        }

        $list = [];
        foreach ($codes_info as $code) {
            $result = $chan->pop();
            $list = array_merge($list, $result);
        }

        $sort = array_column($list, 'date');
        array_multisort($sort, SORT_ASC, $list);

        foreach ($list as $key => $value) {
            $result = $db->prepare("SELECT `price` FROM `hsab` WHERE `code`=$value[code] AND `date`=CURDATE()")->queryOne();
            $value['nPrice'] = $result['price'];
            $result['price'] >= $value['price'] && $value['isUp'] = 1;
            $list[$key] = $value;
        }

        shellPrint($list);
    }

    /**
     * 查询数据
     * @param Channel $chan
     * @param array $params
     */
    public function handleEight(Channel $chan, $params)
    {
        $dbPool = context()->get('dbPool');
        $db     = $dbPool->getConnection();
        $result = $db->prepare("SELECT `price`,`zt`,`dt`,`zd`,`date` FROM `hsab` WHERE `code`=$params[code] AND `type`=$params[type]")->queryAll();

        $suffice = false;
        $response = [];
        foreach ($result as $value) {
            if ($value['price'] == $value['zt']) {
                $suffice = true;
            } else if ($suffice && $value['zd'] == $value['dt']) {
                $response[] = [
                    'code' => $params['code'],
                    'price' => $value['price'],
                    'nPrice' => 0.00,
                    'isUp' => 0,
                    'date' => $value['date']
                ];
                $suffice = false;
            } else {
                $suffice = false;
            }
        }

        $chan->push($response);
        $db->release();
    }

    /**
     * 涨停日开板数
     */
    public function nine()
    {
        $codes_info = $this->getCode();

        $chan = new Channel();
        foreach ($codes_info as $value) {
            $params = [
                'code'  => $value['code'],
                'type'  => $value['type']
            ];
            xgo([$this, 'handleNine'], $chan, $params);
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
    public function handleNine(Channel $chan, $params)
    {
        $dbPool = context()->get('dbPool');
        $db     = $dbPool->getConnection();
        $result = $db->prepare("SELECT `price`,`zt`,`date` FROM `hsab` WHERE `code`=$params[code] AND `type`=$params[type] AND `price`=`zt`")->queryAll();

        $response = [];
        foreach ($result as $value) {
            if (strtotime('20200527') > strtotime($value['date'])) continue;

            $fscj_table = 'fscj_' . date('Ymd', strtotime($value['date']));
            $list = $db->prepare("SELECT `price` FROM `$fscj_table` WHERE `code`=$params[code] AND `type`=$params[type]")->queryAll();

            $suffice = false;
            $count = 0;
            foreach ($list as $lValue) {
                if ($value['zt'] == $lValue['price']) {
                    $suffice = true;
                } else if ($suffice) {
                    $count ++;
                    $suffice = false;
                }
            }

            $response[] = [
                'code' => $params['code'],
                'times' => $count,
                'date' => $value['date']
            ];
        }

        $chan->push($response);
        $db->release();
    }

    /**
     * 十九个交易日涨幅排行
     */
    public function ten()
    {
        $codes_info = $this->getCode();

        $chan = new Channel();
        foreach ($codes_info as $value) {
            $params = [
                'code'  => $value['code'],
                'type'  => $value['type'],
                'sDate' => '2020-05-13',
                'eDate' => '2020-06-08'
            ];
            xgo([$this, 'handleTen'], $chan, $params);
        }

        $list = [];
        foreach ($codes_info as $code) {
            $result = $chan->pop();
            $list[] = $result;
        }

        $sort = array_column($list, 'sUp');
        array_multisort($sort, SORT_ASC, $list);

        shellPrint($list);
    }

    /**
     * 查询数据
     * @param Channel $chan
     * @param array $params
     */
    public function handleTen(Channel $chan, $params)
    {
        $dbPool = context()->get('dbPool');
        $db     = $dbPool->getConnection();
        $result = $db->prepare("SELECT SUM(`up`) AS `sup` FROM `hsab` WHERE `code`=$params[code] AND `type`=$params[type] AND `date`>='$params[sDate]' AND `date`<='$params[eDate]'")->queryOne();

        $chan->push([
            'code'  => $params['code'],
            'sUp'   => $result['sup']
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