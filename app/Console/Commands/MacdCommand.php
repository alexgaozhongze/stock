<?php

namespace App\Console\Commands;

use QL\QueryList;
use GuzzleHttp\Psr7\Response;

/**
 * Class MacdCommand
 * @package Console\Commands
 * @author alex <alexgaozhongze@gmail.com>
 */
class MacdCommand
{

    /**
     * 主函数
     */
    public function main()
    {
        xgo(function () {
            if (!checkOpen()) return false;
            if (time() < strtotime('09:15:03') || time() > strtotime('15:51:06')) return false;
            if (time() < strtotime('12:57:09') && time() > strtotime('11:31:09')) return false;

            self::handle();
        });
    }

    public static function handle()
    {
        $connection = context()->get('dbPool')->getConnection();

        if (time() < strtotime('15:00:00')) {
            $sql = "SELECT `code`,`type` FROM `hsab` WHERE `date`=CURDATE() AND `zg`=`zt` GROUP BY `code`";
        } else {
            $sql = "SELECT `code`,`type` FROM `hsab` WHERE `date`=CURDATE() AND `price` IS NOT NULL GROUP BY `code`";
        }
        $codes = $connection->prepare($sql)->queryAll();

        $urls = [];
        array_walk($codes, function($item) use (&$urls) {
            $ut     = md5(time());
            $date   = date('Ymd');
            $sec    = 2 == $item['type'] ? 0 : 1;
            $secid  = $sec . '.' . str_pad($item['code'], 6, "0", STR_PAD_LEFT);
            $urls[] = "http://push2his.eastmoney.com/api/qt/stock/kline/get?fields1=f1,f2,f3,f4,f5,f6,f7,f8,f9,f10,f11,f12,f13&fields2=f51,f52,f53,f54,f55,f56,f57,f58,f59,f60,f61&beg=$date&end=20500101&ut=$ut&rtntype=6&secid=$secid&klt=5&fqt=1&cb=";
        });

        QueryList::multiGet($urls)
            ->concurrency(8)
            ->withOptions([
                'timeout' => 3
            ])
            ->success(function(QueryList $ql, Response $response, $index) {
                $connection = context()->get('dbPool')->getConnection();

                $response_json = $ql->getHtml();

                $item = json_decode($response_json, true);
                $data = $item['data']['klines'] ?? [];
                $info = $item['data'] ?? [];

                if (!$info) return 1;

                $sql_fields = "INSERT IGNORE INTO `macd` (`code`, `kp`, `sp`, `zg`, `zd`, `cjl`, `cje`, `zf`, `up`, `upp`, `hsl`, `time`, `type`, `dif`, `dea`, `macd`, `ema12`, `ema26` ,`ema5`, `ema10`, `ema20`, `ema60`) VALUES ";
                $sql_values = "";

                $code = $item['data']['code'];
                $type = $info['market'] == 0 ? 2 : 1;

                $pre_l_value = [];
                array_walk($data, function($iitem) use (&$sql_values, &$pre_l_value, $code, $type, $connection) {
                    list($time, $kp, $sp, $zg, $zd, $cjl, $cje, $zf, $up, $upp, $hsl) = explode(',', $iitem);
                    $zf = floatval($zf);

                    $sql_values && $sql_values .= ',';
                    
                    if ($pre_l_value) {
                        $pre_ema12  = $pre_l_value['ema12'];
                        $pre_ema26  = $pre_l_value['ema26'];
                        $pre_ema5   = $pre_l_value['ema5'];
                        $pre_ema10  = $pre_l_value['ema10'];
                        $pre_ema20  = $pre_l_value['ema20'];
                        $pre_ema60  = $pre_l_value['ema60'];
                        $pre_dea    = $pre_l_value['dea'];
                    } else {
                        $sql = "SELECT `ema12`,`ema26`,`ema5`,`ema10`,`ema20`,`ema60`,`dea` FROM `macd` WHERE `code`=$code AND `type`=$type AND `time`<'$time' ORDER BY `time` DESC";
                        $info = $connection->prepare($sql)->queryOne();

                        if ($info) {
                            $pre_ema12  = $info['ema12'];
                            $pre_ema26  = $info['ema26'];
                            $pre_ema5   = $info['ema5'];
                            $pre_ema10  = $info['ema10'];
                            $pre_ema20  = $info['ema20'];
                            $pre_ema60  = $info['ema60'];
                            $pre_dea    = $info['dea'];
                        } else {
                            $pre_ema12  = $pre_ema26 = $pre_ema5 = $pre_ema10 = $pre_ema20 = $pre_ema60 = $sp;
                            $pre_dea    = 0;
                        }
                    }

                    $ema12  = 2 / (12 + 1) * $sp + (12 - 1) / (12 + 1) * $pre_ema12;
                    $ema26  = 2 / (26 + 1) * $sp + (26 - 1) / (26 + 1) * $pre_ema26;
                    $ema5   = 2 / (5  + 1) * $sp + (5  - 1) / (5  + 1) * $pre_ema5;
                    $ema10  = 2 / (10 + 1) * $sp + (10 - 1) / (10 + 1) * $pre_ema10;
                    $ema20  = 2 / (20 + 1) * $sp + (20 - 1) / (20 + 1) * $pre_ema20;
                    $ema60  = 2 / (60 + 1) * $sp + (60 - 1) / (60 + 1) * $pre_ema60;

                    $dif    = $ema12 - $ema26;
                    $dea    = 2 / (9 + 1) * $dif + (9 - 1) / (9 + 1) * $pre_dea;
                    $macd   = 2 * ($dif - $dea);

                    $l_value['dif']     = round($dif, 2);
                    $l_value['dea']     = round($dea, 2);
                    $l_value['macd']    = round($macd, 2);
                    $l_value['ema12']   = round($ema12, 2);
                    $l_value['ema26']   = round($ema26, 2);
                    $l_value['ema5']    = round($ema5, 2);
                    $l_value['ema10']   = round($ema10, 2);
                    $l_value['ema20']   = round($ema20, 2);
                    $l_value['ema60']   = round($ema60, 2);

                    $pre_l_value = $l_value;

                    $sql_values .= "($code, $kp, $sp, $zg, $zd, $cjl, $cje, $zf, $up, $upp, $hsl, '$time', $type, $l_value[dif], $l_value[dea], $l_value[macd], $l_value[ema12], $l_value[ema26], $l_value[ema5], $l_value[ema10], $l_value[ema20], $l_value[ema60])";
                });

                if ($sql_values) {
                    $sql_duplicate = " ON DUPLICATE KEY UPDATE `kp`=VALUES(`kp`), `sp`=VALUES(`sp`), `zg`=VALUES(`zg`), `zd`=VALUES(`zd`), `cjl`=VALUES(`cjl`), `cje`=VALUES(`cje`), `zf`=VALUES(`zf`), `up`=VALUES(`up`), `upp`=VALUES(`upp`), `hsl`=VALUES(`hsl`), `dif`=VALUES(`dif`), `dea`=VALUES(`dea`), `macd`=VALUES(`macd`), `ema12`=VALUES(`ema12`), `ema26`=VALUES(`ema26`), `ema5`=VALUES(`ema5`), `ema10`=VALUES(`ema10`), `ema20`=VALUES(`ema20`), `ema60`=VALUES(`ema60`);";

                    $sql = $sql_fields . $sql_values . $sql_duplicate;
                    $connection->prepare($sql)->execute();
                }
            })->error(function (QueryList $ql, $reason, $index){
                // ...
            })->send();

        return 0;
    }

}
