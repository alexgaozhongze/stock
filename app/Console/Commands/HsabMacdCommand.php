<?php

namespace App\Console\Commands;

use Swoole\Coroutine\Channel;

/**
 * Class HsabMacdCommand
 * @package Console\Commands
 * @author alex <alexgaozhongze@gmail.com>
 */
class HsabMacdCommand
{

    /**
     * 主函数
     */
    public function main()
    {
        $date = date('Y-m-d');

        $dbPool = context()->get('dbPool');
        $db     = $dbPool->getConnection();

        $codes_info = $db->prepare("SELECT `code`,`type`,`date` FROM `hsab` WHERE `date`='$date' AND `price` IS NOT NULL")->queryAll();
        $db->release();

        $chan = new Channel();
        foreach ($codes_info as $value) {
            $params = [
                'code'  => $value['code'],
                'type'  => $value['type'],
                'date'  => $value['date']
            ];
            xgo([$this, 'handle'], $chan, $params);
        }

        foreach ($codes_info as $code) {
            $chan->pop();
        }
    }

    public static function handle(Channel $chan, $params)
    {
        $connection = context()->get('dbPool')->getConnection();
        $pre_l_value = $connection->prepare("SELECT `dea`,`ema5`,`ema10`,`ema12`,`ema20`,`ema26`,`ema60` FROM `hsab` WHERE `code`=$params[code] AND `type`=$params[type] AND `date`<'$params[date]' AND `price` IS NOT NULL ORDER BY `date` DESC")->queryOne();
        $cur_l_value = $connection->prepare("SELECT `price` FROM `hsab` WHERE `code`=$params[code] AND `type`=$params[type] AND `date`='$params[date]'")->queryOne();

        $sp = $cur_l_value['price'];
        if ($pre_l_value) {
            $pre_ema12 = $pre_l_value['ema12'];
            $pre_ema26 = $pre_l_value['ema26'];
            $pre_ema5 = $pre_l_value['ema5'];
            $pre_ema10 = $pre_l_value['ema10'];
            $pre_ema20 = $pre_l_value['ema20'];
            $pre_ema60 = $pre_l_value['ema60'];
            $pre_dea = $pre_l_value['dea'];
        } else {
            $pre_ema12 = $pre_ema26 = $pre_ema5 = $pre_ema10 = $pre_ema20 = $pre_ema60 = $sp;
            $pre_dea = 0;
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

        $l_value['dif'] = round($dif, 2);
        $l_value['dea'] = round($dea, 2);
        $l_value['macd'] = round($macd, 2);
        $l_value['ema12'] = round($ema12, 2);
        $l_value['ema26'] = round($ema26, 2);
        $l_value['ema5'] = round($ema5, 2);
        $l_value['ema10'] = round($ema10, 2);
        $l_value['ema20'] = round($ema20, 2);
        $l_value['ema60'] = round($ema60, 2);

        $sql = "UPDATE `hsab` SET `dif`=$dif, `dea`=$dea, `macd`=$macd, `ema5`=$ema5, `ema10`=$ema10, `ema12`=$ema12, `ema20`=$ema20, `ema26`=$ema26, `ema60`=$ema60 WHERE `code`=$params[code] AND `type`=$params[type] AND `date`='$params[date]'";
        $connection->prepare($sql)->execute();

        $chan->push(0);
    }
}
