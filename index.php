<?php
/**
 * Loshare — LAN file transfer (single PHP file).
 * Run from this directory: php -S 0.0.0.0:PORT index.php  (index.php = router for /pc/ and /phone/)
 * Use start-loshare scripts for high upload limits.
 */

$uploadsRoot = __DIR__ . '/uploads';
$pcDir       = $uploadsRoot . '/pc';
$phoneDir    = $uploadsRoot . '/phone';
foreach ([$uploadsRoot, $pcDir, $phoneDir] as $d) {
    if (!is_dir($d)) {
        mkdir($d, 0777, true);
    }
}

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Built-in PHP server router: allow direct serving of static assets.
// Only whitelisted to `/assets/*` (never serve uploads directly as static files).
if (PHP_SAPI === 'cli-server') {
    $reqPath = parse_url(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/', PHP_URL_PATH);
    if (is_string($reqPath) && strpos($reqPath, '/assets/') === 0) {
        $assetsRoot = realpath(__DIR__ . '/assets');
        $candidate  = realpath(__DIR__ . $reqPath);
        if ($assetsRoot && $candidate && is_file($candidate)) {
            $prefix = $assetsRoot . DIRECTORY_SEPARATOR;
            if (strpos($candidate, $prefix) === 0) {
                return false;
            }
        }
    }
}

/** Web path prefix when not served from document root (e.g. /subdir/index.php). */
function web_path_prefix() {
    $script = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '/index.php';
    $d = str_replace('\\', '/', dirname($script));
    if ($d === '/' || $d === '' || $d === '.') {
        return '';
    }
    return trim($d, '/');
}

/** Build URL path /[prefix/]pc|phone/name */
function file_public_path($which, $filename) {
    $prefix = web_path_prefix();
    $parts = [];
    if ($prefix !== '') {
        $parts[] = $prefix;
    }
    $parts[] = $which;
    $parts[] = rawurlencode($filename);
    return '/' . implode('/', $parts);
}

function safe_name($name) {
    $name = basename($name);
    $name = preg_replace('/[^\w.\-\s]/u', '_', $name);
    return $name;
}

function loshare_is_https() {
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
        return true;
    }
    return false;
}

function loshare_send_common_headers() {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    header("Content-Security-Policy: default-src 'self'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'; object-src 'none'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; connect-src 'self'");
    if (loshare_is_https()) {
        header('Strict-Transport-Security: max-age=15552000; includeSubDomains');
    }
}

/** Convert php.ini size shorthand to bytes (e.g. 8M, 2048K). */
function ini_bytes($val) {
    $val = trim((string) $val);
    if ($val === '') {
        return 0;
    }
    $u = strtolower(substr($val, -1));
    $n = (float) $val;
    if ($u === 'g') {
        return (int) round($n * 1024 * 1024 * 1024);
    }
    if ($u === 'm') {
        return (int) round($n * 1024 * 1024);
    }
    if ($u === 'k') {
        return (int) round($n * 1024);
    }
    return (int) $n;
}

