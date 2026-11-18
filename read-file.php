<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function fail($status, $message) {
    http_response_code($status);
    echo json_encode(array('ok' => false, 'error' => $message), JSON_UNESCAPED_SLASHES);
    exit;
}

$raw = isset($_GET['path']) ? $_GET['path'] : '';
if ($raw === '' && isset($_POST['path'])) {
    $raw = $_POST['path'];
}
$path = trim($raw);

if ($path === '') {
    fail(400, 'Missing file path');
}
if (strpos($path, "\0") !== false) {
    fail(400, 'Invalid file path');
}

if (strpos($path, '~/') === 0) {
    $home = getenv('HOME');
    if (!$home || $home === '/var/www') {
        $home = '/home/ubuntu';
    }
    $path = $home . substr($path, 1);
}

if ($path === '' || $path[0] !== '/') {
    fail(400, 'Use an absolute file path');
}

$real = realpath($path);
if ($real === false) {
    fail(404, 'File not found or not readable');
}
if (!is_file($real) || !is_readable($real)) {
    fail(404, 'File not found or not readable');
}
if (!preg_match('/\.(md|markdown|txt|mdx)$/i', $real)) {
    fail(400, 'Only markdown or text files can be loaded');
}

$allowed = array('/home/', '/var/www/', '/tmp/');
$okRoot = false;
foreach ($allowed as $root) {
    if (strpos($real, $root) === 0) {
        $okRoot = true;
        break;
    }
}
if (!$okRoot) {
    fail(403, 'That path is not allowed');
}

$size = filesize($real);
if ($size === false) {
    fail(500, 'Could not read file');
}
if ($size > 2 * 1024 * 1024) {
    fail(400, 'File is larger than 2 MB');
}

$content = file_get_contents($real);
if ($content === false) {
    fail(500, 'Could not read file');
}

echo json_encode(array(
    'ok' => true,
    'path' => $real,
    'name' => basename($real),
    'content' => $content
), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
