<?php

include(__DIR__ . '/../config.php');
$get_proxy_url = function($url, $if_id) {
    $secret = getenv('PROXY_SECRET');
    $url = getenv('PROXY_URL') . '?url=' . urlencode($url) . '&sig=' . md5($secret . $url) . '&c=' . $if_id . '&with_cookie=1';
    return $url;
};

$if_count = 5;
ini_set('memory_limit', '512m');
date_default_timezone_set('Asia/Taipei');
$now = $_SERVER['argv'][1] ? strtotime($_SERVER['argv'][1]) : time() - 86400;
$end_date = $_SERVER['argv'][2] ? strtotime($_SERVER['argv'][2]) : 0;
$sleep_time = 100;
#$now = strtotime('2017/1/1');

$set_curl_if = function($curl, $v) use (&$if_id) {
    $if_id = $v;
    return;
};

$hack_captcha = function($content, $if_id) use ($set_curl_if, $get_proxy_url) {
    // id = /tps/tpam/validate.do?id=k9SbtD25s6r0Msvd4z52WaOBJnYw8F
    preg_match('#/tps/validate/check\?id=([^&"]*)#', $content, $matches);
    $url = 'https://web.pcc.gov.tw' . $matches[0];
    $validate_id = $matches[1];

    // load cookie
    $curl = curl_init($get_proxy_url($url, $if_id));
    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
        'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/59.0.3071.86 Safari/537.36',
    ));
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($curl, CURLOPT_COOKIEFILE, '/tmp/cookiefile');
    curl_setopt($curl, CURLOPT_COOKIEJAR, '/tmp/cookiefile');
    $content = curl_exec($curl);

    preg_match('#<input type="hidden" name="_csrf" value="([^"]+)"#', $content, $matches);
    $csrf = $matches[1];

    // answer pic
    preg_match('#/tps/validate/init\?poker=answer&[0-9.]*#', $content, $matches);
    $answer_pic_url = $matches[0];
    curl_setopt($curl, CURLOPT_URL, $get_proxy_url('https://web.pcc.gov.tw' . $answer_pic_url, $if_id));
    $answer_pic_content = curl_exec($curl);
    $info = curl_getinfo($curl);
    file_put_contents('tmp', $answer_pic_content);

    if (!$answer_pic = imagecreatefrompng('tmp')) {
        error_log("圖片下載失敗");
        return;
    }
    // 先找直線
    $height = imagesy($answer_pic);
    $answer_cards = array();
    foreach (array(6, 89) as $left) {
        $answer_card = array();
        for ($x = 0; $x < 69; $x ++) {
            $line_info = array();
            for ($y = 0; $y < 80; $y ++) {
                $color = imagecolorat($answer_pic, $left + 1 + $x, $height / 2 - 40 + $y);

                $line_info[$y] = $color;
            }
            $answer_card[] = $line_info;
        }
        $answer_cards[] = $answer_card;
    }

    preg_match_all('#/tps/validate/init\?poker=question&id=([^"]*)#', $content, $matches);
    $answers = array();
    foreach ($matches[0] as $idx => $question_pic_url) {
        curl_setopt($curl, CURLOPT_URL, $get_proxy_url('https://web.pcc.gov.tw' . $question_pic_url, $if_id));
        $answer_pic_content = curl_exec($curl);
        file_put_contents('tmp', $answer_pic_content);
        $question_pic = imagecreatefrompng('tmp');
        // 先找直線
        $height = imagesy($answer_pic);

        foreach ($answer_cards as $ans => $answer_card) {
            $total = 0;
            $wrong = 0;
            for ($x = 0; $x < 69; $x ++) {
                for ($y = 0; $y < 80; $y ++) {
                    $color = imagecolorat($question_pic, $x + 1, $height / 2 - 40 + $y);

                    if (!in_array($color, array(16711680, 16777215, 0))) {
                        continue;
                    }
                    if (!in_array($answer_card[$x][$y], array(16711680, 16777215, 0))) {
                        continue;
                    }
                    $total ++;

                    if ($color != $answer_card[$x][$y]) {
                        continue 3;
                    }

                }
            }
            $answers[] = $matches[1][$idx];
        }
    }

    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
        'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/59.0.3071.86 Safari/537.36',
        'Referer: https://web.pcc.gov.tw/tps/QueryTender/query/searchTenderDetail',
    ));
    curl_setopt($curl, CURLOPT_URL, $get_proxy_url('https://web.pcc.gov.tw/tps/validate/check', $if_id));
    $post_field = "choose={$answers[0]}&choose={$answers[1]}&id={$validate_id}&_csrf={$csrf}";
    error_log($post_field);

    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $post_field);
    $content = curl_exec($curl);
    $info = curl_getinfo($curl);
};

