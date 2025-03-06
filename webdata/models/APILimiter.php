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
        $ip = $_SERVER['REMOTE_ADDR'];
        if (!file_exists($path . "/{$ip}.json")) {
            $ip_data = []; 
        } else {
            $ip_data = json_decode(file_get_contents($path . "/{$ip}.json"));
        }
        if (!is_array($ip_data)) {
            $ip_data = [];
        }

        $block = false;
        if ($ip_data[10] ?? false and $ip_data[10][0] > time() - 10) {
            // 如果最近 10 秒有超過 10 次，就擋掉
            $block =  true;
        }
        if ($ip_data[60] ?? false and $ip_data[60][0] > time() - 60) {
            // 如果最近 60 秒有超過 60 次，就擋掉
            $block =  true;
        }

        array_unshift($ip_data, [time(), $_SERVER['REQUEST_URI']]);
        while (count($ip_data) > 100) {
            array_shift($ip_data);
        }
        file_put_contents($path . "/{$ip}.json", json_encode($ip_data));
        if ($block) {
            header('HTTP/1.1 429 Too Many Requests', true, 429);
            exit;
        }
    }
}

