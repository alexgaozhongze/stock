<?php

namespace App\Api\Controllers;

use App\Common\Helpers\ResponseHelper;
use Mix\Http\Message\Response;
use Mix\Http\Message\ServerRequest;
use Swoole\Coroutine\Channel;

/**
 * Class UpTopController
 * @package App\Api\Controllers
 * @author alex <alexgaozhongze@gmail.com>
 */
class UpTopController
{

    /**
     * UpTopController constructor.
     * @param ServerRequest $request
     * @param Response $response
     */
    public function __construct(ServerRequest $request, Response $response)
    {
    }

    public function index(ServerRequest $request, Response $response)
    {
        // $post = $request->getParsedBody();

        $dates  = dates(19 + 9);
        $dbPool = context()->get('dbPool');
        $db     = $dbPool->getConnection();
        $sql    = "SELECT `code`,`type` FROM `hsab` WHERE `date`=CURDATE() AND `price` IS NOT NULL AND LEFT(`name`,1) NOT IN ('*','S') AND LEFT(`code`,3) NOT IN (300,688)";
        $codes  = $db->prepare($sql)->queryAll();
        $db->release();

        $chan = new Channel();
        foreach ($codes as $value) {
            $params = [
                'code'  => $value['code'],
                'type'  => $value['type'],
                'sDate' => reset($dates)
            ];
            xgo([$this, 'handle'], $chan, $params);
        }

        $list       = [];
        $riseList   = [];
        foreach ($codes as $value) {
            $result = $chan->pop();
            if ($result) {
                $riseList[] = [
                    'code'  => end($result)['code'],
                    'type'  => end($result)['type'],
                    'rise'  => end($result)['rise']
                ];
                $list = array_merge($list, $result);
            }
        }

        $sort = array_column($riseList, 'rise');
        array_multisort($sort, SORT_DESC, $riseList);

        $responseList = [];
        foreach ($riseList as $value) {
            foreach ($list as $lValue) {
                $value['code'] == $lValue['code'] && $value['type'] == $lValue['type'] && $responseList[] = $lValue;
            }
        }

        $db->release();

        $content = ['code' => 0, 'message' => 'OK', 'list' => $responseList];
        return ResponseHelper::json($response, $content);
    }

    public function handle(Channel $chan, $params)
    {
        $dbPool = context()->get('dbPool');
        $db     = $dbPool->getConnection();
        $result = $db->prepare("SELECT * FROM `hsab` WHERE `code`=$params[code] AND `type`=$params[type] AND `date`>='$params[sDate]'")->queryAll();

        foreach ($result as $key => $value) {
            if (18 >= $key) continue;

            $rise = 0;
            for ($i = 18; $i > 0; $i --) {
                if ($result[$key - $i]['zs']) {
                    $pre18  = $result[$key - $i];
                    $rise   = $pre18['zs'] ? bcdiv($value['price'], $pre18['zs'], 9) : 0;
                    break;
                }
            }
            $value['rise']  = $rise;

            $result[$key]   = $value;
        }

        if (isset($value['rise']) && 1.99999999 <= $value['rise']) {
            $count = count($result);
            for ($i = 0; $i < $count - 9; $i ++) {
                unset($result[$i]);
            }
            $chan->push($result);
        } else {
            $chan->push(false);
        }
    }
}
