<?php

namespace App\Api\Controllers;

use App\Common\Helpers\ResponseHelper;
use Mix\Http\Message\Response;
use Mix\Http\Message\ServerRequest;

/**
 * Class GoBeyondController
 * @package App\Api\Controllers
 * @author alex <alexgaozhongze@gmail.com>
 */
class GoBeyondController
{

    public function index(ServerRequest $request, Response $response)
    {
        $dbPool = context()->get('dbPool');
        $db     = $dbPool->getConnection();
        $sql    = "SELECT b.hsl/a.hsl hslpre,b.code, b.price, b.up, b.name, b.type
        FROM hsab a left join hsab b on a.code=b.code and a.type=b.type and b.date=CURDATE()
        WHERE left(a.code, 3) not in (300) AND left(a.name, 1) NOT IN ('*','S') AND a.date=(select max(date) from hsab where date<>CURDATE()) AND a.price =a.zt and a.jk=a.zd and a.zf>=6 and b.ema5>=b.ema10 and b.ema10>=b.ema20 and b.ema20>=b.ema60 
        ORDER BY b.ema12/b.ema26 desc";
        $result = $db->prepare($sql)->queryAll();
        $db->release();

        $content = ['code' => 0, 'message' => 'OK', 'list' => $result];
        return ResponseHelper::json($response, $content);
    }

}