/** Best-effort LAN IPv4 list for the machine running PHP (may be empty if shell_exec is disabled). */
function loshare_detect_ipv4s() {
    $ips = [];
    $saddr = isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : '';
    if ($saddr !== '' && $saddr !== '0.0.0.0' && filter_var($saddr, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $ips[] = $saddr;
    }
    if (!function_exists('shell_exec')) {
        return array_values(array_unique($ips));
    }
    $df = ini_get('disable_functions');
    if (is_string($df) && stripos($df, 'shell_exec') !== false) {
        return array_values(array_unique($ips));
    }
    if (DIRECTORY_SEPARATOR === '\\') {
        $raw = @shell_exec('ipconfig');
        if (is_string($raw) && preg_match_all('/IPv4[^:\r\n]*:\s*(\d+\.\d+\.\d+\.\d+)/i', $raw, $m)) {
            foreach ($m[1] as $ip) {
                if ($ip !== '127.0.0.1') {
                    $ips[] = $ip;
                }
            }
        }
    } else {
        $raw = @shell_exec('hostname -I 2>/dev/null');
        if (is_string($raw)) {
            foreach (preg_split('/\s+/', trim($raw)) as $ip) {
                if ($ip !== '' && $ip !== '127.0.0.1' && strpos($ip, '.') !== false) {
                    $ips[] = $ip;
                }
            }
        }
        if ($ips === [] || (count($ips) === 1 && $ips[0] === $saddr)) {
            $raw = @shell_exec('ip -4 -o addr show scope global 2>/dev/null');
            if (is_string($raw) && preg_match_all('/inet (\d+\.\d+\.\d+\.\d+)/', $raw, $m)) {
                foreach ($m[1] as $ip) {
                    if ($ip !== '127.0.0.1') {
                        $ips[] = $ip;
                    }
                }
            }
        }
    }
    return array_values(array_unique($ips));
}

if ($method === 'GET' && $action === 'hints') {
    $port = isset($_SERVER['SERVER_PORT']) ? (int) $_SERVER['SERVER_PORT'] : 80;
    $um = ini_get('upload_max_filesize');
    $pm = ini_get('post_max_size');
    loshare_send_common_headers();
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode([
        'ipv4' => loshare_detect_ipv4s(),
        'port' => $port,
        'upload_max_filesize' => $um,
        'post_max_size' => $pm,
        'upload_max_bytes' => ini_bytes($um),
        'post_max_bytes' => ini_bytes($pm),
        'limits_tight' => (ini_bytes($um) < 64 * 1024 * 1024) || (ini_bytes($pm) < 64 * 1024 * 1024),
        'sapi' => PHP_SAPI,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($method === 'POST' && $action === 'upload') {
    $results = [];
    if (!empty($_FILES['files'])) {
        foreach ($_FILES['files']['error'] as $i => $err) {
            $orig = $_FILES['files']['name'][$i];
            $tmp  = $_FILES['files']['tmp_name'][$i];
            $size = $_FILES['files']['size'][$i];
            if ($err === UPLOAD_ERR_OK && is_uploaded_file($tmp)) {
                $safe = time() . '_' . safe_name($orig);
                $dest = $phoneDir . '/' . $safe;
                if (move_uploaded_file($tmp, $dest)) {
                    $results[] = ['ok' => true, 'name' => $safe, 'orig' => $orig, 'size' => $size];
                } else {
                    $results[] = ['ok' => false, 'name' => null, 'orig' => $orig, 'error' => 'move_failed'];
                }
            } else {
                $results[] = ['ok' => false, 'name' => null, 'orig' => $orig, 'error' => $err];
            }
        }
    }
    loshare_send_common_headers();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($results);
    exit;
}

if ($method === 'POST' && $action === 'delete') {
    loshare_send_common_headers();
    header('Content-Type: application/json; charset=utf-8');
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true);
    if (!is_array($input)) {
        $input = $_POST;
    }
    $which = isset($input['which']) ? $input['which'] : '';
    $name  = isset($input['name']) ? $input['name'] : '';
    if (!in_array($which, ['pc', 'phone'], true)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'invalid_bucket']);
        exit;
    }
    $safe = basename($name);
    if ($safe === '' || $safe === '.' || $safe === '..') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'invalid_name']);
        exit;
    }
    if (strpos($safe, '.') === 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'dotfiles_not_allowed']);
        exit;
    }
    $base = ($which === 'pc') ? $pcDir : $phoneDir;
    $path = $base . '/' . $safe;
    if (!is_file($path)) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'not_found']);
        exit;
    }
    if (@unlink($path)) {
        echo json_encode(['ok' => true]);
    } else {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'unlink_failed']);
    }
    exit;
}

if ($method === 'GET' && $action === 'list') {
    $list = [];
    foreach (['pc' => $pcDir, 'phone' => $phoneDir] as $k => $dir) {
        $entries = [];
        $files = array_values(array_diff(scandir($dir), ['.', '..']));
        foreach ($files as $f) {
            if (strpos($f, '.') === 0) {
                continue;
            }
            $path = $dir . '/' . $f;
            if (!is_file($path)) {
                continue;
            }
            $entries[] = [
                'name' => $f,
                'orig' => $f,
                'size' => filesize($path),
                'mtime' => filemtime($path),
                'url' => file_public_path($k, $f),
                'which' => $k,
            ];
        }
        usort($entries, function ($a, $b) {
            if ($a['mtime'] === $b['mtime']) return 0;
            return ($a['mtime'] > $b['mtime']) ? -1 : 1;
        });
        $list[$k] = $entries;
    }
    loshare_send_common_headers();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($list);
    exit;
}

$uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
if (preg_match('#^/(pc|phone)/(.+)$#', parse_url($uri, PHP_URL_PATH), $m)) {
    $which = $m[1];
    $name  = rawurldecode($m[2]);
    $safe = basename($name);
    $base = ($which === 'pc') ? $pcDir : $phoneDir;
    $path = $base . '/' . $safe;
    if (is_file($path)) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? finfo_file($finfo, $path) : false;
        if ($finfo) {
            finfo_close($finfo);
        }
        if (!$mime) {
            $mime = 'application/octet-stream';
        }
        loshare_send_common_headers();
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($path));
        $fallback = preg_replace('/[^A-Za-z0-9._-]/', '_', $safe);
        header('Content-Disposition: attachment; filename="' . $fallback . '"; filename*=UTF-8\'\'' . rawurlencode($safe));
        readfile($path);
        exit;
    }
    http_response_code(404);
    loshare_send_common_headers();
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Not found';
    exit;
}

