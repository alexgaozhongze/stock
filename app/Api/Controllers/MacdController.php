<?php

namespace App\Api\Controllers;

use App\Common\Helpers\ResponseHelper;
use Mix\Http\Message\Response;
use Mix\Http\Message\ServerRequest;
use Swoole\Coroutine\Channel;

/**
 * Class MacdController
 * @package App\Api\Controllers
 * @author alex <alexgaozhongze@gmail.com>
 */
class MacdController
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
        $code   = $request->getAttribute('code');
        $type   = $request->getAttribute('type');

        $dates  = dates(36);
        $sDate  = reset($dates);
        
        $dbPool = context()->get('dbPool');
        $db     = $dbPool->getConnection();
        $sql    = "SELECT `kp`,`sp`,`zg`,`zd`,`time`,`ema5`,`ema10`,`ema20`,`ema60` FROM `macd` WHERE `code`=$code AND `type`=$type AND `time`>='$sDate'";
        $list   = $db->prepare($sql)->queryAll();

        $content = [];
        foreach ($list as $value) {
            $content[] = [
                $value['time'],
                floatval($value['kp']),
                floatval($value['sp']),
                floatval($value['zd']),
                floatval($value['zg']),
                floatval($value['ema5']),
                floatval($value['ema10']),
                floatval($value['ema20']),
                floatval($value['ema60']),
            ];
        }

        return ResponseHelper::json($response, $content);
    }

}
