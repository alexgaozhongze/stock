<?php

return [

    'udp:start' => [
        \App\Udp\Commands\StartCommand::class,
        'description' => "Start service",
        'options'     => [
            [['d', 'daemon'], 'description' => "\tRun in the background"],
            [['a', 'addr'], 'description' => "\tListen to the specified address"],
            [['p', 'port'], 'description' => "\tListen to the specified port"],
            [['r', 'reuse-port'], 'description' => "Reuse port in multiple processes"],
        ],
    ],

];
