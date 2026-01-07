<?php

class APILimiter
{
    public static function check($path, $prefix)
    {
        if (false === strpos($_SERVER['REQUEST_URI'], $prefix)) {
            return;
        }
        if (!file_exists($path)) {
            mkdir($path);
        }
        if (!file_exists($path . '/blocking')) {
            mkdir($path . '/blocking');
        }
        $ip = $_SERVER['REMOTE_ADDR'];
        if (!file_exists($path . "/{$ip}.log")) {
            $ip_data = []; 
        } else {
            clearstatcache();
            $ip_data = array_map(function($l) {
                return explode(',', trim($l), 2);
            }, file($path . "/{$ip}.log"));
        }
        if (!is_array($ip_data)) {
            $ip_data = [];
        }

        $block = false;
        $count = count($ip_data);
        if ($count > 10 and $ip_data[$count - 10][0] > time() - 10) {
            // 如果最近 10 秒有超過 10 次，就擋掉
            $block =  true;
        }
        if ($count > 60 and $ip_data[$count - 60][0] > time() - 60) {
            // 如果最近 60 秒有超過 60 次，就擋掉
            $block =  true;
        }
        // 如果資料已經超過 200 筆，就刪掉最舊的，避免檔案過大
        if ($count > 200) {
            $ip_data = array_slice($ip_data, 100);
            file_put_contents($path . "/{$ip}.log", implode("\n", array_map(function($entry) {
                return implode(',', $entry);
            }, $ip_data)) . "\n");
        }

        file_put_contents($path . "/{$ip}.log", implode(",", [time(), $_SERVER['REQUEST_URI']]) . "\n", FILE_APPEND);
        if ($block) {
            file_put_contents($path . "/blocking/{$ip}.json", json_encode($ip_data));
            header('HTTP/1.1 429 Too Many Requests', true, 429);
            exit;
        }
        @unlink($path . "/blocking/{$ip}.json");
    }
}

