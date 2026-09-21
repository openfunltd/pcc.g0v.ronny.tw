<?php

include(__DIR__ . '/../config.php');

date_default_timezone_set('Asia/Taipei');

$fetch_user_agent = getenv('FETCH_USER_AGENT') ?: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:132.0) Gecko/20100101 Firefox/132.0';

$now = time();// - 86400;
// 刊登公報
$empty_retry_count = 0;
while (true) {
    $ymd = date('Ymd', $now);
    if ($ymd < 20100102) {
        break;
    }
    $date = sprintf("%03d年%02d月%02d日", date('Y', $now) - 1911, date('m', $now), date('d', $now));
    $target = "list/{$ymd}.html.gz";
    $now -= 86400;
    if (file_exists($target)) {
        $old_content = gzdecode(file_get_contents($target));
    }

    if (!file_Exists($target)) {
    } elseif (strpos($old_content, 'Web Page Blocked') !== FALSE) { // captcha
    } elseif (filesize($target) == 20) {
    } elseif (strpos($old_content, '</html>') === FALSE) { // no end
    } else {
        continue;
    }

    $curl = curl_init();
    // curl -interface eth1 'http://web.pcc.gov.tw/prkms/prms-viewTenderStatClient.do?ds=20170628&root=tps' 
    // -H 'User-Agent: '$FETCH_USER_AGENT' (見 config.php)
    // -H 'Referer: http://web.pcc.gov.tw/prkms/prms-viewDailyTenderListClient.do?root=tps'
    $url = 'https://web.pcc.gov.tw/prkms/tender/common/noticeDate/readPublish?dateStr=' . urlencode($date);
    curl_setopt($curl, CURLOPT_URL, $url);
    $proxy_port = 20001 + rand(0, 4);
    curl_setopt($curl, CURLOPT_PROXY, getenv('PROXY_HOST') . ':' . $proxy_port);
    curl_setopt($curl, CURLOPT_PROXYUSERPWD, getenv('PROXY_USER') . ':' . getenv('PROXY_PASS'));
    curl_setopt($curl, CURLOPT_TIMEOUT, 60);
    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
        'User-Agent: ' . $fetch_user_agent,
    ));
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    error_log("crawling {$ymd} : " . $url . " via proxy port {$proxy_port}");
    $output = curl_exec($curl);
    curl_close($curl);
    if (strlen($output) == 0) {
        $empty_retry_count++;
        if ($empty_retry_count > 5) {
            error_log("crawling {$ymd} 空結果，已重試 {$empty_retry_count} 次，先跳過這天，之後重跑時靠快取檢查機制再抓一次");
            $empty_retry_count = 0;
            continue;
        }
        error_log("crawling {$ymd} 空結果，第 {$empty_retry_count} 次重試，sleep 30 秒");
        sleep(30);
        $now += 86400;
        continue;
    }
    $empty_retry_count = 0;
    file_put_contents($target, gzencode($output));
    touch('heartbeat');
    error_log("crawled {$ymd} : " . strlen($output));
    sleep(1);
}

$now = time(); // - 86400;
// 不刊登公報
$empty_retry_count = 0;
while (true) {
    $ymd = date('Ymd', $now);
    if ($ymd < 20120619) {
        break;
    }
    $date = sprintf("%03d年%02d月%02d日", date('Y', $now) - 1911, date('m', $now), date('d', $now));
    $target = "list/np-{$ymd}.html.gz";
    $now -= 86400;
    if (file_exists($target)) {
        $old_content = gzdecode(file_get_contents($target));
    }

    if (!file_Exists($target)) {
    } elseif (strpos($old_content, 'Web Page Blocked') !== FALSE) { // captcha
    } elseif (filesize($target) == 20) {
    } else {
        continue;
    }

    $curl = curl_init();
    // curl -interface eth1 'http://web.pcc.gov.tw/prkms/prms-viewTenderStatClient.do?ds=20170628&root=tps' 
    // -H 'User-Agent: '$FETCH_USER_AGENT' (見 config.php)
    // -H 'Referer: http://web.pcc.gov.tw/prkms/prms-viewDailyTenderListClient.do?root=tps'
    $url = 'https://web.pcc.gov.tw/prkms/tender/common/noticeDate/readUnPublish?dateStr=' . urlencode($date);
    curl_setopt($curl, CURLOPT_URL, $url);
    $proxy_port = 20001 + rand(0, 4);
    curl_setopt($curl, CURLOPT_PROXY, getenv('PROXY_HOST') . ':' . $proxy_port);
    curl_setopt($curl, CURLOPT_PROXYUSERPWD, getenv('PROXY_USER') . ':' . getenv('PROXY_PASS'));
    curl_setopt($curl, CURLOPT_TIMEOUT, 60);
    //curl_setopt($curl, CURLOPT_INTERFACE, '10.0.200.18');
    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
        'User-Agent: ' . $fetch_user_agent,
    ));
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    $output = curl_exec($curl);
    curl_close($curl);
    if (strlen($output) == 0) {
        $empty_retry_count++;
        if ($empty_retry_count > 5) {
            error_log("{$ymd} 空結果，已重試 {$empty_retry_count} 次，先跳過這天，之後重跑時靠快取檢查機制再抓一次");
            $empty_retry_count = 0;
            continue;
        }
        error_log("fail {$ymd}, 第 {$empty_retry_count} 次重試, sleep 30 seconds");
        sleep(30);
        $now += 86400;
        continue;
    }
    $empty_retry_count = 0;
    file_put_contents($target, gzencode($output));
    touch('heartbeat');
    error_log("crawled {$ymd} : " . strlen($output));
    sleep(1);
}
