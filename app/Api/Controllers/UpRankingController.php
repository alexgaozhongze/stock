<?php

namespace App\Api\Controllers;

use App\Common\Helpers\ResponseHelper;
use Mix\Http\Message\Response;
use Mix\Http\Message\ServerRequest;
use Swoole\Coroutine\Channel;

/**
 * Class UpRankingController
 * @package App\Api\Controllers
 * @author alex <alexgaozhongze@gmail.com>
 */
class UpRankingController
{

    /**
     * UpRankingController constructor.
     * @param ServerRequest $request
     * @param Response $response
     */
    public function __construct(ServerRequest $request, Response $response)
    {
    }

    public function index(ServerRequest $request, Response $response)
    {
        // $post = $request->getParsedBody();

        $dates = dates(18);
        $startDate = reset($dates);
        $endDate = end($dates);

        $dbPool = context()->get('dbPool');
        $db     = $dbPool->getConnection();
        $sql    = "SELECT `code`,`type` FROM `hsab` WHERE `date`='$endDate' AND `price` IS NOT NULL AND LEFT(`name`,1) NOT IN ('*','S','退') AND LEFT(`code`,3) NOT IN (300,688) GROUP BY `code`";
        $codes  = $db->prepare($sql)->queryAll();
        $db->release();

        $chan = new Channel();
        foreach ($codes as $value) {
            $params = [
                'code'  => $value['code'],
                'type'  => $value['type'],
                'sDate' => $startDate,
                'eDate' => $endDate
            ];
            xgo([$this, 'handle'], $chan, $params);
        }

        $list = [];
        foreach ($codes as $value) {
            $result = $chan->pop();
            $result && $list[] = $result;
        }

        $sort = array_column($list, 'rise');
        array_multisort($sort, SORT_DESC, $list);

        $db = $dbPool->getConnection();
        $responseList = [];
        foreach ($list as $value) {
            if (1.99999999 > $value['rise']) break;
            $sql        = "SELECT * FROM `hsab` WHERE `code`=$value[code] AND `type`=$value[type] AND `date`>='$startDate'";
            $codesList  = $db->prepare($sql)->queryAll();
            foreach ($codesList as $key => $clValue) {
                $codesList[$key]['rise'] = $value['rise'];
            }
            $responseList = array_merge($responseList, $codesList);
        }
        $db->release();

        $content = ['code' => 0, 'message' => 'OK', 'list' => $responseList];
        return ResponseHelper::json($response, $content);
    }

    public function handle(Channel $chan, $params)
    {
        $dbPool = context()->get('dbPool');
        $db     = $dbPool->getConnection();
        $sInfo  = $db->prepare("SELECT `zs` FROM `hsab` WHERE `code`=$params[code] AND `type`=$params[type] AND `date`>='$params[sDate]' AND `price` IS NOT NULL ORDER BY `date` ASC")->queryOne();
        $eInfo  = $db->prepare("SELECT `price` FROM `hsab` WHERE `code`=$params[code] AND `type`=$params[type] AND `date`<='$params[eDate]' AND `price` IS NOT NULL ORDER BY `date` DESC")->queryOne();

        if ($sInfo && $eInfo) {
            $pre = bcdiv($eInfo['price'], $sInfo['zs'], 8);
            $chan->push([
                'code'  => $params['code'],
                'type'  => $params['type'],
                'rise'   => $pre
            ]);
        } else {
            $chan->push(false);
        }
    }

}
