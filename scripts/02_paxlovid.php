<?php
$config = require __DIR__ . '/config.php';
$nhiFiles = [
    dirname(dirname(__DIR__)) . '/data.nhi.gov.tw/raw/A21030000I-D21001-003.csv',
    dirname(dirname(__DIR__)) . '/data.nhi.gov.tw/raw/A21030000I-D21002-005.csv',
    dirname(dirname(__DIR__)) . '/data.nhi.gov.tw/raw/A21030000I-D21003-003.csv',
    dirname(dirname(__DIR__)) . '/data.nhi.gov.tw/raw/A21030000I-D21005-001.csv',
];

$info = [];
foreach ($nhiFiles as $nhiFile) {
    $fh = fopen($nhiFile, 'r');
    while ($line = fgetcsv($fh, 2048)) {
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
    $key = $line[1];
    if (false !== strpos($line[1], '藥局')) {
        $city = mb_substr($info[$line[0]][4], 0, 3, 'utf-8');
        $key = $city . $line[1];
    }
    $pool[$key] = [
        'meta' => $line,
        'info' => isset($info[$line[0]]) ? $info[$line[0]] : [],
    ];
}
$rawPath = dirname(__DIR__) . '/raw';

$fc = [
    'type' => 'FeatureCollection',
    'features' => [],
];
$fh = fopen($rawPath . '/paxlovid_pharmacies.csv', 'r');
while ($line = fgetcsv($fh, 2048)) {
    $line[1] = str_replace('台', '臺', $line[1]);
    $key = $line[1] . $line[2];
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
                $fc['features'][] = [
                    'type' => 'Feature',
                    'properties' => [
                        'id' => $code,
                        'type' => '2',
                        'name' => $line[2],
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

$fh = fopen($rawPath . '/paxlovid.csv', 'r');
fgetcsv($fh, 2048);
while ($line = fgetcsv($fh, 2048)) {
    switch ($line[1]) {
        case '光田醫療社團法人光田綜合醫院沙鹿總院':
            $key = '光田醫療社團法人光田綜合醫院';
            $code = $pool[$key]['info'][0] . '-1';
            $address = '台中市沙鹿區沙田路117號';
            break;
        case '光田醫療社團法人光田綜合醫院大甲院區':
            $key = '光田醫療社團法人光田綜合醫院';
            $code = $pool[$key]['info'][0] . '-2';
            $address = '台中市大甲區經國路321號';
            break;
        case '臺北市立聯合醫院仁愛院區':
            $key = '臺北市立聯合醫院';
            $code = $pool[$key]['info'][0] . '-1';
            $address = '臺北市大安區仁愛路四段10號';
            break;
        case '臺北市立聯合醫院中興院區':
            $key = '臺北市立聯合醫院';
            $code = $pool[$key]['info'][0] . '-2';
            $address = '臺北市鄭州路145號';
            break;
        case '臺北市立聯合醫院忠孝院區':
            $key = '臺北市立聯合醫院';
            $code = $pool[$key]['info'][0] . '-3';
            $address = '臺北市南港區同德路87號';
            break;
        case '臺北市立聯合醫院陽明院區':
            $key = '臺北市立聯合醫院';
            $code = $pool[$key]['info'][0] . '-4';
            $address = '臺北市雨聲街105號';
            break;
        case '臺北市立聯合醫院和平婦幼院區':
            $key = '臺北市立聯合醫院';
            $code = $pool[$key]['info'][0] . '-5';
            $address = '臺北市福州街12號';
            break;
        case '國軍臺中總醫院附設民眾診療服務處':
            $key = '國軍臺中總醫院中清分院附設民眾診療服務處';
            $code = '49733093';
            $address = '臺中市太平區中山路二段348號';
            break;
        case '新北市立聯合醫院(三重)':
            $key = '新北市立聯合醫院';
            $code = $pool[$key]['info'][0];
            $address = '新北市三重區新北大道一段3號';
            break;
        case '新北市立土城醫院(委託長庚醫療財團法人興建經營)':
            $key = '新北市立土城醫院（委託長庚醫療財團法人興建經營）';
            $code = $pool[$key]['info'][0];
            $address = $pool[$key]['info'][4];
            break;
        case '衛生福利部雙和醫院(委託臺北醫學大學興建經營)':
            $key = '衛生福利部雙和醫院〈委託臺北醫學大學興建經營〉';
            $code = $pool[$key]['info'][0];
            $address = $pool[$key]['info'][4];
            break;
        case '國立台灣大學醫學院附設醫院':
            $key = '國立臺灣大學醫學院附設醫院';
            $code = $pool[$key]['info'][0];
            $address = $pool[$key]['info'][4];
            break;
        case '胸腔病院':
            $key = '衛生福利部胸腔病院';
            $code = $pool[$key]['info'][0];
            $address = $pool[$key]['info'][4];
            break;
        case '高雄市立大同醫院(委託財團法人私立高雄醫學大學附設中和紀念醫院經營)':
            $key = '高雄市立大同醫院（委託財團法人私立高雄醫學大學附設中和紀念';
            $code = $pool[$key]['info'][0];
            $address = $pool[$key]['info'][4];
            break;
        case '高雄市立小港醫院(委託財團法人私立高雄醫學大學經營)':
            $key = '高雄市立小港醫院（委託財團法人私立高雄醫學大學經營）';
            $code = $pool[$key]['info'][0];
            $address = $pool[$key]['info'][4];
            break;
        default:
            $key = $line[1];
            $code = $pool[$key]['info'][0];
            $address = $pool[$key]['info'][4];
    }
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
            $fc['features'][] = [
                'type' => 'Feature',
                'properties' => [
                    'id' => $code,
                    'type' => '1',
                    'name' => $line[1],
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
$jsonPath = dirname(__DIR__) . '/docs/json';
file_put_contents($jsonPath . '/paxlovid.json', json_encode($fc, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
