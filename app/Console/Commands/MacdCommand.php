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
            if (time() < strtotime('09:15:03') || time() > strtotime('15:30:09')) return false;
            if (time() < strtotime('12:57:09') && time() > strtotime('11:31:09')) return false;

            self::handle();
        });
    }

    public static function handle()
    {
        $connection = context()->get('dbPool')->getConnection();

        if (time() < strtotime('15:00:00')) {
            $sql = "SELECT `code`,`type` FROM `hsab` WHERE `date`=CURDATE() AND `price`=`zt` GROUP BY `code`";
        } else {
            $sql = "SELECT `code`,`type` FROM `hsab` WHERE `date`=CURDATE() AND `price` IS NOT NULL GROUP BY `code`";
        }
        $codes = $connection->prepare($sql)->queryAll();

        list($microstamp, $timestamp) = explode(' ', microtime());
        $timestamp = "$timestamp" . intval($microstamp * 1000);

        $urls = [];
        array_walk($codes, function($item) use (&$urls, $timestamp) {
            $key = str_pad($item['code'], 6, "0", STR_PAD_LEFT) . $item['type'];
            $urls[] = "http://pdfm.eastmoney.com/EM_UBG_PDTI_Fast/api/js?token=4f1862fc3b5e77c150a2b985b12db0fd&rtntype=6&id=$key&type=m5k&authorityType=&js={%22data%22:(x)}";
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
                $data = $item['data']['data'] ?? [];
                $info = $item['data']['info'] ?? [];

                if (!$info) return 1;

                $sql_fields = "INSERT IGNORE INTO `macd` (`code`, `kp`, `sp`, `zg`, `zd`, `cjl`, `cje`, `zf`, `time`, `type`, `dif`, `dea`, `macd`, `ema12`, `ema26` ,`ema5`, `ema10`, `ema20`, `ema60`) VALUES ";
                $sql_values = "";

                $code = $item['data']['code'];
                $type = $info['mk'];

                $pre_l_value = [];
                array_walk($data, function($iitem) use (&$sql_values, &$pre_l_value, $code, $type, $connection) {
                    list($time, $kp, $sp, $zg, $zd, $cjl, $cje, $zf) = explode(',', $iitem);
                    $zf = floatval($zf);

                    if (date('Y-m-d') == date('Y-m-d', strtotime($time)) && in_array(substr($time, -1), ['0','5']) && '09:30' != substr($time, 11, 5)) {
                        $sql_values && $sql_values .= ',';

                        if ($pre_l_value) {
                            $pre_ema12 = $pre_l_value['ema12'];
                            $pre_ema26 = $pre_l_value['ema26'];
                            $pre_ema5 = $pre_l_value['ema5'];
                            $pre_ema10 = $pre_l_value['ema10'];
                            $pre_ema20 = $pre_l_value['ema20'];
                            $pre_ema60 = $pre_l_value['ema60'];
                            $pre_dea = $pre_l_value['dea'];
                        } else {
                            $sql = "SELECT `ema12`,`ema26`,`ema5`,`ema10`,`ema20`,`ema60`,`dea` FROM `macd` WHERE `code`=$code AND `type`=$type AND `time`<'$time' ORDER BY `time` DESC";
                            $info = $connection->prepare($sql)->queryOne();

                            if ($info) {
                                $pre_ema12 = $info['ema12'];
                                $pre_ema26 = $info['ema26'];
                                $pre_ema5 = $info['ema5'];
                                $pre_ema10 = $info['ema10'];
                                $pre_ema20 = $info['ema20'];
                                $pre_ema60 = $info['ema60'];
                                $pre_dea = $info['dea'];
                            } else {
                                $pre_ema12 = $pre_ema26 = $pre_ema5 = $pre_ema10 = $pre_ema20 = $pre_ema60 = $sp;
                                $pre_dea = 0;
                            }
                        }

                        $ema12 = 2 / (12 + 1) * $sp + (12 - 1) / (12 + 1) * $pre_ema12;
                        $ema26 = 2 / (26 + 1) * $sp + (26 - 1) / (26 + 1) * $pre_ema26;
                        $ema5 = 2 / (5 + 1) * $sp + (5 - 1) / (5 + 1) * $pre_ema5;
                        $ema10 = 2 / (10 + 1) * $sp + (10 - 1) / (10 + 1) * $pre_ema10;
                        $ema20 = 2 / (20 + 1) * $sp + (20 - 1) / (20 + 1) * $pre_ema20;
                        $ema60 = 2 / (60 + 1) * $sp + (60 - 1) / (60 + 1) * $pre_ema60;
    
                        $dif = $ema12 - $ema26;
                        $dea = 2 / (9 + 1) * $dif + (9 - 1) / (9 + 1) * $pre_dea;
                        $macd = 2 * ($dif - $dea);
    
                        $l_value['dif'] = round($dif, 3);
                        $l_value['dea'] = round($dea, 3);
                        $l_value['macd'] = round($macd, 3);
                        $l_value['ema12'] = round($ema12, 3);
                        $l_value['ema26'] = round($ema26, 3);
                        $l_value['ema5'] = round($ema5, 3);
                        $l_value['ema10'] = round($ema10, 3);
                        $l_value['ema20'] = round($ema20, 3);
                        $l_value['ema60'] = round($ema60, 3);

                        $pre_l_value = $l_value;

                        $sql_values .= "($code, $kp, $sp, $zg, $zd, $cjl, $cje, $zf, '$time', $type, $l_value[dif], $l_value[dea], $l_value[macd], $l_value[ema12], $l_value[ema26], $l_value[ema5], $l_value[ema10], $l_value[ema20], $l_value[ema60])";
                    }
                });

                if ($sql_values) {
                    $sql = $sql_fields . $sql_values;
                    $connection->prepare($sql)->execute();
                }
            })->error(function (QueryList $ql, $reason, $index){
                // ...
            })->send();

        return 0;
    }

}
