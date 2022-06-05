<?php

include(__DIR__ . '/../config.php');

$get_proxy_url = function($url){
    $secret = getenv('PROXY_SECRET');
    $url = getenv('PROXY_URL') . '?url=' . urlencode($url) . '&sig=' . md5($secret . $url);
    return $url;
};
date_default_timezone_set('Asia/Taipei');

$now = time();// - 86400;
// 刊登公報
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
    // -H 'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/59.0.3071.86 Safari/537.36' 
    // -H 'Referer: http://web.pcc.gov.tw/prkms/prms-viewDailyTenderListClient.do?root=tps'
    $url = 'https://web.pcc.gov.tw/prkms/tender/common/noticeDate/readPublish?dateStr=' . urlencode($date);
    curl_setopt($curl, CURLOPT_URL, $get_proxy_url($url));
    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
        'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/59.0.3071.86 Safari/537.36',
    ));
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    $output = curl_exec($curl);
    curl_close($curl);
    file_put_contents($target, gzencode($output));
    error_log("crawled {$ymd} : " . strlen($output));
    sleep(1);
}

$now = time(); // - 86400;
// 不刊登公報
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
    // -H 'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/59.0.3071.86 Safari/537.36' 
    // -H 'Referer: http://web.pcc.gov.tw/prkms/prms-viewDailyTenderListClient.do?root=tps'
    $url = 'https://web.pcc.gov.tw/prkms/tender/common/noticeDate/readUnPublish?dateStr=' . urlencode($date);
    curl_setopt($curl, CURLOPT_URL, $get_proxy_url($url));
    //curl_setopt($curl, CURLOPT_INTERFACE, '10.0.200.18');
    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
        'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/59.0.3071.86 Safari/537.36',
    ));
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    $output = curl_exec($curl);
    curl_close($curl);
    if (strlen($output) == 0) {
        error_log("fail {$ymd}, sleep 30 seconds");
        sleep(30);
        $now += 86400;
        continue;
    }
    file_put_contents($target, gzencode($output));
    error_log("crawled {$ymd} : " . strlen($output));
    sleep(1);
}
