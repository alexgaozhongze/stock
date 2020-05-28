<?php

namespace App\Console\Models;

use QL\QueryList;
use GuzzleHttp\Psr7\Response;

/**
 * Class MacdModel
 * @package Console\Models
 * @author alex <alexgaozhongze@gmail.com>
 */
class MacdModel
{

    public static function sync()
    {
        if (!checkOpen()) return false;

        $dbPool = context()->get('dbPool');
        $db     = $dbPool->getConnection();

        $table_name = 'hq_' . date('Ymd');
        $sql = "SELECT `code`,`type` FROM `hsab` AS `a` WHERE `date`=CURDATE() AND `price` IS NOT NULL GROUP BY `code`";
        $codes = $db->prepare($sql)->queryAll();
        $db->release();

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
            ->success(function(QueryList $ql, Response $response, $index) use ($table_name) {
                $response_json = $ql->getHtml();

                $item = json_decode($response_json, true);
                $data = $item['data']['data'] ?? [];
                $info = $item['data']['info'] ?? [];

                $sql_fields = "INSERT IGNORE INTO `macd` (`code`, `kp`, `sp`, `zg`, `zd`, `cjl`, `cje`, `zf`, `time`, `type`, `dif`, `dea`, `macd`, `ema12`, `ema26` ,`ema5`, `ema10`, `ema20`, `ema60`) VALUES ";
                $sql_values = "";
    
                $code = $item['data']['code'];
                $type = $info['mk'];

                $pre_l_value = [];
                array_walk($data, function($iitem) use (&$sql_values, &$pre_l_value, $code, $type) {
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
                            $dbPool = context()->get('dbPool');
                            $db     = $dbPool->getConnection();

                            $sql = "SELECT `ema12`,`ema26`,`ema5`,`ema10`,`ema20`,`ema60`,`dea` FROM `macd` WHERE `code`=$code AND `type`=$type AND `time`<'$time' ORDER BY `time` DESC";
                            $info = $db->prepare($sql)->queryOne();
                            $db->release();

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
                    $dbPool = context()->get('dbPool');
                    $db     = $dbPool->getConnection();
                    $db->prepare($sql)->execute();
                    $db->release();
                }
            })->error(function (QueryList $ql, $reason, $index){
                // ...
            })->send();
    }

}
