<?php
$config = require __DIR__ . '/config.php';

$rawPath = dirname(__DIR__) . '/raw';

$nhiFiles = [
    dirname(dirname(__DIR__)) . '/data.nhi.gov.tw/raw/A21030000I-D21001-003.csv', //醫學中心
    dirname(dirname(__DIR__)) . '/data.nhi.gov.tw/raw/A21030000I-D21002-005.csv', //區域醫院
    dirname(dirname(__DIR__)) . '/data.nhi.gov.tw/raw/A21030000I-D21003-003.csv', //地區醫院
    dirname(dirname(__DIR__)) . '/data.nhi.gov.tw/raw/A21030000I-D21004-009.csv', //診所
];

$info = [];
foreach ($nhiFiles as $nhiFile) {
    $fh = fopen($nhiFile, 'r');
    while ($line = fgetcsv($fh, 2048)) {
        if (false === strpos($line[4], '臺南市')) {
            continue;
        }
        $info[$line[0]] = $line;
    }
}


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
    if (!isset($info[$line[0]])) {
        continue;
    }
    $key = $line[1];
    $pool[$key] = [
        'meta' => $line,
        'info' => $info[$line[0]],
    ];
}


$fc = [
    'type' => 'FeatureCollection',
    'features' => [],
];

$csvFile = $rawPath . '/city/tainan.csv';
// format error = https://docs.google.com/spreadsheets/d/1rVrP0RKSe2_NNJyhHQKkX0POyl_7Hmxb/gviz/tq?tqx=out:csv&sheet=%E5%BD%99%E6%95%B4%E8%A1%A8

file_put_contents($csvFile, file_get_contents('https://docs.google.com/spreadsheets/d/1rVrP0RKSe2_NNJyhHQKkX0POyl_7Hmxb/export?format=csv'));
/**
    [0] => 院所名稱
    [1] => 聯絡電話
    [2] => 視訊方式：LINE ID
    [3] => 視訊方式：google meet 連結
    [4] => 星期一
    [5] => 星期二
    [6] => 星期三
    [7] => 星期四
    [8] => 星期五
    [9] => 星期六
    [10] => 星期日
 */
