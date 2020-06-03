<?php

namespace App\Console\Commands;

use QL\QueryList;
use GuzzleHttp\Psr7\Response;
use Mix\Concurrent\Timer;

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
            if (time() < strtotime('09:15:00') || time() > strtotime('15:08:09')) return false;
            if (time() < strtotime('13:00:00') && time() > strtotime('11:38:09')) return false;

            list($microstamp, $timestamp) = explode(' ', microtime());
            $timestamp = "$timestamp" . intval($microstamp * 1000);

            $ql = QueryList::get("http://nufm.dfcfw.com/EM_Finance2014NumericApplication/JS.aspx?type=ct&st=(ChangePercent)&sr=-1&p=1&ps=1&js={%22pages%22:(pc),%22data%22:[(x)]}&token=894050c76af8597a853f5b408b759f5d&cmd=C._AB&sty=DCFFITA&rt=$timestamp");
    
            $json_data = $ql->getHtml();
            $data = json_decode($json_data, true);
            $datas = $data['data'] ?? false;
            $pages = $data['pages'] ?? 0;
            if (!$datas) return false;

            $info = reset($datas);
            $date = explode(',', $info)[15];
            if (date('Y-m-d', strtotime($date)) != date('Y-m-d')) return false;

            $ql = QueryList::get("http://50.push2.eastmoney.com/api/qt/clist/get?pn=1&pz=1&po=1&np=1&ut=bd1d9ddb04089700cf9c27f6f7426281&fltt=2&invt=2&fid=f3&fs=m:0+t:6,m:0+t:13,m:0+t:80,m:1+t:2&fields=f2,f3,f4,f5,f6,f7,f8,f9,f10,f12,f13,f14,f15,f16,f17,f18,f23&_=$timestamp");
        
            $json_data = $ql->getHtml();
            $data = json_decode($json_data, true);

            $pages = $data['data']['total'] ?? 0;
            if (!$datas) return false;

            $timer = new Timer();
            $timer->tick(18899, function () use ($pages) {
                self::handle($pages);
                echo date('Y-m-d H:i:s'), PHP_EOL;
            });

            Timer::new()->after(58899, function () use ($timer) {
                $timer->clear();
            });
        });
    }

    public function handle($pages)
    {
        list($microstamp, $timestamp) = explode(' ', microtime());
        $timestamp = "$timestamp" . intval($microstamp * 1000);

        $rand = rand(8,9);
        $page_size = $rand * 11;
        $page_count = ceil($pages / $page_size);
        for ($i = 1; $i <= $page_count; $i ++) {
            $urls[] = "http://$page_size.push2.eastmoney.com/api/qt/clist/get?pn=$i&pz=$page_size&po=1&np=1&ut=bd1d9ddb04089700cf9c27f6f7426281&fltt=2&invt=2&fid=f3&fs=m:0+t:6,m:0+t:13,m:0+t:80,m:1+t:2&fields=f2,f3,f4,f5,f6,f7,f8,f9,f10,f12,f13,f14,f15,f16,f17,f18,f23&_=$timestamp";
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

                    $sql_values .= "($value[f12], $value[f2], $value[f3], $value[f4], $zt, $dt, $value[f5], $value[f6], $value[f7], $value[f15], $value[f16], $value[f17], $value[f18], $value[f10], $value[f8], $value[f9], $value[f23], '$date', '$value[f14]', $type)";
                }
        
                $sql_duplicate = " ON DUPLICATE KEY UPDATE `price`=VALUES(`price`), `up`=VALUES(`up`), `upp`=VALUES(`upp`), `cjs`=VALUES(`cjs`), `cje`=VALUES(`cje`), `zf`=VALUES(`zf`), `zg`=VALUES(`zg`), `zd`=VALUES(`zd`), `jk`=VALUES(`jk`), `lb`=VALUES(`lb`), `hsl`=VALUES(`hsl`), `syl`=VALUES(`syl`), `sjl`=VALUES(`sjl`);";
        
                $sql = $sql_fields . $sql_values . $sql_duplicate;
                $connection->prepare($sql)->execute();
        
            })->error(function (QueryList $ql, $reason, $index){
                // ...
            })->send();

    }

}
