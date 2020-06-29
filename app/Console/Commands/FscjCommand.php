<?php

namespace App\Console\Commands;

use QL\QueryList;
use GuzzleHttp\Psr7\Response;
use App\Common\Models\CommonModel;

/**
 * Class FscjCommand
 * @package Console\Commands
 * @author alex <alexgaozhongze@gmail.com>
 */
class FscjCommand
{

    /**
     * 主函数
     */
    public function main()
    {
        xgo(function () {
            if (time() < strtotime('09:15:00') || time() > strtotime('15:08:09')) return false;
            if (time() < strtotime('13:00:00') && time() > strtotime('11:38:09')) return false;
            if (!checkOpen()) return false;
            
            $connection = context()->get('dbPool')->getConnection();
            $table_name = "fscj_" . date('Ymd');
            $sql = "CREATE TABLE IF NOT EXISTS `$table_name` (
                `code` mediumint(6) unsigned zerofill NOT NULL,
                `price` decimal(6,2) DEFAULT NULL,
                `up` decimal(5,2) DEFAULT NULL,
                `num` int(11) unsigned DEFAULT NULL,
                `bs` char(2) NOT NULL,
                `time` time NOT NULL,
                `type` char(1) NOT NULL,
                PRIMARY KEY (`code`,`type`,`time`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8";
            $connection->prepare($sql)->execute();

            self::handle();
            echo date('Y-m-d H:i:s'), PHP_EOL;
        });
    }

    public static function handle()
    {
        $connection = context()->get('dbPool')->getConnection();

        $fscj_table = 'fscj_' . date('Ymd');
        $sql = "SELECT `a`.`code`,`a`.`type`,COUNT(`b`.`code`) AS `count` FROM `hsab` AS `a` LEFT JOIN `$fscj_table` AS `b` ON `a`.`code`=`b`.`code` AND `a`.`type`=`b`.`type` WHERE `a`.`date`=CURDATE() GROUP BY `code`";

        $code_times = $connection->prepare($sql)->queryAll();
        if (!$code_times) return false;

        list($microstamp, $timestamp) = explode(' ', microtime());
        $timestamp = "$timestamp" . intval($microstamp * 1000);

        $urls = [];
        array_walk($code_times, function($item) use (&$urls, $timestamp) {
            $page_size = 198;
            $page = ceil(($item['count'] + 1) / $page_size);
            $page_index = $page - 1;

            $market = 1 == $item['type'] ? 1 : 0;
            $code = str_pad($item['code'], 6, "0", STR_PAD_LEFT);
            $key = $code . $item['type'];

            $urls[] = "http://push2ex.eastmoney.com/getStockFenShi?pagesize=$page_size&ut=7eea3edcaed734bea9cbfc24409ed989&dpt=wzfscj&cb=&pageindex=$page_index&id=$key&sort=1&ft=1&code=$code&market=$market&_=$timestamp";
        });

        $code_type = array_column($code_times, 'type', 'code');
        QueryList::multiGet($urls)
            ->concurrency(8)
            ->withOptions([
                'timeout' => 3
            ])
            ->success(function(QueryList $ql, Response $response, $index) use ($fscj_table, $code_type) {
                $data = $ql->getHtml();

                $item = json_decode($data, true);
                $code = $item['data']['c'];
                $type = $code_type[intval($code)];
                $cp = $item['data']['cp'] ?? 0;
                $datas = $item['data']['data'] ?? [];
    
                $sql_fields = "INSERT IGNORE INTO `$fscj_table` (`code`, `price`, `up`, `num`, `bs`, `time`, `type`) VALUES ";
                $sql_values = "";
    
                array_walk($datas, function($iitem) use (&$sql_values, $cp, $code, $type) {
                    $time = $iitem['t'];
                    $price = $iitem['p'] / 1000;
                    $num = $iitem['v'];
                    $bs = $iitem['bs'];
                    $pc = $cp / 1000;

                    $sql_values && $sql_values .= ',';
                    $up = bcmul(bcdiv(bcsub($price, $pc, 2), $pc, 4), 100, 2);
    
                    $sql_values .= "('$code', $price, $up, $num, $bs, '$time', $type)";
                });
    
                if ($sql_values) {
                    $connection = context()->get('dbPool')->getConnection();

                    $sql = $sql_fields . $sql_values;
                    $connection->prepare($sql)->execute();
                }
            })->error(function (QueryList $ql, $reason, $index){
                // ...
            })->send();
    }

}
