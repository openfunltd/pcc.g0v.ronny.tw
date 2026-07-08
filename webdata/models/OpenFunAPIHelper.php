<?php

/**
 * 歐噴公司各 API 服務共用的 token 驗證與使用記錄輔助類別。
 *
 * 使用方式：
 *   1. 在 bootstrap 呼叫 OpenFunAPIHelper::setUsageLogPath('/path/to/log/dir');
 *   2. 每支 API action 最開頭呼叫 OpenFunAPIHelper::checkUsage(['service' => 'pcc-api', ...]);
 *   3. 回傳資料前呼叫 OpenFunAPIHelper::apiDone(['size' => $bytes, 'count' => $n]);
 *
 * 前提：nginx gateway 已透過 verify_token.lua 驗證 token，
 * 並將 verify_token 回傳的 JSON 以 Base64URL 編碼後放入 X-Token-Info header。
 */
class OpenFunAPIHelper
{
    protected static $_log_path   = null;
    protected static $_token_info = null;
    protected static $_options    = null;
    protected static $_start_time = null;

    public static function setUsageLogPath(string $path): void
    {
        self::$_log_path = rtrim($path, '/');
    }

    /**
     * 在每支 API action 最開頭呼叫。
     *
     * $options:
     *   'service' => string  本服務識別符，用於 scope 比對（如 'pcc-api'）
     *   其餘 key 會保留到 $_options 供 apiDone() 讀取
     */
    public static function checkUsage(array $options): void
    {
        self::$_options    = $options;
        self::$_start_time = microtime(true);
        self::$_token_info = null;

        $raw = $_SERVER['HTTP_X_TOKEN_INFO'] ?? '';
        if ($raw === '') {
            // guest 請求，無 token；由 nginx 做限流
            return;
        }

        // Base64URL decode（補回 = padding）
        $padded = strtr($raw, '-_', '+/');
        $padded = str_pad($padded, strlen($padded) + (4 - strlen($padded) % 4) % 4, '=');
        $json   = base64_decode($padded, true);
        $info   = $json !== false ? json_decode($json, true) : null;

        if (!is_array($info) || !($info['valid'] ?? false)) {
            self::errorJson('Invalid token info', 401);
        }

        self::$_token_info = $info;

        // scope check：若 token 限制了允許呼叫的服務，確認本服務在列表中
        $scope   = $info['scope'] ?? null;
        $service = $options['service'] ?? '';
        if (is_array($scope) && $service !== '' && !in_array($service, $scope, true)) {
            self::errorJson('Token scope not allowed for this service', 403);
        }

        // allowed_origins check：frontend token 限定請求來源
        // 優先用 Origin header（CORS 請求必送），fallback 到 Referer
        $token_type      = $info['token_type'] ?? 'api';
        $allowed_origins = $info['allowed_origins'] ?? null;
        if ($token_type === 'frontend' && is_array($allowed_origins)) {
            $origin_hdr   = $_SERVER['HTTP_ORIGIN']  ?? '';
            $referer_hdr  = $_SERVER['HTTP_REFERER'] ?? '';
            $request_host = '';
            if ($origin_hdr !== '') {
                $request_host = parse_url($origin_hdr, PHP_URL_HOST) ?: '';
            } elseif ($referer_hdr !== '') {
                $request_host = parse_url($referer_hdr, PHP_URL_HOST) ?: '';
            }
            $match = false;
            foreach ($allowed_origins as $allowed) {
                $allowed_host = parse_url($allowed, PHP_URL_HOST) ?: $allowed;
                if ($request_host !== '' && $request_host === $allowed_host) {
                    $match = true;
                    break;
                }
            }
            if (!$match) {
                self::errorJson('Origin not allowed', 403);
            }
        }
    }

    /**
     * 在取得回傳資料後、送出 response 前呼叫。
     * 將此次請求記錄寫入 usage log（JSONL 格式）。
     *
     * $options:
     *   'size'        => int  回應大小（bytes）
     *   'count'       => int  回應筆數
     *   'http_status' => int  HTTP 狀態碼（預設 200）
     */
    public static function apiDone(array $options): void
    {
        if (!self::$_log_path) {
            return;
        }

        $ts          = microtime(true);
        $sec         = (int)$ts;
        $ms          = (int)(($ts - $sec) * 1000);
        $time_str    = gmdate('Y-m-d\TH:i:s', $sec) . '.' . sprintf('%03d', $ms) . 'Z';
        $response_ms = self::$_start_time !== null
            ? (int)round(($ts - self::$_start_time) * 1000)
            : 0;

        $info       = self::$_token_info;
        $sha256     = $info['token_sha256'] ?? '';
        $token_hash = $sha256 !== '' ? substr($sha256, 0, 12) : null;

        $query_params = $_GET ?: null;

        $entry = [
            '_time'        => $time_str,
            '_msg'         => 'api request',
            'service'      => self::$_options['service']   ?? 'unknown',
            'ip'           => $_SERVER['REMOTE_ADDR']       ?? null,
            'user_agent'   => $_SERVER['HTTP_USER_AGENT']   ?? null,
            'referer'      => $_SERVER['HTTP_REFERER']       ?? null,
            'user_id'      => $info ? ($info['user_id']    ?? null) : null,
            'token_hash'   => $token_hash,
            'plan'         => $info ? ($info['plan']       ?? null) : null,
            'source'       => $info ? ($info['token_type'] ?? 'api') : 'guest',
            'token_id'     => null,
            'path'         => $_SERVER['REQUEST_URI']       ?? null,
            'http_status'  => $options['http_status']       ?? 200,
            'record_count' => $options['count']             ?? 0,
            'record_size'  => $options['size']              ?? 0,
            'response_ms'  => $response_ms,
            'query_params' => $query_params,
        ];

        $logfile = self::$_log_path . '/' . gmdate('Y-m-d') . '.log';
        @file_put_contents($logfile, json_encode($entry, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);
    }

    public static function errorJson(string $message, int $status = 400): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode(['error' => true, 'message' => $message], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
