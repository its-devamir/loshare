<?php
$uploadDir = __DIR__ . '/files/';

if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

header('Access-Control-Allow-Origin: *'); // optional, for easier debugging

// Handle file upload via Dropzone
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $file = $_FILES['file'];
    $target = $uploadDir . basename($file['name']);
    if (move_uploaded_file($file['tmp_name'], $target)) {
        echo json_encode(['success' => true, 'file' => $file['name']]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false]);
    }
    exit;
}

// List files
$files = array_diff(scandir($uploadDir), ['.', '..']);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>📂 Local File Server</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@3.3.3/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/dropzone@5.9.3/dist/min/dropzone.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/dropzone@5.9.3/dist/min/dropzone.min.js"></script>
</head>
<body class="bg-gray-100 p-4">
    <div class="max-w-xl mx-auto">
        <h1 class="text-3xl font-bold text-center mb-6">📂 Local File Server</h1>

        <!-- Dropzone Upload -->
        <form action="" class="dropzone mb-6 p-6 border-2 border-dashed border-gray-300 bg-white rounded-lg shadow-sm" id="fileDropzone"></form>

        <!-- File List -->
        <h2 class="text-xl font-semibold mb-2">Files</h2>
        <ul class="bg-white rounded-lg shadow-sm p-4 space-y-2">
            <?php foreach ($files as $file): ?>
                <li class="flex justify-between items-center">
                    <span class="truncate"><?php echo htmlspecialchars($file); ?></span>
                    <a href="files/<?php echo urlencode($file); ?>" download class="text-blue-500 hover:underline">Download</a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <script>
        Dropzone.options.fileDropzone = {
            paramName: "file",
            maxFilesize: 500, // MB
            init: function() {
                this.on("success", function(file, response) {
                    // Reload page or update list
                    location.reload();
                });
            }
        };
    </script>
</body>
</html>
