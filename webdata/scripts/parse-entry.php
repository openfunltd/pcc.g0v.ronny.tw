<?php

include(__DIR__ . '/../init.inc.php');
date_default_timezone_set('Asia/Taipei');
ini_set('memory_limit', '2G');

$now = $_SERVER['argv'][1] ? strtotime($_SERVER['argv'][1]) : time();

while (true) {
    $ymd = date('Ymd', $now);
    $year = date('Y', $now);
    $list_source = "list/{$ymd}.html.gz";
    $list_source_np = "list/np-{$ymd}.html.gz";

    if ($ymd < 19990101) {
        break;
    }
    $now -= 86400;
    $records = array();

    if (file_Exists($list_source)) {
        $list_content = gzdecode(file_get_contents($list_source));
        $records = array_merge($records, Parser::parse_list($list_content));
    }
    if (file_Exists($list_source_np)) {
        $list_content = gzdecode(file_get_contents($list_source_np));
        $records = array_merge($records, Parser::parse_list_np($list_content));
    }

    if (!$records) {
        continue;
    }

    $total = count($records);
    error_log("parsing {$ymd} " . $total);
    foreach ($records as $seq => $record) {
        $filename = Entity::getFilename($record);
        $url = Entity::updateUrl($record, $ymd, $filename);
        $entry_source = "entry/{$year}/{$ymd}-{$filename}.gz";

        if (!file_exists($entry_source)) {
            continue;
        }
        $entry_target = "entry-json/{$year}/{$ymd}-{$filename}.json.gz";
        if (!file_exists("entry-json/{$year}")) {
            mkdir("entry-json/{$year}");
        }
        if (file_exists($entry_target) and filesize($entry_target) > 300) {
            $obj = json_decode(gzdecode(file_get_contents($entry_target)));
            if ($obj->type == '政府電子採購網' or $obj->type == '◎' or is_numeric($obj->type)) {
                // no continue
            } else if (property_exists($obj, 'fetched_at') and $obj->fetched_at != '1970-01-01T08:00:00+08:00') {
                continue;
            }
        }
        if (in_array($url, array(
        ))) {
            // 內容有問題無法解析
            continue;
        }
        error_log("$url $entry_source");
        $content = gzdecode(file_get_contents($entry_source));
        if ($url == 'http://web.pcc.gov.tw/prkms/prms-viewTenderDetailClient.do?ds=20100108&fn=TIQ-1-50003926.xml') {
            $content = str_replace('<由票據交換所或受理查詢金融機構出具之票據信用查覆單，應加蓋查覆單位及該單位有權人員、經辦員共3個圖章始為有效>', '&lt;由票據交換所或受理查詢金融機構出具之票據信用查覆單，應加蓋查    覆單位及該單位有權人員、經辦員共3個圖章始為有效&gt;', $content);
            $content = str_replace('<br>', "\n", $content);
            $content = str_replace('<b>', '', $content);
            $content = str_replace('</b>', '', $content);
        }
        try {
            if (strpos($content, '尚無資料')) {
                //continue;
            }
            $values = Parser::parseHTML($content, $url);
            $values->fetched_at = date('c', filemtime($entry_source));
            if (in_array($values->url, array(
                'http://web.pcc.gov.tw/prkms/prms-viewTenderDetailClient.do?ds=20121227&fn=BDM-1-50864742.xml',
            )) and $values->{'已公告資料:標案名稱'} == '') {
                $values->{'已公告資料:標案名稱'} = '< 新修彰化縣志・政事志〉纂修案';
            } elseif (in_array($values->url, array(
                'http://web.pcc.gov.tw/prkms/prms-viewTenderDetailClient.do?ds=20121204&fn=TIQ-3-50768140.xml',
                'http://web.pcc.gov.tw/prkms/prms-viewTenderDetailClient.do?ds=20121114&fn=TIQ-3-50750126.xml',
            )) and $values->{'採購資料:標案名稱'} == '') {
                $values->{'採購資料:標案名稱'} = '< 新修彰化縣志・政事志〉纂修案';
            } elseif (in_array($values->url, array(
                'http://web.pcc.gov.tw/prkms/prms-viewTenderDetailClient.do?ds=20121130&fn=NAI-1-50835192.xml',
            )) and $values->{'無法決標公告:標案名稱'} == '') {
                $values->{'無法決標公告:標案名稱'} = '< 新修彰化縣志・政事志〉纂修案';
            }
        } catch (Exception $e) {
            error_log("{$url} {$entry_source} " . $e->getMessage());
            if ($e->GetCode() === 999) {
                throw $e;
            }
            // TODO: too big file_put_contents('big-file', $entry_source . "\n", FILE_APPEND);
            continue;
        }

        file_put_contents($entry_target, gzencode(json_encode($values, JSON_UNESCAPED_UNICODE)));
        //readline('continue');
    }
}
