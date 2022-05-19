<?php
require 'vendor/autoload.php';
$config = require __DIR__ . '/config.php';
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
    $pool[$line[0]] = $line;
}
$meta = json_decode(file_get_contents(dirname(__DIR__) . '/raw/meta.json'), true);
$fc = [
    'type' => 'FeatureCollection',
    'features' => [],
];

$rawPath = dirname(__DIR__) . '/raw';
$jsonPath = dirname(__DIR__) . '/docs/json';
if (!file_exists($jsonPath)) {
    mkdir($jsonPath, 0777, true);
}

$formFile = $rawPath . '/google_form.csv';
$fh = fopen($formFile, 'r');
fgetcsv($fh, 2048);
$userInput = [];
while ($line = fgetcsv($fh, 2048)) {
    if (!empty($line[1])) {
        $userInput[$line[1]] = $line;
    }
}

$errorCount = 0;
$lineCount = 0;
$filesPool = ['醫院.ods', '診所.ods'];
foreach ($filesPool as $odsFile) {
    $odsFile = $rawPath . '/' . $odsFile;
    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($odsFile);
    $sheetData   = $spreadsheet->getActiveSheet()->toArray();
    array_shift($sheetData);
    array_shift($sheetData);
    array_shift($sheetData);
    array_shift($sheetData);
    foreach ($sheetData as $line) {
        if (empty($line[6])) {
            continue;
        }
        $pos = strpos($line[6], '號');
        if (false !== $pos) {
            $fullAddress = substr($line[6], 0, $pos) . '號';
        } else {
            $fullAddress = $line[6];
        }

        $cityPath = $rawPath . '/geocoding/' . $line[1];
        if (!file_exists($cityPath)) {
            mkdir($cityPath, 0777, true);
        }
        $rawFile = $cityPath . '/' . $fullAddress . '.json';
        if ($errorCount < 5 && !file_exists($rawFile)) {
            $apiUrl = $config['tgos']['url'] . '?' . http_build_query([
                'oAPPId' => $config['tgos']['APPID'], //應用程式識別碼(APPId)
                'oAPIKey' => $config['tgos']['APIKey'], // 應用程式介接驗證碼(APIKey)
                'oAddress' => $fullAddress, //所要查詢的門牌位置
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
                echo "[{$lineCount}]{$fullAddress}\n";
                file_put_contents($rawFile, substr($content, $pos, $posEnd - $pos));
            }
        }
        if (file_exists($rawFile)) {
            ++$lineCount;
            $json = json_decode(file_get_contents($rawFile), true);
            if (!empty($json['AddressList'][0]['X'])) {
                $pointFound = true;
                $key = $code = $line[3];
                $fType = 1; // default
                $imLine = $imGoogle = $url = $note = '';
                if (isset($userInput[$code])) {
                    if ($userInput[$code][2] === '是') {
                        $fType = 2; // confirmed it has the service
                        $url = $userInput[$code][3];
                        $imLine = $userInput[$code][4];
                        $imGoogle = $userInput[$code][5];
                        $note = $userInput[$code][6];
                    }
                }
                if (isset($meta[$code])) {
                    $fType = 2; // confirmed it has the service
                    $imLine = $meta[$code]['line'];
                    $imGoogle = $meta[$code]['google'];
                }
                if (empty($note)) {
                    $note = $line[7];
                }
                $fc['features'][] = [
                    'type' => 'Feature',
                    'properties' => [
                        'id' => $line[3],
                        'type' => $fType,
                        'name' => $line[4],
                        'phone' => $line[5],
                        'address' => $line[6],
                        'note' => $note,
                        'service_periods' => isset($pool[$key]) ? $pool[$key][5] : '',
                        'url' => $url,
                        'line' => $imLine,
                        'google' => $imGoogle,
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
            if ($lineCount % 3000 === 0) {
                file_put_contents($jsonPath . '/points.json', json_encode($fc, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }
        } else {
            ++$errorCount;
        }
    }
}

file_put_contents($jsonPath . '/points.json', json_encode($fc, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
