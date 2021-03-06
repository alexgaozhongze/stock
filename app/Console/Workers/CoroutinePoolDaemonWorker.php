<?php

namespace App\Console\Workers;

use Mix\Concurrent\CoroutinePool\AbstractWorker;
use Mix\Concurrent\CoroutinePool\WorkerInterface;

/**
 * Class CoroutinePoolDaemonWorker
 * @package Daemon\Libraries
 * @author liu,jian <coder.keda@gmail.com>
 */
class CoroutinePoolDaemonWorker extends AbstractWorker implements WorkerInterface
{

    /**
     * CoroutinePoolDaemonWorker constructor.
     * @param array $config
     */
    public function __construct(array $config)
    {
        parent::__construct($config);
        // 实例化一些需重用的对象
        // ...
    }

    /**
     * 处理
     * @param $data
     */
    public function handle($data)
    {
        // TODO: Implement handle() method.
        var_dump($data);
    }

}
