<?php

namespace App\Api\Controllers;

use App\Common\Helpers\ResponseHelper;
use Mix\Http\Message\Response;
use Mix\Http\Message\ServerRequest;

/**
 * Class UpRankingController
 * @package App\Api\Controllers
 * @author alex <alexgaozhongze@gmail.com>
 */
class UpRankingController
{

    public function index(ServerRequest $request, Response $response)
    {
        $dbPool = context()->get('dbPool');
        $db     = $dbPool->getConnection();
        $sql    = "SELECT `code`,`type`,`date` FROM `hsab` WHERE `date`>=(SELECT MIN(`date`) FROM (SELECT `date` FROM `hsab` GROUP BY `date` ORDER BY `date` DESC LIMIT 18) AS `t`) AND LEFT(`code`,3) NOT IN (300,688) AND LEFT(`name`,1)='N' ORDER BY `date` DESC";
        $codes  = $db->prepare($sql)->queryAll();
        $db->release();

        foreach ($codes as $key => $value) {
            $sql    = "SELECT `price`,`up`,`name` FROM `hsab` WHERE `code`=$value[code] AND `type`=$value[type] ORDER BY `date` DESC LIMIT 3";
            $list   = $db->prepare($sql)->queryAll();

            $value['price'] = $list[0]['price'];
            $value['up']    = $list[0]['up'];
            $value['name']  = $list[0]['name'];
            $value['p1Up']  = isset($list[1]) ? $list[1]['up'] : '';
            $value['p2Up']  = isset($list[2]) ? $list[2]['up'] : '';

            $codes[$key]    = $value;
        }

        $db->release();

        $content = ['code' => 0, 'message' => 'OK', 'list' => $codes];
        return ResponseHelper::json($response, $content);
    }

}
