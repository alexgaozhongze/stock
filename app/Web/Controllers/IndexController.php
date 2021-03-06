<?php

namespace App\Web\Controllers;

use App\Common\Helpers\ResponseHelper;
use Mix\Http\Message\ServerRequest;
use Mix\Http\Message\Response;

/**
 * Class IndexController
 * @package App\Web\Controllers
 * @author liu,jian <coder.keda@gmail.com>
 */
class IndexController
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
        $content = 'Hello, World!';
        return ResponseHelper::html($response, $content);
    }

    public function login(ServerRequest $request, Response $response)
    {
        return ResponseHelper::view($response, 'login.index');
    }

    public function upranking(ServerRequest $request, Response $response)
    {
        return ResponseHelper::view($response, 'upranking.index');
    }

    public function uptop(ServerRequest $request, Response $response)
    {
        return ResponseHelper::view($response, 'uptop.index');
    }
}
