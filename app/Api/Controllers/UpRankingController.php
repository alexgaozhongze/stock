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
        $sql    = "SELECT `code`,`type`,`price`,`up`,`name`,`ema60` FROM `hsab` WHERE LEFT(`code`,3) NOT IN (300,688) AND `date`=(SELECT MAX(`date`) FROM `hsab`) AND `price` IS NOT NULL AND LEFT(`name`,1) NOT IN ('*','S')";
        $codes  = $db->prepare($sql)->queryAll();
        $db->release();

        foreach ($codes as $key => $value) {
            $rise   = bcdiv($value['price'], $value['ema60'], 2);

            if (1.23456789 < $rise) {
                $codes[$key]['rise']    = $rise;
            } else {
                unset($codes[$key]);
            }
        }

        $sort = array_column($codes, 'rise');
        array_multisort($sort, SORT_DESC, $codes);

        $db->release();

        $content = ['code' => 0, 'message' => 'OK', 'list' => $codes];
        return ResponseHelper::json($response, $content);
    }

}
