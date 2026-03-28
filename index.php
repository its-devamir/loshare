<?php
// index.php — Local file transfer web app (single-file)
// Place this file in a folder and run: php -S 0.0.0.0:3000
// Then open http://<PC_IP>:3000/ on your phone and PC (replace <PC_IP> with your computer's LAN IP).

// --- Basic router ---
$uploadsDir = __DIR__ . '/uploads';
$filesDir   = __DIR__ . '/files';
if (!is_dir($uploadsDir)) mkdir($uploadsDir, 0777, true);
if (!is_dir($filesDir)) mkdir($filesDir, 0777, true);

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Security: sanitize names when saving/listing
function safe_name($name) {
    // remove path components and control characters
    $name = basename($name);
    $name = preg_replace('/[^\w.\-\s]/u', '_', $name);
    return $name;
}

if ($method === 'POST' && ($action === 'upload')) {
    // Handle AJAX multi-file upload (files[])
    $results = [];
    if (!empty($_FILES['files'])) {
        foreach ($_FILES['files']['error'] as $i => $err) {
            $orig = $_FILES['files']['name'][$i];
            $tmp  = $_FILES['files']['tmp_name'][$i];
            $size = $_FILES['files']['size'][$i];
            if ($err === UPLOAD_ERR_OK && is_uploaded_file($tmp)) {
                $safe = time() . '_' . safe_name($orig);
                $dest = $uploadsDir . '/' . $safe;
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
    header('Content-Type: application/json');
    echo json_encode($results);
    exit;
}

if ($method === 'GET' && $action === 'list') {
    // Return JSON list of files in /files and /uploads (metadata)
    $list = [];
    foreach (['files' => $filesDir, 'uploads' => $uploadsDir] as $k => $dir) {
        $entries = [];
        $files = array_values(array_diff(scandir($dir), ['.', '..']));
        foreach ($files as $f) {
            $path = $dir . '/' . $f;
            if (!is_file($path)) continue;
            $entries[] = [
                'name' => $f,
                'orig' => $f,
                'size' => filesize($path),
                'mtime' => filemtime($path),
                'url'  => sprintf('%s/%s/%s', rtrim(pathinfo($_SERVER['SCRIPT_NAME'], PATHINFO_DIRNAME), '/'), $k, rawurlencode($f)),
                'which' => $k
            ];
        }
        $list[$k] = $entries;
    }
    header('Content-Type: application/json');
    echo json_encode($list);
    exit;
}

// Serve static file downloads from /files and /uploads when requested as /files/<name> or /uploads/<name>
$scriptName = $_SERVER['SCRIPT_NAME'];
$uri = $_SERVER['REQUEST_URI'];
// If the request is directly to /files/... or /uploads/..., serve from disk (this helps when using php -S 0.0.0.0:3000)
if (preg_match('#^/(files|uploads)/(.+)$#', parse_url($uri, PHP_URL_PATH), $m)) {
    $which = $m[1];
    $name  = rawurldecode($m[2]);
    $safe = basename($name);
    $base = ($which === 'files') ? $filesDir : $uploadsDir;
    $path = $base . '/' . $safe;
    if (is_file($path)) {
        // Serve with headers
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $path) ?: 'application/octet-stream';
        finfo_close($finfo);
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: attachment; filename="' . rawurlencode($safe) . '"');
        readfile($path);
        exit;
    } else {
        http_response_code(404);
        echo 'Not found';
        exit;
    }
}

// Fall through: render the HTML UI
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Local Transfer — iPhone ↔ PC</title>
<style>
:root{--bg:#0f1724;--card:#0b1220;--muted:#9aa4b2;--accent:#7dd3fc}
*{box-sizing:border-box;font-family:Inter, system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial}
html,body{height:100%;margin:0;background:linear-gradient(180deg,#071022 0%, #071727 100%);color:#e6eef6}
.container{max-width:980px;margin:28px auto;padding:18px}
.header{display:flex;align-items:center;justify-content:space-between;gap:12px}
.brand{display:flex;gap:12px;align-items:center}
.logo{width:48px;height:48px;border-radius:10px;background:linear-gradient(135deg,#06b6d4,#60a5fa);display:flex;align-items:center;justify-content:center;font-weight:700;color:#022}
.h1{font-size:20px}
.card{background:rgba(255,255,255,0.02);padding:16px;border-radius:12px;margin-top:12px}
.uploader{border:2px dashed rgba(255,255,255,0.04);padding:18px;border-radius:10px;text-align:center}
.input{display:none}
.btn{background:linear-gradient(90deg,#06b6d4,#60a5fa);border:none;padding:10px 14px;border-radius:9px;color:#022;font-weight:600;cursor:pointer}
.list{display:grid;grid-template-columns:1fr 220px;gap:12px;margin-top:12px}
.files{padding:8px}
.file-row{display:flex;justify-content:space-between;align-items:center;padding:10px;border-radius:8px;background:rgba(255,255,255,0.01);margin-bottom:8px}
.small{font-size:12px;color:var(--muted)}
.progress{height:8px;background:rgba(255,255,255,0.03);border-radius:6px;overflow:hidden}
.progress > i{display:block;height:100%;width:0%;background:linear-gradient(90deg,#34d399,#60a5fa)}
.drop-hint{color:var(--muted);margin-top:8px}
.speed{font-variant-numeric:tabular-nums}
.footer{font-size:12px;color:var(--muted);margin-top:14px}
@media(max-width:720px){.list{grid-template-columns:1fr;}.brand .h1{font-size:16px}}
</style>
</head>
<body>
<div class="container">
  <div class="header">
    <div class="brand">
      <div class="logo">⇄</div>
      <div>
        <div class="h1">Local Transfer — iPhone ↔ PC</div>
        <div class="small">Upload from phone, download on PC and vice versa. No other app.</div>
      </div>
    </div>
    <div>
      <button id="refreshBtn" class="btn">Refresh list</button>
    </div>
  </div>

  <div class="card uploader" id="uploader">
    <input id="fileInput" class="input" type="file" multiple />
    <div style="display:flex;gap:10px;justify-content:center;align-items:center;flex-direction:column">
      <div style="font-weight:600">Drop files here or click to choose</div>
      <div class="drop-hint">Multiple files supported · Safari & Chrome supported</div>
      <div style="margin-top:10px"><button id="pickBtn" class="btn">Choose files</button></div>
    </div>
  </div>

  <div class="card">
    <div style="font-weight:700">Transfers</div>
    <div id="transferList" style="margin-top:12px"></div>
  </div>

  <div class="list">
    <div class="card files" id="filesList">
      <div style="font-weight:700;margin-bottom:8px">PC "files" folder (download to phone)</div>
      <div id="pcFiles"></div>
    </div>
    <div class="card files" id="uploadsList">
      <div style="font-weight:700;margin-bottom:8px">Uploads (from phone)</div>
      <div id="uploadedFiles"></div>
    </div>
  </div>

  <div class="footer">Access this page from your phone using your computer's LAN IP (example: http://192.168.1.25:3000/). Run server in the same folder as this file with: <code>php -S 0.0.0.0:3000</code></div>
</div>

<script>
const uploader = document.getElementById('uploader');
const fileInput = document.getElementById('fileInput');
const pickBtn = document.getElementById('pickBtn');
const transferList = document.getElementById('transferList');
const pcFiles = document.getElementById('pcFiles');
const uploadedFiles = document.getElementById('uploadedFiles');
const refreshBtn = document.getElementById('refreshBtn');

pickBtn.addEventListener('click', e => fileInput.click());
fileInput.addEventListener('change', e => handleFiles(e.target.files));

['dragenter','dragover'].forEach(ev => {
  uploader.addEventListener(ev, e => { e.preventDefault(); uploader.style.borderColor = '#60a5fa'; });
});
['dragleave','drop'].forEach(ev => {
  uploader.addEventListener(ev, e => { e.preventDefault(); uploader.style.borderColor = ''; });
});

uploader.addEventListener('drop', e => { e.preventDefault(); if (e.dataTransfer.files) handleFiles(e.dataTransfer.files); });

function humanSize(bytes){
  const units = ['B','KB','MB','GB','TB'];
  let i = 0; while(bytes >= 1024 && i < units.length-1){ bytes /= 1024; i++; }
  return bytes.toFixed( (i===0?0:2) ) + ' ' + units[i];
}

function handleFiles(list){
  const files = Array.from(list);
  files.forEach(uploadFile);
}

function uploadFile(file){
  const id = 't' + Math.random().toString(36).slice(2,9);
  const row = document.createElement('div'); row.className='file-row';
  row.innerHTML = `<div style="min-width:0"><div style="font-weight:700">${file.name}</div><div class="small">${humanSize(file.size)}</div></div><div style="width:260px"><div class="progress" id="p_${id}"><i></i></div><div class="small speed" id="s_${id}">queued</div></div>`;
  transferList.prepend(row);

  const url = '?action=upload';
  const xhr = new XMLHttpRequest();
  const form = new FormData();
  form.append('files[]', file, file.name);

  let lastLoaded = 0; let lastTime = Date.now();
  xhr.upload.addEventListener('progress', e => {
    if (e.lengthComputable) {
      const pct = Math.round((e.loaded / e.total) * 100);
      document.querySelector('#p_' + id + ' i').style.width = pct + '%';
      const now = Date.now();
      const dt = (now - lastTime) / 1000; if (dt > 0.25) {
        const bytes = e.loaded - lastLoaded;
        const speed = bytes / dt; // bytes/sec
        document.getElementById('s_' + id).textContent = humanSize(speed) + '/s';
        lastLoaded = e.loaded; lastTime = now;
      }
    }
  });
  xhr.onreadystatechange = () => {
    if (xhr.readyState === 4) {
      if (xhr.status >= 200 && xhr.status < 300) {
        document.getElementById('s_' + id).textContent = 'done';
        setTimeout(()=>{ refreshList(); }, 700);
      } else {
        document.getElementById('s_' + id).textContent = 'error';
      }
    }
  };
  xhr.open('POST', url);
  xhr.send(form);
}

async function refreshList(){
  try{
    const r = await fetch('?action=list');
    const data = await r.json();
    // PC files
    pcFiles.innerHTML = '';
    if (data.files.length === 0) pcFiles.innerHTML = '<div class="small">No files in /files</div>';
    data.files.forEach(f => {
      const el = document.createElement('div'); el.className='file-row';
      el.innerHTML = `<div style="min-width:0"><div style="font-weight:600">${f.name}</div><div class="small">${humanSize(f.size)} · ${new Date(f.mtime*1000).toLocaleString()}</div></div><div style="display:flex;gap:8px"><a class="btn" href="${f.url}" download>Download</a></div>`;
      pcFiles.appendChild(el);
    });
    // Uploads
    uploadedFiles.innerHTML = '';
    if (data.uploads.length === 0) uploadedFiles.innerHTML = '<div class="small">No uploads yet</div>';
    data.uploads.forEach(f => {
      const el = document.createElement('div'); el.className='file-row';
      el.innerHTML = `<div style="min-width:0"><div style="font-weight:600">${f.name}</div><div class="small">${humanSize(f.size)} · ${new Date(f.mtime*1000).toLocaleString()}</div></div><div style="display:flex;gap:8px"><a class="btn" href="${window.location.href+f.url}" download>Download</a></div>`;
      uploadedFiles.appendChild(el);
    });
  }catch(e){ console.error(e); }
}

refreshBtn.addEventListener('click', refreshList);
refreshList();
// periodic refresh of lists
setInterval(refreshList, 5000);
</script>
</body>
</html>
