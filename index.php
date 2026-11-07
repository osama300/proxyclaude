<?php
// Anthropic API proxy — حط هذا الملف + .env + .htaccess في جذر الموقع
$env = [];
foreach (@file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $l)
    if ($l[0] !== '#' && str_contains($l, '=')) { [$k, $v] = explode('=', $l, 2); $env[trim($k)] = trim($v, " \"'"); }

$upstream = rtrim($env['UPSTREAM'] ?? 'https://api.anthropic.com', '/');
$clients  = array_filter(array_map('trim', explode(',', $env['CLIENT_KEYS'] ?? '')));

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: *');
    header('Access-Control-Allow-Methods: *');
    exit;
}

// مفتاح العميل: x-api-key أو Authorization: Bearer
$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
$key  = $_SERVER['HTTP_X_API_KEY'] ?? (preg_match('/^Bearer\s+(.+)$/i', $auth, $m) ? $m[1] : '');
if (!in_array($key, $clients, true)) {
    http_response_code(401);
    header('Content-Type: application/json');
    exit('{"type":"error","error":{"type":"authentication_error","message":"invalid x-api-key"}}');
}

// تمرير كل الهيدرات ما عدا المفاتيح والهيدرات الخاصة بالاتصال
$skip = ['host','x-api-key','authorization','content-length','connection','accept-encoding','cookie','expect'];
$h = ['x-api-key: ' . $env['ANTHROPIC_API_KEY'], 'Expect:'];
foreach ($_SERVER as $k => $v)
    if (str_starts_with($k, 'HTTP_') && !in_array($n = strtolower(strtr(substr($k, 5), '_', '-')), $skip, true)
        && !str_starts_with($n, 'x-forwarded') && $n !== 'content-type') $h[] = "$n: $v";
if (!empty($_SERVER['CONTENT_TYPE'])) $h[] = 'content-type: ' . $_SERVER['CONTENT_TYPE'];

while (ob_get_level()) ob_end_clean();
set_time_limit(0);
header('X-Accel-Buffering: no');

$ch = curl_init($upstream . $_SERVER['REQUEST_URI']);
curl_setopt_array($ch, [
    CURLOPT_CUSTOMREQUEST  => $_SERVER['REQUEST_METHOD'],
    CURLOPT_HTTPHEADER     => $h,
    CURLOPT_TIMEOUT        => 900,
    CURLOPT_HEADERFUNCTION => function ($c, $line) {
        if (preg_match('#^HTTP/\S+ (\d+)#', $line, $m)) http_response_code((int)$m[1]);
        elseif (str_contains($line, ':') && !preg_match('/^(transfer-encoding|content-length|connection|content-encoding|set-cookie)\s*:/i', $line))
            header(trim($line), false);
        return strlen($line);
    },
    CURLOPT_WRITEFUNCTION  => function ($c, $d) { echo $d; flush(); return strlen($d); },
]);
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'HEAD'])) curl_setopt($ch, CURLOPT_POSTFIELDS, file_get_contents('php://input'));
if (curl_exec($ch) === false && !headers_sent()) {
    http_response_code(502);
    header('Content-Type: application/json');
    echo '{"type":"error","error":{"type":"api_error","message":"upstream unavailable"}}';
}