$fh = fopen($csvFile, 'r');
fgetcsv($fh, 2048);
fgetcsv($fh, 2048);
$duplicatedCheck = [];
$codePool = [];
$fcKey = 0;
while ($line = fgetcsv($fh, 2048)) {
    foreach ($line as $k => $v) {
        $line[$k] = trim($v);
    }
    switch ($line[1]) {
        case '陳信宏小兒科':
            $line[1] = '陳信宏小兒科診所';
            break;
        case '路加小兒科':
            $line[1] = '路加小兒科診所';
            break;
        case '悅恩小兒科（樂恩聯合診所）':
            $line[1] = '悅恩小兒科診所（樂恩聯合診所）';
            break;
        case '康庭小兒科（樂恩聯合診所）':
            $line[1] = '康庭小兒科診所（樂恩聯合診所）';
            break;
        case '蔡廸光小兒科診所':
            $line[1] = '蔡迪光小兒科診所';
            break;
        case '富儿康診所':
            $line[1] = '富ㄦ康診所';
            break;
        case '賴俊良診所':
            $line[1] = '賴俊良骨外科診所';
            break;
        case '呂怡璋小兒科':
            $line[1] = '呂怡璋小兒科診所';
            break;
        case '蔡尚均小兒科￼':
            $line[1] = '蔡尚均小兒科診所';
            break;
        case '劉伊薰小兒科':
            $line[1] = '劉伊薰小兒科診所';
            break;
        case '平安聯合診所（白袍旅人兒科）':
            $line[1] = '白袍旅人兒科診所';
            break;
        case '吳美華小兒科':
            $line[1] = '吳美華小兒科診所';
            break;
    }
    $key = $line[1];
    if (isset($pool[$key]) && !isset($duplicatedCheck[$key])) {
        $duplicatedCheck[$key] = true;
        $code = $pool[$key]['info'][0];
        $address = $pool[$key]['info'][4];

        $cityPath = $rawPath . '/geocoding/' . mb_substr($pool[$key]['info'][4], 0, 3, 'utf-8');
        if (!file_exists($cityPath)) {
            mkdir($cityPath, 0777, true);
        }
        $pos = strpos($address, '號');
        if (false !== $pos) {
            $address = substr($address, 0, $pos) . '號';
        }
        $rawFile = $cityPath . '/' . $address . '.json';
        if (!file_exists($rawFile)) {
            $apiUrl = $config['tgos']['url'] . '?' . http_build_query([
                'oAPPId' => $config['tgos']['APPID'], //應用程式識別碼(APPId)
                'oAPIKey' => $config['tgos']['APIKey'], // 應用程式介接驗證碼(APIKey)
                'oAddress' => $address, //所要查詢的門牌位置
                'oSRS' => 'EPSG:4326', //回傳的坐標系統
                'oFuzzyType' => '2', //模糊比對的代碼
                'oResultDataType' => 'JSON', //回傳的資料格式
                'oFuzzyBuffer' => '0', //模糊比對回傳門牌號的許可誤差範圍
                'oIsOnlyFullMatch' => 'false', //是否只進行完全比對
                'oIsLockCounty' => 'true', //是否鎖定縣市
                'oIsLockTown' => 'false', //是否鎖定鄉鎮市區
                'oIsLockVillage' => 'false', //是否鎖定村里
                'oIsLockRoadSection' => 'false', //是否鎖定路段
                'oIsLockLane' => 'false', //是否鎖定巷
                'oIsLockAlley' => 'false', //是否鎖定弄
                'oIsLockArea' => 'false', //是否鎖定地區
                'oIsSameNumber_SubNumber' => 'true', //號之、之號是否視為相同
                'oCanIgnoreVillage' => 'true', //找不時是否可忽略村里
                'oCanIgnoreNeighborhood' => 'true', //找不時是否可忽略鄰
                'oReturnMaxCount' => '0', //如為多筆時，限制回傳最大筆數
                'oIsSupportPast' => 'true',
                'oIsShowCodeBase' => 'true',
            ]);
            $content = file_get_contents($apiUrl);
            $pos = strpos($content, '{');
            $posEnd = strrpos($content, '}') + 1;
            $resultline = substr($content, $pos, $posEnd - $pos);
            if (strlen($resultline) > 10) {
                echo "{$address}\n";
                file_put_contents($rawFile, substr($content, $pos, $posEnd - $pos));
            }
        }
        if (file_exists($rawFile)) {
            $json = json_decode(file_get_contents($rawFile), true);
            if (!empty($json['AddressList'][0]['X'])) {
                $codePool[$code] = $fcKey;
                $fc['features'][$fcKey] = [
                    'type' => 'Feature',
                    'properties' => [
                        'id' => $code,
                        'type' => 1,
                        'name' => $key,
                        'line' => $line[2],
                        'google' => $line[3],
                        'phone' => $pool[$key]['info'][3],
                        'address' => $address,
                        'service_periods' => $pool[$key]['meta'][5],
                    ],
                    'geometry' => [
                        'type' => 'Point',
                        'coordinates' => [
                            $json['AddressList'][0]['X'],
                            $json['AddressList'][0]['Y']
                        ],
                    ],
                ];
                $fcKey++;
            }
        }
    }
}

