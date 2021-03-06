<?php

namespace App\Api\Controllers;

use Mix\Http\Message\Response;
use Mix\Http\Message\ServerRequest;
use App\Common\Helpers\ResponseHelper;

/**
 * Class UserController
 * @package App\Api\Controllers
 * @author liu,jian <coder.keda@gmail.com>
 */
class StockController
{

    /**
     * FileController constructor.
     * @param ServerRequest $request
     * @param Response $response
     */
    public function __construct(ServerRequest $request, Response $response)
    {
    }

    /**
     * Create
     * @param ServerRequest $request
     * @param Response $response
     * @return Response
     */
    public function ztwatch(ServerRequest $request, Response $response)
    {
        $session  = context()->getBean('session', [
            'request'  => $request,
            'response' => $response,
        ]);
        // $session->createId();
        // $payload = [
        //     'uid'      => 1088,
        //     'openid'   => 'yZmFiZDc5MjIzZDMz',
        //     'username' => '小明',
        // ];
        // $session->set('payload', $payload);

        var_dump($session->get('payload'));

        // 响应
        $content = ['code' => 0, 'message' => 'OK'];
        return ResponseHelper::json($response, $content);
    }

}