$if_id = 0;
$skip_if = array();
while ($now >= $end_date) {
    $ymd = date('Ymd', $now);
    $year = date('Y', $now);
    $target = "list/{$ymd}.html.gz";
    $np_target = "list/np-{$ymd}.html.gz";
    $now -= 86400;
    $records = array();
    if (file_Exists($target)) {
        $doc = new DOMDocument;
        @$doc->loadHTML(gzdecode(file_get_contents($target)));

        foreach ($doc->getElementsByTagName('a') as $a_dom) {
            if ($a_dom->getAttribute('class') == 'tenderLinkPublish') {
                $records[] = $a_dom->getAttribute('href');
            }
        }
    }
    if (file_Exists($np_target)) {
        $doc = new DOMDocument;
        @$doc->loadHTML(gzdecode(file_get_contents($np_target)));

        foreach ($doc->getElementsByTagName('a') as $a_dom) {
            if (strpos($a_dom->getAttribute('class'), 'tenderLinkUnPublish') !== false) {
                $href = $a_dom->getAttribute('href');
                if (preg_match('#unPublish.(tender|award).(.*)#', $href, $matches)) {
                    // ttd
                    $records[] = $href;
                } elseif (preg_match('#unPublish.nonAward.(.*)#', $href, $matches)) {
                    // anaa
                    $records[] = $href;
                } elseif (preg_match('#unPublish\.tender\.(.*)#', $href, $matches)) {
                    // aaa
                    $records[] = $href;
                } elseif (preg_match('#unPublish\.gpa\.(.*)#', $href, $matches)) {
                    // TODO: 採購預告公告
                    continue;
                } elseif (preg_match('#unPublish\.tpRead\.(.*)#', $href, $matches)) {
                    $records[] = $href;
                } elseif (preg_match('#unPublish\.(aspam|arpam)\.(.*)#', $href, $matches)) {
                    // TODO: 財物變更公告
                } elseif (preg_match('#/opas/aspam/public/readOneAspamDetailNew#', $href, $matches)) {
                    // TODO: 財物變更公告
                } elseif (preg_match('#/opas/arpam/public/readOneArpamDetailNew#', $href, $matches)) {
                    // TODO: 財物出租公告
                } elseif (preg_match('#unPublish\.tpAppeal\.(.*)#', $href, $matches)) {
                    // TODO: 公開徵求廠商提供參考資料(不刊公報)
                } else {
                    print_r($href);
                    echo "\n";
                    readline($np_target . ' wrong!!!');
                }
            }
        }
    }

    if (!$records) {
        continue;
    }

    $total = count($records);
    error_log("crawling {$ymd} " . $total);
    for ($seq = 0; $seq < count($records); $seq ++) {
        $record = $records[$seq];
        if (strpos($record, 'xml')) {
            $url = "https://web.pcc.gov.tw/prkms/tender/common/noticeDate/redirectPublic?ds={$ymd}&fn={$record}";
        } elseif (preg_match('#unPublish.tender.(.*)#', $record, $matches)) {
            $url = "https://web.pcc.gov.tw/prkms/urlSelector/common/tpam?pk=" . $matches[1];
            $record = 'ttd-' . base64_decode($matches[1]);
        } elseif (preg_match('#unPublish.nonAward.(.*)#', $record, $matches)) {
            $url = "https://web.pcc.gov.tw/prkms/urlSelector/common/nonAtm?pk=" . $matches[1];
            $record = 'anaa-' . base64_decode($matches[1]);
        } elseif (preg_match('#unPublish\.award\.(.*)#', $record, $matches)) {
            $url = "https://web.pcc.gov.tw/prkms/urlSelector/common/atm?pk=" . $matches[1];
            $record = 'aaa-' . base64_decode($matches[1]);
        } elseif (preg_match('#unPublish\.tpRead\.(.*)#', $record, $matches)) {
            $url = "https://web.pcc.gov.tw/prkms/urlSelector/common/tpRead?pk=" . $matches[1];
            $record = 'idpr-' . base64_decode($matches[1]);
        } else {
            print_r($record);
            exit;
        }
        if (in_array($url, [
            'http://web.pcc.gov.tw/prkms/prms-viewTenderDetailClient.do?ds=20220321&fn=BDM-2-53517820.xml', // too big
        ])) {
            continue;
        }
        $entry_target = "entry/{$year}/{$ymd}-{$record}.gz";
        if (file_exists($entry_target)) {
            $old_content = gzdecode(file_get_contents($entry_target));
        }

        if (!file_exists($entry_target)) { // 找不到檔案 
        } elseif (filesize($entry_target) == 20) { // 空檔
        } else if (strpos($old_content, '為預防惡意程式針對本系統進行大量') !== FALSE) { // captcha
        } else if (strpos($old_content, '政府電子採購網_失敗訊息畫面') !== FALSE) { //
        } else if (strpos($old_content, 'Web Page Blocked') !== FALSE) { // captcha
        } else if (strpos($old_content, '特別預算類型為') !== FALSE) { // old format
        } else if (strpos($old_content, '您尚未登入或已被登出本系統') !== FALSE) { // old format
        } else if (strpos($old_content, '500 Internal Server Error') !== FALSE) {
        } else if (strpos($old_content, 'Internal Server Error') !== FALSE) {
        } else if (strpos($old_content, '尚無資料') !== FALSE ){
        } else if (strpos($old_content, '錯誤訊息') !== FALSE ){
        } elseif (strpos($old_content, '</html>') === false) { //
        } else {
            continue;
        }
        $curl = curl_init();
        // curl -interface eth1 'http://web.pcc.gov.tw/prkms/prms-viewTenderStatClient.do?ds=20170628&root=tps' 
        // -H 'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/59.0.3071.86 Safari/537.36' 
        // -H 'Referer: http://web.pcc.gov.tw/prkms/prms-viewDailyTenderListClient.do?root=tps'
        $proxy_url = $get_proxy_url($url, $if_id);
        curl_setopt($curl, CURLOPT_URL, $proxy_url);
        curl_setopt($curl, CURLOPT_TIMEOUT, 300);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        $set_curl_if($curl, $if_id);
        $current_if_id = $if_id;
        $wait_time = 30;
        while (true) {
            if (count($skip_if) == $if_count) {
                error_log("都不能用了，等 {$wait_time} 秒看看");
                sleep($wait_time);
            }
            $if_id = ($if_id + 1) % $if_count;
            if (!array_key_exists($if_id, $skip_if) or $skip_if[$if_id] < time() - $wait_time) {
                unset($skip_if[$if_id]);
                break;
            }
        }


        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/59.0.3071.86 Safari/537.36',
            'Referer: http://web.pcc.gov.tw/prkms/prms-viewDailyTenderListClient.do?root=tps',
        ));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        //curl_setopt($curl, CURLINFO_HEADER_OUT, true);
        $output = curl_exec($curl);
        $info = curl_Getinfo($curl);
        curl_close($curl);

        if (strpos($output, '為預防惡意程式針對本系統進行大量')) {
            //usleep($sleep_time * 10000 / ($if_count - count($skip_if)));
            error_log("crawled {$ymd} {$seq}/{$total} {$record} failed, 遇到驗證碼" . strlen($output));
            $output = $hack_captcha($output, $current_if_id);
            $seq --;
            continue;

            $skip_if[$current_if_id] = time();
            if (count($skip_if) == $if_count) { 
                //throw new Exception("全部界面都不能用了");
            }

            //sleep(60);
            //usleep($sleep_time * 10000 / ($if_count - count($skip_if)));
            continue;
        } elseif (strpos($output, 'Web Page Blocked')) {
            error_log("if_id={$current_if_id} 無法使用 $url");
            $skip_if[$current_if_id] = time();
            if (count($skip_if) == $if_count) { 
                //throw new Exception("全部界面都不能用了");
            }
            sleep(5);
            $seq --;
            continue;
        } elseif (strlen($output) == 0) {
            error_log(json_encode($info, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            error_log("{$ymd} {$record} {$url} failed, retry (if_id={$current_if_id})");
            $skip_if[$current_if_id] = time(); // 等 30 秒
            //usleep($sleep_time * 10000 / ($if_count - count($skip_if)));
            //sleep(10);
            $seq --;
            continue;
        }

        if (strlen($output)) {
            file_put_contents($entry_target, gzencode($output));
        } else {
            //error_log("crawled {$ymd} {$seq}/{$total} {$record} " . strlen($output));
            sleep(60);
        }
        fwrite(STDERR, chr(27) . "k{$ymd} {$seq}/{$total}" . chr(27) . "\\");
        error_log(date('His') . " crawled {$ymd} {$seq}/{$total} {$record} " . strlen($output) . " if_id={$current_if_id}");
        //usleep($sleep_time * 180000 / $if_count);
    }
    error_log("crawled {$ymd} " . $total);
}