$kmlFile = dirname(__DIR__) . '/raw/city/tainan_pcr.xml';
file_put_contents($kmlFile, file_get_contents('https://mapsengine.google.com/map/u/0/kml?forcekml=1&mid=1xLiYr0aHrJ6CUpzZ1fvwzojFp9YbXihY'));
$xml = simplexml_load_file($kmlFile, null, LIBXML_NOCDATA);
foreach ($xml->Document->Folder as $folder) {
    foreach ($folder->Placemark as $placemark) {
        $key = trim($placemark->name);
        if (isset($pool[$key])) {
            $code = $pool[$key]['info'][0];
            $address = $pool[$key]['info'][4];

            $cityPath = $rawPath . '/geocoding/' . mb_substr($pool[$key]['info'][4], 0, 3, 'utf-8');
            if (!file_exists($cityPath)) {
                mkdir($cityPath, 0777, true);
            }
            $pos = strpos($address, '號');
            if (false !== $pos) {
                $address = substr($address, 0, $pos) . '號';
            }
            $rawFile = $cityPath . '/' . $address . '.json';
            if (!file_exists($rawFile)) {
                $apiUrl = $config['tgos']['url'] . '?' . http_build_query([
                    'oAPPId' => $config['tgos']['APPID'], //應用程式識別碼(APPId)
                    'oAPIKey' => $config['tgos']['APIKey'], // 應用程式介接驗證碼(APIKey)
                    'oAddress' => $address, //所要查詢的門牌位置
                    'oSRS' => 'EPSG:4326', //回傳的坐標系統
                    'oFuzzyType' => '2', //模糊比對的代碼
                    'oResultDataType' => 'JSON', //回傳的資料格式
                    'oFuzzyBuffer' => '0', //模糊比對回傳門牌號的許可誤差範圍
                    'oIsOnlyFullMatch' => 'false', //是否只進行完全比對
                    'oIsLockCounty' => 'true', //是否鎖定縣市
                    'oIsLockTown' => 'false', //是否鎖定鄉鎮市區
                    'oIsLockVillage' => 'false', //是否鎖定村里
                    'oIsLockRoadSection' => 'false', //是否鎖定路段
                    'oIsLockLane' => 'false', //是否鎖定巷
                    'oIsLockAlley' => 'false', //是否鎖定弄
                    'oIsLockArea' => 'false', //是否鎖定地區
                    'oIsSameNumber_SubNumber' => 'true', //號之、之號是否視為相同
                    'oCanIgnoreVillage' => 'true', //找不時是否可忽略村里
                    'oCanIgnoreNeighborhood' => 'true', //找不時是否可忽略鄰
                    'oReturnMaxCount' => '0', //如為多筆時，限制回傳最大筆數
                    'oIsSupportPast' => 'true',
                    'oIsShowCodeBase' => 'true',
                ]);
                $content = file_get_contents($apiUrl);
                $pos = strpos($content, '{');
                $posEnd = strrpos($content, '}') + 1;
                $resultline = substr($content, $pos, $posEnd - $pos);
                if (strlen($resultline) > 10) {
                    echo "{$address}\n";
                    file_put_contents($rawFile, substr($content, $pos, $posEnd - $pos));
                }
            }
            if (file_exists($rawFile)) {
                $json = json_decode(file_get_contents($rawFile), true);
                if (!empty($json['AddressList'][0]['X'])) {
                    if (isset($codePool[$code])) {
                        unset($fc['features'][$codePool[$code]]);
                        $pointType = 3;
                    } else {
                        $pointType = 2;
                    }
                    $fc['features'][] = [
                        'type' => 'Feature',
                        'properties' => [
                            'id' => $code,
                            'type' => $pointType,
                            'name' => $key,
                            'phone' => $pool[$key]['info'][3],
                            'address' => $address,
                            'service_periods' => $pool[$key]['meta'][5],
                        ],
                        'geometry' => [
                            'type' => 'Point',
                            'coordinates' => [
                                $json['AddressList'][0]['X'],
                                $json['AddressList'][0]['Y']
                            ],
                        ],
                    ];
                }
            }
        }
    }
}
$fc['features'] = array_values($fc['features']);

$jsonPath = dirname(__DIR__) . '/docs/json';
file_put_contents($jsonPath . '/tainan.json', json_encode($fc, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
