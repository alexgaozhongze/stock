<?php

/**
 * 用户助手函数
 * @author liu,jian <coder.keda@gmail.com>
 */

/**
 * 获取全局配置对象
 * @return \Noodlehaus\Config
 */
function config()
{
    return $GLOBALS['config'];
}

function checkOpen()
{
    $connection = context()->get('dbPool')->getConnection();
    $sql = "SELECT `date` FROM `hsab` WHERE `date`=CURDATE() LIMIT 1";
    $date_exists = $connection->prepare($sql)->queryOne();

    return $date_exists ? true : false;
}

function dates($limit=10, $format='')
{
    $connection = context()->get('dbPool')->getConnection();
    $sql = "SELECT `date` FROM `hsab` WHERE `date`<>CURDATE() GROUP BY `date` ORDER BY `date` DESC LIMIT $limit";
    $date_list = $connection->prepare($sql)->queryAll();

    $list = array_column($date_list, 'date');
    sort($list);

    if ($format) {
        foreach ($list as $key => $value) {
            $list[$key] = date($format, strtotime($value));
        }
    }
    return $list;
}

function shellPrint($datas)
{
    if (!$datas) {
        echo 'false', PHP_EOL, PHP_EOL;
        return false;
    }

    $data = reset($datas);
    $keys = array_keys($data);
    foreach ($keys as $value) {
        printf("% -10s", $value);
    }
    echo PHP_EOL;

    foreach ($datas as $value) {
        foreach ($value as $vvalue) {
            printf("% -10s", $vvalue);
        }
        echo PHP_EOL;
    }

    $data = reset($datas);
    $keys = array_keys($data);
    foreach ($keys as $value) {
        printf("% -10s", $value);
    }

    echo PHP_EOL;
}
