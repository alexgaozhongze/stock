<?php

return [

    'hsab' => [
        \App\Console\Commands\HsabCommand::class,
        'description' => "\t沪深A板",
        'options'     => [
            [['d', 'daemon'], 'description' => 'Run in the background'],
        ],
    ],

    'fscj' => [
        \App\Console\Commands\FscjCommand::class,
        'description' => "\t分时成交",
        'options'     => [
            [['d', 'daemon'], 'description' => 'Run in the background'],
        ],
    ],

    'macd' => [
        \App\Console\Commands\MacdCommand::class,
        'description' => "\tmacd",
        'options'     => [
            [['d', 'daemon'], 'description' => 'Run in the background'],
        ],
    ],

    'wssc' => [
        \App\Console\Commands\WebSocketSendCommand::class,
        'description' => "\tmacd",
        'options'     => [
            [['d', 'daemon'], 'description' => 'Run in the background'],
        ],
    ],

    'cm' => [
        \App\Console\Commands\CjhMonitorCommand::class,
        'description' => "\tcjh monitor",
        'options'     => [
            [['d', 'daemon'], 'description' => 'Run in the background'],
        ],
    ],

    'he' => [
        \App\Console\Commands\HelloCommand::class,
        'description' => "\tEcho demo",
        'options'     => [
            [['n', 'name'], 'description' => 'Your name'],
            ['say', 'description' => "\tSay ..."],
        ],
    ],

    'co' => [
        \App\Console\Commands\CoroutineCommand::class,
        'description' => "\tCoroutine demo",
    ],

    'wg' => [
        \App\Console\Commands\WaitGroupCommand::class,
        'description' => "\tWaitGroup demo",
    ],

    'cp' => [
        \App\Console\Commands\CoroutinePoolCommand::class,
        'description' => "\tCoroutine pool demo",
    ],

    'cpd' => [
        \App\Console\Commands\CoroutinePoolDaemonCommand::class,
        'description' => "\tCoroutine pool daemon demo",
        'options'     => [
            [['d', 'daemon'], 'description' => 'Run in the background'],
        ],
    ],

    'ti' => [
        \App\Console\Commands\TimerCommand::class,
        'description' => "\tTimer demo",
    ],

];
