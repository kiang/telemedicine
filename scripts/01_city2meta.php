<?php

/**
    [0] => ﻿醫事機構代碼
    [1] => 醫事機構名稱
    [2] => 業務組別
    [3] => 特約類別
    [4] => 看診年度
    [5] => 看診星期
    [6] => 看診備註
    [7] => 開業狀況
    [8] => 資料集更新時間
 */
$rawFile = dirname(dirname(__DIR__)) . '/data.nhi.gov.tw/raw/A21030000I-D21006-001.csv';
$fh = fopen($rawFile, 'r');
$header = fgetcsv($fh, 2048);
$pool = [];
while ($line = fgetcsv($fh, 2048)) {
    $pool[$line[1]] = $line;
}
$data = [];

/**
    [1] => 院所名稱
    [2] => 聯絡電話
    [3] => 視訊方式:LINEID
    [4] => 視訊方式:google meet連結
    [5] => 星期一
    [6] => 星期二
    [7] => 星期三
    [8] => 星期四
    [9] => 星期五
    [10] => 星期六
    [11] => 星期日
 */
$fh = fopen(dirname(__DIR__) . '/raw/city/tainan.csv', 'r');
while ($line = fgetcsv($fh, 2048)) {
    if (isset($pool[$line[1]])) {
        $data[$pool[$line[1]][0]] = [
            'line' => $line[3],
            'google' => $line[4],
        ];
    }
}

file_put_contents(dirname(__DIR__) . '/raw/meta.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
