<?php

namespace App\Web\Controllers;

use App\Common\Helpers\ResponseHelper;
use Mix\Http\Message\ServerRequest;
use Mix\Http\Message\Response;

/**
 * Class WebSocketController
 * @package App\Web\Controllers
 * @author alex <alexgaozhongze@gmail.com>
 */
class WebSocketController
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
     * Index
     * @param ServerRequest $request
     * @param Response $response
     * @return Response
     */
    public function index(ServerRequest $request, Response $response)
    {
        $session  = context()->getBean('session', [
            'request'  => $request,
            'response' => $response,
        ]);
        $session->createId();
        $payload = [
            'uid'      => 1088,
            'openid'   => 'yZmFiZDc5MjIzZDMz',
            'username' => '小明',
        ];
        $session->set('payload', $payload);

        var_dump($session->get('payload'));

        $data = [
            'id'      => 1,
            'name'    => '小明',
            'age'     => 18,
            'friends' => ['小红', '小花', '小飞'],
        ];
        return ResponseHelper::view($response, 'webSocket.index', $data);
    }

}
