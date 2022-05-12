<?php
$page = file_get_contents('https://www.nhi.gov.tw/Content_List.aspx?n=EC68146E978EC380');
$rawPath = dirname(__DIR__) . '/raw';
$pos = strpos($page, 'class="ods"');
while (false !== $pos) {
    $pos = strpos($page, 'href="', $pos);
    $posEnd = strpos($page, 'target=', $pos);
    $parts = explode('"', substr($page, $pos, $posEnd - $pos));
    $fileNames = preg_split('/[_\\-\\(]/', $parts[3]);

    file_put_contents($rawPath . '/' . $fileNames[2] . '.ods', file_get_contents($parts[1]));
    $pos = strpos($page, 'class="ods"', $posEnd);
}
