<?php

namespace App\Console\Commands;

use GuzzleHttp\Psr7\Response;
use Mix\Concurrent\Timer;
use QL\QueryList;

/**
 * Class HsabCommand
 * @package Console\Commands
 * @author alex <alexgaozhongze@gmail.com>
 */
class HsabCommand
{
        
    /*
     * f2 price 最新价
     * f3 up 涨跌幅
     * f4 upp 涨跌额
     * f5 cjs 成交手
     * f6 cje 成交额
     * f7 zf 振幅
     * f8 hsl 换手率
     * f9 syl 市盈率
     * f10 lb 量比
     * 
     * 
     * f12 code
     * f13 type
     * f14 name
     * f15 zg 最高
     * f16 zd 最低
     * f17 jk 今开
     * f18 zs 昨收
     * f23 sjl 市净率
     */

    /**
     * 主函数
     */
    public function main()
    {
        xgo(function () {
            if (time() < strtotime('09:09:09') || time() > strtotime('15:15:15')) return false;
            if (time() < strtotime('12:58:59') && time() > strtotime('11:32:23')) return false;

            list($microstamp, $timestamp) = explode(' ', microtime());
            $timestamp = "$timestamp" . intval($microstamp * 1000);
            $date = date('Ymd');

            $ql = QueryList::get("http://push2his.eastmoney.com/api/qt/stock/kline/get?fields1=f1&fields2=f61&beg=$date&end=$date&ut=$timestamp&rtntype=6&secid=1.000001&klt=5&fqt=1&cb=");
            $json_data = $ql->getHtml();
            $data = json_decode($json_data, true);
            if (!$data['data']['klines']) return false;

            $ql = QueryList::get("http://$timestamp.push2.eastmoney.com/api/qt/clist/get?pn=1&pz=1&po=1&np=1&ut=bd1d9ddb04089700cf9c27f6f7426281&fltt=2&invt=2&fid=f3&fs=m:0+t:6,m:0+t:13,m:0+t:80,m:1+t:2&fields=f2,f3,f4,f5,f6,f7,f8,f9,f10,f12,f13,f14,f15,f16,f17,f18,f23&_=$timestamp");
        
            $json_data = $ql->getHtml();
            $data = json_decode($json_data, true);

            $total = $data['data']['total'] ?? 0;
            if (!$total) return false;

            $info = reset($data['data']['diff']);
            $code = $info['f12'];
            $type = $info['f13'];
            $price = $info['f2'];
            $cjs = $info['f5'];

            $connection = context()->get('dbPool')->getConnection();
            $sql = "SELECT `price`,`cjs` FROM `hsab` WHERE `code`=$code AND `type`=$type ORDER BY `date` DESC";
            $existsInfo = $connection->prepare($sql)->queryOne();
            if ($existsInfo && $price == $existsInfo['price'] && $cjs == $existsInfo['cjs']) return false;

            $timer = new Timer();
            $timer->tick(15999, function () use ($total) {
                self::handle($total);
            });

            Timer::new()->after(189999, function () use ($timer) {
                $timer->clear();
            });
        });
    }

    public static function handle($total)
    {
        list($microstamp, $timestamp) = explode(' ', microtime());
        $timestamp = "$timestamp" . intval($microstamp * 1000);

        $rand = rand(8,9);
        $page_size = $rand * 11;
        $page_count = ceil($total / $page_size);
        for ($i = 1; $i <= $page_count; $i ++) {
            $urls[] = "http://$timestamp.push2.eastmoney.com/api/qt/clist/get?pn=$i&pz=$page_size&po=1&np=1&ut=bd1d9ddb04089700cf9c27f6f7426281&fltt=2&invt=2&fid=f3&fs=m:0+t:6,m:0+t:13,m:0+t:80,m:1+t:2&fields=f2,f3,f4,f5,f6,f7,f8,f9,f10,f12,f13,f14,f15,f16,f17,f18,f23&_=$timestamp";
        }

        QueryList::multiGet($urls)
            ->concurrency(8)
            ->withOptions([
                'timeout' => 3
            ])
            ->success(function(QueryList $ql, Response $response, $index) {
                $connection = context()->get('dbPool')->getConnection();
                $date = date('Y-m-d');

                $json_data = $ql->getHtml();
                $data = json_decode($json_data, true);
                $datas = $data['data']['diff'] ?? false;
                if (!$datas) return false;

                $sql_fields = "INSERT INTO `hsab` (`code`, `price`, `up`, `upp`, `zt`, `dt`, `cjs`, `cje`, `zf`, `zg`, `zd`, `jk`, `zs`, `lb`, `hsl`, `syl`, `sjl`, `date`, `name`, `type`) VALUES ";
                $sql_values = "";

                foreach ($datas as $value) {
                    foreach ($value as $v_key => $v_value) {
                        '-' === $v_value && $value[$v_key] = 'NULL';
                    }

                    $sql_values && $sql_values .= ',';
                    $type = $value['f13'] ?: 2;
                    if ('NULL' == $value['f18']) {
                        $zt = $dt = 'NULL';
                    } else {
                        if (false !== strpos($value['f14'], 'ST')) {
                            $rate = 0.05;
                        } else {
                            $rate = 0.1;
                        }
                        $zt = round($value['f18'] * (1 + $rate), 2);
                        $dt = round($value['f18'] * (1 - $rate), 2);
                    }

                    $value['f10'] >= 9999.99 && $value['f10'] = 9999.99;

                    $sql_values .= "($value[f12], $value[f2], $value[f3], $value[f4], $zt, $dt, $value[f5], $value[f6], $value[f7], $value[f15], $value[f16], $value[f17], $value[f18], $value[f10], $value[f8], $value[f9], $value[f23], '$date', '$value[f14]', $type)";
                }
        
                $sql_duplicate = " ON DUPLICATE KEY UPDATE `price`=VALUES(`price`), `up`=VALUES(`up`), `upp`=VALUES(`upp`), `zt`=VALUES(`zt`), `dt`=VALUES(`dt`), `cjs`=VALUES(`cjs`), `cje`=VALUES(`cje`), `zf`=VALUES(`zf`), `zg`=VALUES(`zg`), `zd`=VALUES(`zd`), `jk`=VALUES(`jk`), `lb`=VALUES(`lb`), `hsl`=VALUES(`hsl`), `syl`=VALUES(`syl`), `sjl`=VALUES(`sjl`), `name`=VALUES(`name`);";
        
                $sql = $sql_fields . $sql_values . $sql_duplicate;
                $connection->prepare($sql)->execute();
            })->error(function (QueryList $ql, $reason, $index){
                // ...
            })->send();
    }

}
