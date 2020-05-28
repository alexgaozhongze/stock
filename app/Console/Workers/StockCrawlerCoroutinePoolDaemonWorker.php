<?php

namespace App\Console\Workers;

use Mix\Concurrent\CoroutinePool\AbstractWorker;
use Mix\Concurrent\CoroutinePool\WorkerInterface;
use App\Console\Models\HsabModel;
use App\Console\Models\FscjModel;
use App\Console\Models\MacdModel;

/**
 * Class StockCrawlerCoroutinePoolDaemonWorker
 * @package Daemon\Libraries
 * @author liu,jian <coder.keda@gmail.com>
 */
class StockCrawlerCoroutinePoolDaemonWorker extends AbstractWorker implements WorkerInterface
{

    /**
     * StockCrawlerCoroutinePoolDaemonWorker constructor.
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
    public function handle($action)
    {
        $start_time = date('Y-m-d H:i:s');

        switch ($action) {
            case 'hsab':
                $model = new HsabModel();
                break;
            case 'fscj':
                $model = new FscjModel();
                break;
            case 'macd':
                $model = new MacdModel();
                break;
        }

        $status = $model->sync();

        echo $action, ' ', $status, ' ', $start_time, ' ', date('Y-m-d H:i:s'), PHP_EOL;
        return $status;
    }

}
