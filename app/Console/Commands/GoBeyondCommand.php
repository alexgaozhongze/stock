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
            case 666:
                $this->SixSixSix();
                break;
        }
    }

    private function SixSixSix()
    {
        $dates      = dates(9);
        $codesInfo  = $this->getCode();

        $chan = new Channel();
        foreach ($codesInfo as $value) {
            $params = [
                'code'  => $value['code'],
                'type'  => $value['type'],
                'price' => $value['price'],
                'sDate' => reset($dates),
                'eDate' => end($dates)
            ];
            xgo([$this, 'handleSixSixSix'], $chan, $params);
        }

        $list = [];
        foreach ($codesInfo as $value) {
            $result = $chan->pop();
            $result && $list[] = $result;
        }

        $sort = array_column($list, 'pre');
        array_multisort($sort, SORT_DESC, $list);

        $list = array_slice($list, 0, 9);
        shellPrint($list);
    }

    public function handleSixSixSix(Channel $chan, $params)
    {
        $dbPool = context()->get('dbPool');
        $db     = $dbPool->getConnection();

        $sql    = "SELECT `price`,`zt`,`jk`,`zd` FROM `hsab` WHERE `code`=$params[code] AND `type`=$params[type] AND `date`>='$params[sDate]' AND `price` IS NOT NULL";
        $result = $db->prepare($sql)->queryAll();

        $first  = reset($result);
        $match  = false;
        foreach ($result as $value) {
            $value['price'] == $value['zt'] && $value['jk'] * 0.001 <= $value['zd'] && $match = true;
        }

        $response = [
            'code'  => $params['code'],
            'type'  => $params['type'],
            'price' => $params['price'],
            'pre'   => bcdiv($params['price'], $first['price'], 3)
        ];

        if ($match) {
            $chan->push($response);
        } else {
            $chan->push([]);
        }
        $db->release();
    }

    /**
     * 9 days ema20 >= ema60 several times
     */
    private function three()
    {
        $dates      = dates(36);
        $codesInfo  = $this->getCode();

        $chan = new Channel();
        foreach ($codesInfo as $value) {
            $params = [
                'code'  => $value['code'],
                'type'  => $value['type'],
                'sDate' => reset($dates),
                'eDate' => end($dates)
            ];
            xgo([$this, 'handleThree'], $chan, $params);
        }

        $list = [];
        foreach ($codesInfo as $value) {
            $result = $chan->pop();
            $result && $list[] = $result;
        }

        $sort = array_column($list, 'pre');
        array_multisort($sort, SORT_DESC, $list);

        $list = array_slice($list, 0, 36);
        shellPrint($list);
    }

    public function handleThree(Channel $chan, $params)
    {
        $dbPool = context()->get('dbPool');
        $db     = $dbPool->getConnection();

        $sql    = "SELECT `code`,`price`,`type` FROM `hsab` WHERE `code`=$params[code] AND `type`=$params[type] AND `date` IN ('$params[sDate]','$params[eDate]') AND `price` IS NOT NULL";
        $result = $db->prepare($sql)->queryAll();

        $response = [];
        if (2 == count($result)) {
            $response = end($result);
            $response['pre'] = bcdiv(end($result)['price'], reset($result)['price'], 3);
        }

        $chan->push($response);
        $db->release();
    }

    /**
     * 9 days ema20 >= ema60 several times
     */
    private function six()
    {
        $dates      = dates(36);
        $codesInfo  = $this->getCode();

        $chan = new Channel();
        foreach ($codesInfo as $value) {
            $params = [
                'code'  => $value['code'],
                'type'  => $value['type'],
                'sDate' => reset($dates)
            ];
            xgo([$this, 'handleSix'], $chan, $params);
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

    public function handleSix(Channel $chan, $params)
    {
        $dbPool = context()->get('dbPool');
        $db     = $dbPool->getConnection();

        $sql    = "SELECT `ema20`,`ema60`,`time` FROM `macd` WHERE `code`=$params[code] AND `type`=$params[type] AND `time`>='$params[sDate]'";
        $result = $db->prepare($sql)->queryAll();

        $count  = 0;
        $times  = [];
        foreach ($result as $key => $value) {
            $count  += $value['ema20'] > $value['ema60'] ? 1 : 0;
            if ($key >= 432) {
                $count -= $result[$key - 432]['ema20'] > $result[$key - 432]['ema60'] ? 1 : 0;
            }

            $count >= 423 && $times[] = $value['time'];
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
     * 3days upStop continue
     */
    private function nine()
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
            xgo([$this, 'handleNine'], $chan, $params);
        }

        $list = [];
        foreach ($codesInfo as $value) {
            $result = $chan->pop();
            $result && $list[] = $result;
        }

        $sort = array_column($list, 'date');
        array_multisort($sort, SORT_ASC, $list);

        shellPrint($list);
    }

    public function handleNine(Channel $chan, $params)
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

            $response   = [
                'code'  => $params['code'],
                'date'  => $value['date']
            ];
        }
        
        $chan->push($response);
        $db->release();
    }

    private function getCode()
    {
        $dbPool = context()->get('dbPool');
        $db     = $dbPool->getConnection();
        $sql    = "SELECT `code`,`price`,`up`,`zt`,`type` FROM `hsab` WHERE `date`=(SELECT MAX(`date`) FROM `hsab`) AND `price` IS NOT NULL AND LEFT(`name`,1) NOT IN ('*','S') AND LEFT(`code`,3) NOT IN (300,688)";
        $codesInfo  = $db->prepare($sql)->queryAll();
        $db->release();

        return $codesInfo;
    }

} 