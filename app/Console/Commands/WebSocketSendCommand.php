<?php

namespace App\Console\Commands;

/**
 * Class WebSocketSendCommand
 * @package App\Console\Commands
 * @author alex <alexgaozhongze@gmail.com>
 */
class WebSocketSendCommand
{

    /**
     * 主函数
     */
    public function main()
    {
        $cli = new \Swoole\Coroutine\http\Client('127.0.0.1', 9502);
        $ret = $cli->upgrade("/websocket");
        if ($ret) {
            $frame = new \Swoole\WebSocket\Frame();
            $frame->opcode = SWOOLE_WEBSOCKET_OPCODE_TEXT;
            $frame->data = '{"method":"join.room","params":[1012,"小明"],"id":1}';
            $cli->push($frame);
            $data = $cli->recv();
            var_export($data);
            $frame = new \Swoole\WebSocket\Frame();
            $frame->opcode = SWOOLE_WEBSOCKET_OPCODE_TEXT;
            $frame->data = '{"method":"message.emit","params":["大家好"],"id":2}';
            $cli->push($frame);
            $data = $cli->recv();
            var_export($data);
        }
    }

}