$limits = [
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size' => ini_get('post_max_size'),
    'max_file_uploads' => (int) ini_get('max_file_uploads'),
    'limits_tight' => (ini_bytes(ini_get('upload_max_filesize')) < 64 * 1024 * 1024)
        || (ini_bytes(ini_get('post_max_size')) < 64 * 1024 * 1024),
];
$configJson = json_encode($limits, JSON_UNESCAPED_UNICODE);
$assetsVersion = @filemtime(__DIR__ . '/assets/js/kjua.min.js') ?: 1;
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="description" content="Loshare — transfer files between your phone and PC on the same local network. One PHP file, no cloud.">
<meta name="theme-color" content="#0c1117">
<title>Loshare — LAN file transfer</title>
<!-- <link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet"> -->
<style>
:root {
  --bg0: #080b0f;
  --bg1: #0f1419;
  --card: rgba(255,255,255,0.04);
  --card-border: rgba(255,255,255,0.08);
  --text: #e8edf4;
  --muted: #8b98a8;
  --accent: #3ee0a9;
  --accent-dim: rgba(62, 224, 169, 0.15);
  --danger: #f87171;
  --radius: 14px;
  --font: "IBM Plex Sans", system-ui, -apple-system, "Segoe UI", sans-serif;
  --mono: "IBM Plex Mono", ui-monospace, monospace;
}
*, *::before, *::after { box-sizing: border-box; }
html, body { height: 100%; margin: 0; }
body {
  font-family: var(--font);
  color: var(--text);
  background: var(--bg0);
  background-image:
    radial-gradient(ellipse 120% 80% at 50% -30%, rgba(62, 224, 169, 0.12), transparent 55%),
    linear-gradient(180deg, var(--bg0) 0%, var(--bg1) 100%);
  line-height: 1.45;
  -webkit-font-smoothing: antialiased;
}
.skip-link {
  position: absolute;
  left: -9999px;
  top: 0;
  padding: 12px 16px;
  background: var(--accent);
  color: #042;
  font-weight: 600;
  z-index: 100;
  border-radius: 0 0 8px 0;
}
.skip-link:focus { left: 0; outline: 2px solid var(--text); outline-offset: 2px; }
.wrap { max-width: 920px; margin: 0 auto; padding: 24px 18px 48px; }
.top {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 8px;
}
.brand { display: flex; gap: 14px; align-items: center; }
.mark {
  width: 52px; height: 52px;
  border-radius: 14px;
  background: var(--accent-dim);
  border: 1px solid rgba(62, 224, 169, 0.35);
  display: grid; place-items: center;
  font-size: 22px;
  font-weight: 700;
  color: var(--accent);
}
h1 { font-size: 1.35rem; font-weight: 700; margin: 0; letter-spacing: -0.02em; }
.tagline { color: var(--muted); font-size: 0.9rem; margin-top: 4px; max-width: 36ch; }
.toolbar { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
.btn {
  font-family: var(--font);
  font-size: 0.875rem;
  font-weight: 600;
  border: none;
  border-radius: 10px;
  padding: 10px 16px;
  cursor: pointer;
  background: var(--accent);
  color: #05251a;
  transition: transform 0.12s ease, filter 0.12s ease;
}
.btn:hover { filter: brightness(1.06); }
.btn:active { transform: scale(0.98); }
.btn:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }
.btn-ghost {
  background: var(--card);
  color: var(--text);
  border: 1px solid var(--card-border);
}
.btn-ghost:hover { filter: brightness(1.08); }
.btn-danger {
  background: rgba(248, 113, 113, 0.18);
  color: #fecaca;
  border: 1px solid rgba(248, 113, 113, 0.35);
}
.btn-danger:hover { filter: brightness(1.1); }
.btn-sm { padding: 6px 10px; font-size: 0.8rem; border-radius: 8px; }
.card {
  background: var(--card);
  border: 1px solid var(--card-border);
  border-radius: var(--radius);
  padding: 18px 20px;
  margin-top: 16px;
}
.card h2 {
  font-size: 0.8rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--muted);
  margin: 0 0 12px;
}
.share-row {
  display: flex;
  flex-wrap: wrap;
  gap: 16px;
  align-items: flex-start;
}
.url-box {
  flex: 1;
  min-width: min(100%, 240px);
  font-family: var(--mono);
  font-size: 0.8rem;
  word-break: break-all;
  padding: 12px 14px;
  background: rgba(0,0,0,0.25);
  border-radius: 10px;
  border: 1px solid var(--card-border);
  color: var(--accent);
}
.qr-wrap {
  flex-shrink: 0;
  padding: 10px;
  background: #fff;
  border-radius: 12px;
  line-height: 0;
}
.qr-wrap canvas { display: block; max-width: 140px; height: auto; }
.dropzone {
  border: 2px dashed rgba(255,255,255,0.12);
  border-radius: var(--radius);
  padding: 28px 20px;
  text-align: center;
  transition: border-color 0.15s ease, background 0.15s ease;
}
.dropzone.dragover {
  border-color: var(--accent);
  background: var(--accent-dim);
}
.dropzone:focus-within { border-color: rgba(62, 224, 169, 0.45); }
.drop-title { font-weight: 600; font-size: 1rem; margin-bottom: 6px; }
.drop-hint { color: var(--muted); font-size: 0.875rem; }
.visually-hidden {
  position: absolute;
  width: 1px; height: 1px;
  padding: 0; margin: -1px;
  overflow: hidden;
  clip: rect(0,0,0,0);
  white-space: nowrap;
  border: 0;
}
.grid-2 {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
  margin-top: 16px;
}
@media (max-width: 720px) {
  .grid-2 { grid-template-columns: 1fr; }
}
.file-row {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  align-items: center;
  gap: 10px;
  padding: 12px 14px;
  border-radius: 10px;
  background: rgba(0,0,0,0.2);
  border: 1px solid rgba(255,255,255,0.05);
  margin-bottom: 8px;
}
.file-row:last-child { margin-bottom: 0; }
.file-meta { min-width: 0; flex: 1; }
.file-name { font-weight: 600; font-size: 0.9rem; word-break: break-word; }
.file-sub { color: var(--muted); font-size: 0.75rem; margin-top: 4px; }
.row-actions { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
.progress-track {
  height: 6px;
  background: rgba(255,255,255,0.06);
  border-radius: 99px;
  overflow: hidden;
  margin-top: 8px;
}
.progress-bar {
  height: 100%;
  width: 0%;
  background: linear-gradient(90deg, var(--accent), #5eead4);
  border-radius: 99px;
  transition: width 0.1s linear;
}
.transfer-status { font-size: 0.75rem; color: var(--muted); margin-top: 6px; font-variant-numeric: tabular-nums; }
.empty { color: var(--muted); font-size: 0.875rem; padding: 8px 0; }
.footer {
  margin-top: 28px;
  padding-top: 20px;
  border-top: 1px solid var(--card-border);
  font-size: 0.8rem;
  color: var(--muted);
}
.footer code {
  font-family: var(--mono);
  font-size: 0.78rem;
  background: rgba(0,0,0,0.3);
  padding: 2px 6px;
  border-radius: 6px;
}
.limits { margin-top: 10px; font-size: 0.75rem; opacity: 0.9; }
.limits.warn {
  color: #fcd34d;
  border-left: 3px solid rgba(252, 211, 77, 0.6);
  padding-left: 12px;
  margin-top: 12px;
}
.hints-list {
  list-style: none;
  margin: 0;
  padding: 0;
}
.hints-list li {
  font-family: var(--mono);
  font-size: 0.78rem;
  margin-bottom: 8px;
  word-break: break-all;
}
.hints-list a { color: var(--accent); }
.hint-cmd {
  margin-top: 10px;
  padding: 10px 12px;
  background: rgba(0,0,0,0.35);
  border-radius: 8px;
  font-family: var(--mono);
  font-size: 0.72rem;
  color: var(--muted);
  overflow-x: auto;
  white-space: pre-wrap;
  word-break: break-all;
}
#toast[aria-hidden="true"] { display: none; }
#toast {
  position: fixed;
  bottom: 24px;
  left: 50%;
  transform: translateX(-50%);
  z-index: 50;
  padding: 12px 20px;
  border-radius: 10px;
  background: #1a222c;
  border: 1px solid var(--card-border);
  color: var(--text);
  font-size: 0.875rem;
  box-shadow: 0 12px 40px rgba(0,0,0,0.45);
}
#toast.error { border-color: rgba(248,113,113,0.5); color: #fecaca; }
a.btn { text-decoration: none; display: inline-block; }
</style>
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>
<div id="toast" role="status" aria-live="polite" aria-atomic="true" aria-hidden="true"></div>

<div class="wrap">
  <header class="top">
    <div class="brand">
      <div class="mark" aria-hidden="true">⇄</div>
      <div>
        <h1>Loshare</h1>
        <p class="tagline">Move files between your phone and PC on the same local network (Wi‑Fi or phone hotspot). No accounts, no cloud.</p>
      </div>
    </div>
    <div class="toolbar">
      <button type="button" class="btn btn-ghost" id="refreshBtn">Refresh</button>
    </div>
  </header>

  <main id="main">
    <section class="card" aria-labelledby="share-heading">
      <h2 id="share-heading">Open on another device</h2>
      <div class="share-row">
        <div style="flex:1;min-width:min(100%,200px)">
          <p class="drop-hint" style="margin:0 0 10px">On your phone or tablet, open the same URL as below (must be on the <strong>same network</strong> as this computer—home Wi‑Fi, office LAN, or <strong>mobile hotspot</strong> with this PC connected).</p>
          <div class="url-box" id="pageUrl" tabindex="0"></div>
          <div class="toolbar" style="margin-top:12px">
            <button type="button" class="btn" id="copyUrlBtn">Copy link</button>
          </div>
          <p class="drop-hint" style="margin-top:14px;margin-bottom:6px">This PC’s addresses (port <strong id="portLabel"></strong> — use whatever port you started PHP with):</p>
          <ul class="hints-list" id="hintsList" aria-live="polite"></ul>
          <p class="drop-hint" id="hintsFallback" style="display:none">Could not auto-detect IPs. On Windows run <code style="font-family:var(--mono);font-size:0.8rem">ipconfig</code> and use your Wi‑Fi/Ethernet <strong>IPv4 Address</strong> (not “Default Gateway” — that’s usually your router).</p>
        </div>
        <div class="qr-wrap" id="qrHost" aria-hidden="true"></div>
      </div>
      <p class="limits" id="limitsNote"></p>
      <p class="limits warn" id="limitsWarn" style="display:none"></p>
      <div class="hint-cmd" id="phpCmdHint" style="display:none"></div>
    </section>

    <section class="card" aria-labelledby="upload-heading">
      <h2 id="upload-heading">Upload</h2>
      <div
        class="dropzone"
        id="uploader"
        role="button"
        tabindex="0"
        aria-label="File upload area. Drop files or press Enter to choose."
      >
        <input id="fileInput" class="visually-hidden" type="file" multiple />
        <div class="drop-title">Drop files here or tap to choose</div>
        <div class="drop-hint">Works in Safari on iOS and desktop browsers · multiple files</div>
        <div style="margin-top:14px">
          <button type="button" class="btn" id="pickBtn">Choose files</button>
        </div>
      </div>
    </section>

    <section class="card" aria-labelledby="transfers-heading">
      <h2 id="transfers-heading">Active uploads</h2>
      <div id="transferList" class="empty">No uploads in progress.</div>
    </section>

    <div class="grid-2">
      <section class="card" aria-labelledby="pc-heading">
        <h2 id="pc-heading">PC → phone</h2>
        <p class="drop-hint" style="margin:-8px 0 12px">Put files in <code style="font-family:var(--mono);font-size:0.8rem">uploads/pc</code> on this machine, then download here.</p>
        <div id="pcFiles"></div>
      </section>
      <section class="card" aria-labelledby="up-heading">
        <h2 id="up-heading">Phone → PC</h2>
        <p class="drop-hint" style="margin:-8px 0 12px">Browser uploads are saved under <code style="font-family:var(--mono);font-size:0.8rem">uploads/phone</code>.</p>
        <div id="uploadedFiles"></div>
      </section>
    </div>
  </main>

  <footer class="footer">
    <strong>Loshare</strong> — from this folder use any free port; pass <code>index.php</code> so <code>/pc/</code> and <code>/phone/</code> downloads work, e.g.
    <code>php -S 0.0.0.0:3000 index.php</code>
    or <code>php -S 0.0.0.0:8080 index.php</code>.
    Use <code>start-loshare.bat</code> / <code>start-loshare.ps1</code> for large upload limits.
    <span class="limits" style="display:block;margin-top:8px">Trusted network only: while running, anyone who can reach this IP and port can use this app.</span>
  </footer>
</div>

<!-- <script src="https://cdn.jsdelivr.net/npm/kjua@0.9.0/dist/kjua.min.js" crossorigin="anonymous"></script> -->
<script src="assets/js/kjua.min.js?v=<?php echo (int) $assetsVersion; ?>"></script>
<script>
(function () {
  const CFG = <?php echo $configJson; ?>;

  const uploader = document.getElementById('uploader');
  const fileInput = document.getElementById('fileInput');
  const pickBtn = document.getElementById('pickBtn');
  const transferList = document.getElementById('transferList');
  const pcFiles = document.getElementById('pcFiles');
  const uploadedFiles = document.getElementById('uploadedFiles');
  const refreshBtn = document.getElementById('refreshBtn');
  const pageUrlEl = document.getElementById('pageUrl');
  const copyUrlBtn = document.getElementById('copyUrlBtn');
  const limitsNote = document.getElementById('limitsNote');
  const limitsWarn = document.getElementById('limitsWarn');
  const phpCmdHint = document.getElementById('phpCmdHint');
  const hintsList = document.getElementById('hintsList');
  const hintsFallback = document.getElementById('hintsFallback');
  const portLabel = document.getElementById('portLabel');
  const toast = document.getElementById('toast');

  function showToast(msg, isError) {
    toast.textContent = msg;
    toast.classList.toggle('error', !!isError);
    toast.setAttribute('aria-hidden', 'false');
    clearTimeout(showToast._t);
    showToast._t = setTimeout(function () {
      toast.setAttribute('aria-hidden', 'true');
      toast.textContent = '';
    }, 3200);
  }

  function escapeHtml(s) {
    const d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
  }

  function humanSize(bytes) {
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    let i = 0;
    while (bytes >= 1024 && i < units.length - 1) {
      bytes /= 1024;
      i++;
    }
    return bytes.toFixed(i === 0 ? 0 : 2) + ' ' + units[i];
  }

  function uploadErrLabel(code) {
    const map = {
      1: 'File too large (PHP limit)',
      2: 'File too large (form limit)',
      3: 'Upload incomplete',
      4: 'No file',
      6: 'Server temp missing',
      7: 'Write failed',
      8: 'Extension blocked'
    };
    if (typeof code === 'string') return code;
    return map[code] != null ? map[code] : 'Upload failed (' + code + ')';
  }

  function renderQr(href) {
    var host = document.getElementById('qrHost');
    if (!host) return;
    host.innerHTML = '';
    if (typeof kjua === 'undefined') return;
    try {
      var el = kjua({
        text: href,
        size: 140,
        crisp: true,
        render: 'canvas',
        fill: '#05251a',
        back: '#ffffff',
        quiet: 1,
        ecLevel: 'M'
      });
      if (el) host.appendChild(el);
    } catch (e) {
      console.warn('QR', e);
    }
  }

  function currentPort() {
    var p = window.location.port;
    if (p) return p;
    return window.location.protocol === 'https:' ? '443' : '80';
  }

  function buildUrlForIp(ip) {
    var proto = window.location.protocol;
    var port = window.location.port;
    var path = window.location.pathname + window.location.search;
    var authority = ip;
    if (port && port !== '80' && port !== '443') {
      authority = ip + ':' + port;
    }
    return proto + '//' + authority + path;
  }

  function phpRelaunchCmd() {
    return 'php -d upload_max_filesize=8192M -d post_max_size=8192M -d max_file_uploads=200 -d max_execution_time=0 -S 0.0.0.0:' + currentPort() + ' index.php';
  }

  var seenIps = {};

  function addHintUrl(url) {
    if (!hintsList) return;
    var li = document.createElement('li');
    var a = document.createElement('a');
    a.href = url;
    a.textContent = url;
    li.appendChild(a);
    hintsList.appendChild(li);
    if (hintsFallback) hintsFallback.style.display = 'none';
  }

  function mergeIp(ip) {
    if (!ip || ip.indexOf('127.') === 0 || seenIps[ip]) return;
    seenIps[ip] = true;
    addHintUrl(buildUrlForIp(ip));
  }

  function tryGuessLocalIp(cb) {
    var Ctor = window.RTCPeerConnection || window.webkitRTCPeerConnection || window.mozRTCPeerConnection;
    if (!Ctor) return;
    try {
      var pc = new Ctor({ iceServers: [] });
      pc.createDataChannel('');
      pc.createOffer().then(function (o) { return pc.setLocalDescription(o); }).catch(function () {});
      pc.onicecandidate = function (e) {
        if (!e || !e.candidate || !e.candidate.candidate) return;
        var m = /([0-9]{1,3}(?:\.[0-9]{1,3}){3})/.exec(e.candidate.candidate);
        if (m && m[1].indexOf('127.') !== 0) cb(m[1]);
      };
      setTimeout(function () { try { pc.close(); } catch (x) {} }, 4000);
    } catch (e) {}
  }

  function applyLimitsUi() {
    if (CFG.limits_tight) {
      limitsWarn.style.display = 'block';
      limitsWarn.textContent = 'PHP upload limits look small (' + CFG.upload_max_filesize + ' / ' + CFG.post_max_size + '). Large files may fail until you restart PHP with higher limits — use start-loshare or the command below.';
      phpCmdHint.style.display = 'block';
      phpCmdHint.textContent = phpRelaunchCmd();
    } else {
      limitsWarn.style.display = 'none';
      phpCmdHint.style.display = 'none';
    }
  }

  function loadHints() {
    if (portLabel) portLabel.textContent = currentPort();
    if (hintsList) hintsList.innerHTML = '';
    seenIps = {};
    applyLimitsUi();
    fetch('?action=hints')
      .then(function (r) { return r.json(); })
      .then(function (h) {
        if (h.ipv4 && h.ipv4.length) {
          h.ipv4.forEach(function (ip) { mergeIp(ip); });
        }
        tryGuessLocalIp(function (ip) { mergeIp(ip); });
        setTimeout(function () {
          if (hintsFallback && hintsList && hintsList.children.length === 0) {
            hintsFallback.style.display = 'block';
          }
        }, 500);
        if (typeof h.limits_tight === 'boolean') {
          CFG.limits_tight = h.limits_tight;
        }
        applyLimitsUi();
      })
      .catch(function () {
        tryGuessLocalIp(function (ip) { mergeIp(ip); });
        setTimeout(function () {
          if (hintsFallback && hintsList && hintsList.children.length === 0) {
            hintsFallback.style.display = 'block';
          }
        }, 500);
      });
  }

  function setShareUrl() {
    const href = window.location.href.split('#')[0];
    pageUrlEl.textContent = href;
    renderQr(href);
  }

  limitsNote.textContent = 'PHP limits: max upload ' + CFG.upload_max_filesize + ' · POST max ' + CFG.post_max_size +
    (CFG.max_file_uploads ? ' · up to ' + CFG.max_file_uploads + ' files per request' : '');

  copyUrlBtn.addEventListener('click', function () {
    const t = pageUrlEl.textContent;
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(t).then(function () { showToast('Link copied'); }).catch(function () { fallbackCopy(t); });
    } else {
      fallbackCopy(t);
    }
  });

  function fallbackCopy(text) {
    const ta = document.createElement('textarea');
    ta.value = text;
    ta.style.position = 'fixed';
    ta.style.left = '-9999px';
    document.body.appendChild(ta);
    ta.select();
    try {
      document.execCommand('copy');
      showToast('Link copied');
    } catch (e) {
      showToast('Copy manually from the box above', true);
    }
    document.body.removeChild(ta);
  }

  pickBtn.addEventListener('click', function (e) {
    e.stopPropagation();
    fileInput.click();
  });
  uploader.addEventListener('click', function (e) {
    if (e.target === uploader || e.target.closest('.drop-hint') || e.target.classList.contains('drop-title')) {
      fileInput.click();
    }
  });
  uploader.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      fileInput.click();
    }
  });

  fileInput.addEventListener('change', function (e) {
    handleFiles(e.target.files);
    e.target.value = '';
  });

  ['dragenter', 'dragover'].forEach(function (ev) {
    uploader.addEventListener(ev, function (e) {
      e.preventDefault();
      uploader.classList.add('dragover');
    });
  });
  ['dragleave', 'drop'].forEach(function (ev) {
    uploader.addEventListener(ev, function (e) {
      e.preventDefault();
      uploader.classList.remove('dragover');
    });
  });
  uploader.addEventListener('drop', function (e) {
    e.preventDefault();
    if (e.dataTransfer.files) handleFiles(e.dataTransfer.files);
  });

  function handleFiles(list) {
    Array.from(list).forEach(uploadFile);
  }

  function setTransferPlaceholder() {
    if (!transferList.querySelector('.file-row')) {
      transferList.classList.add('empty');
      transferList.textContent = 'No uploads in progress.';
    }
  }

  function uploadFile(file) {
    transferList.classList.remove('empty');
    if (transferList.textContent === 'No uploads in progress.') {
      transferList.textContent = '';
    }

    const id = 't' + Math.random().toString(36).slice(2, 9);
    const row = document.createElement('div');
    row.className = 'file-row';
    row.innerHTML =
      '<div class="file-meta">' +
        '<div class="file-name">' + escapeHtml(file.name) + '</div>' +
        '<div class="file-sub">' + escapeHtml(humanSize(file.size)) + '</div>' +
        '<div class="progress-track" id="ptr_' + id + '"><div class="progress-bar" id="p_' + id + '"></div></div>' +
        '<div class="transfer-status" id="s_' + id + '">Queued</div>' +
      '</div>';
    transferList.prepend(row);

    const bar = document.getElementById('p_' + id);
    const statusEl = document.getElementById('s_' + id);
    const xhr = new XMLHttpRequest();
    const form = new FormData();
    form.append('files[]', file, file.name);

    var lastLoaded = 0;
    var lastTime = Date.now();
    xhr.upload.addEventListener('progress', function (e) {
      if (e.lengthComputable && bar) {
        bar.style.width = Math.round((e.loaded / e.total) * 100) + '%';
        var now = Date.now();
        var dt = (now - lastTime) / 1000;
        if (dt > 0.25) {
          var speed = (e.loaded - lastLoaded) / dt;
          statusEl.textContent = humanSize(speed) + '/s';
          lastLoaded = e.loaded;
          lastTime = now;
        }
      }
    });

    xhr.onreadystatechange = function () {
      if (xhr.readyState !== 4) return;
      if (xhr.status >= 200 && xhr.status < 300) {
        try {
          var arr = JSON.parse(xhr.responseText);
          var first = Array.isArray(arr) ? arr[0] : null;
          if (first && first.ok) {
            statusEl.textContent = 'Done';
            setTimeout(function () {
              row.remove();
              setTransferPlaceholder();
              refreshList();
            }, 600);
          } else if (first && !first.ok) {
            statusEl.textContent = uploadErrLabel(first.error);
            showToast(first.orig + ': ' + uploadErrLabel(first.error), true);
          } else {
            statusEl.textContent = 'Done';
            row.remove();
            setTransferPlaceholder();
            refreshList();
          }
        } catch (ex) {
          statusEl.textContent = 'Done';
          row.remove();
          setTransferPlaceholder();
          refreshList();
        }
      } else {
        statusEl.textContent = 'Failed (HTTP ' + xhr.status + ')';
        showToast('Upload failed: ' + file.name, true);
      }
    };
    xhr.open('POST', '?action=upload');
    xhr.send(form);
  }

  function makeRow(f, which) {
    const row = document.createElement('div');
    row.className = 'file-row';

    const meta = document.createElement('div');
    meta.className = 'file-meta';
    const nameEl = document.createElement('div');
    nameEl.className = 'file-name';
    nameEl.textContent = f.name;
    const sub = document.createElement('div');
    sub.className = 'file-sub';
    sub.textContent = humanSize(f.size) + ' · ' + new Date(f.mtime * 1000).toLocaleString();
    meta.appendChild(nameEl);
    meta.appendChild(sub);

    const actions = document.createElement('div');
    actions.className = 'row-actions';
    const dl = document.createElement('a');
    dl.className = 'btn btn-sm';
    dl.href = f.url;
    dl.setAttribute('download', '');
    dl.textContent = 'Download';

    const del = document.createElement('button');
    del.type = 'button';
    del.className = 'btn btn-sm btn-danger';
    del.textContent = 'Delete';
    del.addEventListener('click', function () {
      if (!confirm('Delete "' + f.name + '"?')) return;
      fetch('?action=delete', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ which: which, name: f.name })
      })
        .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
        .then(function (x) {
          if (x.ok && x.j && x.j.ok) {
            showToast('Deleted');
            refreshList();
          } else {
            showToast('Could not delete', true);
          }
        })
        .catch(function () { showToast('Could not delete', true); });
    });

    actions.appendChild(dl);
    actions.appendChild(del);
    row.appendChild(meta);
    row.appendChild(actions);
    return row;
  }

  function refreshList() {
    fetch('?action=list')
      .then(function (r) { return r.json(); })
      .then(function (data) {
        pcFiles.innerHTML = '';
        if (!data.pc || data.pc.length === 0) {
          pcFiles.innerHTML = '<div class="empty">No files in <code style="font-family:var(--mono)">uploads/pc</code> yet.</div>';
        } else {
          data.pc.forEach(function (f) {
            pcFiles.appendChild(makeRow(f, 'pc'));
          });
        }

        uploadedFiles.innerHTML = '';
        if (!data.phone || data.phone.length === 0) {
          uploadedFiles.innerHTML = '<div class="empty">Nothing in <code style="font-family:var(--mono)">uploads/phone</code> yet.</div>';
        } else {
          data.phone.forEach(function (f) {
            uploadedFiles.appendChild(makeRow(f, 'phone'));
          });
        }
      })
      .catch(function (e) {
        console.error(e);
        showToast('Could not refresh list', true);
      });
  }

  refreshBtn.addEventListener('click', function () {
    setShareUrl();
    loadHints();
    refreshList();
  });
  setShareUrl();
  loadHints();
  refreshList();
  setInterval(refreshList, 8000);
})();
</script>
</body>
</html>
