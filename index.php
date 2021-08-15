<?php

function handler($src, $dest, $format) {
    if (
        empty($src) ||
        type($src) == 'error' ||
        !in_array($dest, ['ng', 'rk', 'quan']) ||
        !in_array($format, ['json', 'plain'])
    ) {
        return [
            'status' => 400,
        ];
    }
    switch (type($src)) {
        case 'link':
            $result = linkconv($src, $dest);
            return [
                'status' => $result == 'error' ? 400 : 200,
                'headers' => [
                    'content-type' =>
                        $format == 'json'
                            ? 'application/json'
                            : 'text/plain; charset=utf-8',
                ],
                'body' =>
                    $format == 'json'
                        ? json_encode(['result' => $result])
                        : $result,
            ];
            break;
        case 'sub':
            $result = subconv($src, $dest);
            return [
                'status' => $result == 'error' ? 400 : 200,
                'headers' => [
                    'content-type' =>
                        $format == 'json'
                            ? 'application/json'
                            : 'text/plain; charset=utf-8',
                ],
                'body' =>
                    $format == 'json'
                        ? json_encode(['result' => $result])
                        : $result,
            ];
            break;
        default:
            return [
                'status' => 400,
            ];
            break;
    }
}

function type($src) {
    if (strpos($src, 'vmess://') === 0) {
        return 'link';
    } elseif (strpos($src, 'http') === 0 || strpos($src, 'https') === 0) {
        return 'sub';
    } else {
        return 'error';
    }
}

function socket($msg) {
    $socket = @socket_create(AF_UNIX, SOCK_STREAM, 0);
    @socket_connect($socket, '/tmp/vmess_convert_api.sock');
    @socket_send($socket, $msg, strlen($msg), 0);
    $response = @socket_read($socket, 1024);
    @socket_close($socket);
    if ($response === false) {
        echo "Warning: Failed to connect to daemon! Is the daemon running?\n";
        return json_encode([
            'v2rayn' => 'error',
            'shadowrocket' => 'error',
            'quantumult' => 'error',
        ]);
    }
    return $response;
}

function linkconv($src, $dest) {
    $result = json_decode(socket($src), true);
    switch ($dest) {
        case 'ng':
            return $result['v2rayn'];
            break;
        case 'rk':
            return $result['shadowrocket'];
            break;
        case 'quan':
            return $result['quantumult'];
            break;
        default:
            return $result['v2rayn'];
            break;
    }
}

function subconv($src, $dest) {
    $sub = @file_get_contents($src);
    $sub = explode("\n", base64_decode($sub));
    if (empty($sub)) {
        return 'error';
    }
    $result = '';
    foreach ($sub as $k) {
        if (type($k) != 'link') {
            continue;
        }
        $link = linkconv($k, $dest);
        if ($link == 'error') {
            continue;
        }
        $result = $result . $link . "\n";
    }
    if (empty($result)) {
        return 'error';
    }
    return base64_encode($result);
}

function main() {
    $src = $_GET['src'];
    $dest = $_GET['dest'] ?? 'ng';
    $format = $_GET['format'] ?? 'json';
    $result = handler($src, $dest, $format);
    if ($result['status'] >= 400) {
        http_response_code($result['status']);
        return;
    }
    header('Access-Control-Allow-Origin: *');
    if ($result['status'] >= 200) {
        if (!empty($result['headers'])) {
            foreach ($result['headers'] as $k => $v) {
                header($k . ': ' . $v);
            }
        }
        echo $result['body'];
        return;
    }
}

main();
