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

function dates($limit=18, $format='')
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
    $arrayKeys = [];
    $maxLens = [];
    $list = [];

    foreach ($datas as $key => $value) {
        if (!$arrayKeys) {
            $arrayKeys = array_keys($value);
            foreach ($arrayKeys as $aValue) {
                $list[$aValue] = [$aValue];
            }
        }

        foreach ($arrayKeys as $aValue) {
            $list[$aValue][] = $value[$aValue];
        }
    }

    foreach ($arrayKeys as $aValue) {
        $list[$aValue][] = $aValue;
    }

    foreach ($list as $key => $value) {
        $maxLens[$key] = max(array_map('strlen', $value));
    }

    $count = count($datas);
    for ($i=0; $i <= $count; $i++) { 
        foreach ($arrayKeys as $value) {
            $len = $maxLens[$value] + 5;
            printf("% -{$len}s", $list[$value][$i]);
        }
        echo PHP_EOL;
    }

    foreach ($arrayKeys as $value) {
        $len = $maxLens[$value] + 5;
        printf("% -{$len}s", $value);
    }
    echo PHP_EOL, PHP_EOL;
}