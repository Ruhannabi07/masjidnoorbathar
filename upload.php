<?php
header('Content-Type: application/json');

// Constants
define('DATA_FILE', __DIR__ . '/data/app_data.json');
define('UPLOAD_DIR', __DIR__ . '/uploads/');

// Ensure directories exist
if (!is_dir(UPLOAD_DIR . 'apps'))
    mkdir(UPLOAD_DIR . 'apps', 0777, true);
if (!is_dir(UPLOAD_DIR . 'icons'))
    mkdir(UPLOAD_DIR . 'icons', 0777, true);
if (!is_dir('data'))
    mkdir('data', 0777, true);

// Function to send JSON response
function sendResponse($success, $message, $data = [])
{
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit;
}

// Check Request Method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Invalid request method.');
}

// Validate Inputs
$appVersion = $_POST['app_version'] ?? '';
if (empty($appVersion)) {
    sendResponse(false, 'App version is required.');
}

// Helper to handle file upload
function handleUpload($fileInputName, $subDir, $allowTypes)
{
    if (!isset($_FILES[$fileInputName]) || $_FILES[$fileInputName]['error'] !== UPLOAD_ERR_OK) {
        return 'ERR_UPLOAD';
    }

    $file = $_FILES[$fileInputName];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    // Basic type check
    if (!in_array($ext, $allowTypes)) {
        return 'ERR_TYPE';
    }

    $filename = uniqid() . '_' . basename($file['name']);
    $targetPath = UPLOAD_DIR . $subDir . '/' . $filename;

    if (!is_writable(dirname($targetPath))) {
        return 'ERR_MOVE: Target directory not writable: ' . dirname($targetPath);
    }

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return 'uploads/' . $subDir . '/' . $filename;
    }
    $err = error_get_last();
    return 'ERR_MOVE: ' . ($err['message'] ?? 'Unknown error');
}

// Upload Icon
$iconPath = handleUpload('app_icon', 'icons', ['jpg', 'jpeg', 'png', 'webp', 'svg', 'gif', 'ico']);
if (strpos($iconPath, 'ERR_TYPE') === 0) { // Starts with ERR_TYPE
    $ext = isset($_FILES['app_icon']['name']) ? pathinfo($_FILES['app_icon']['name'], PATHINFO_EXTENSION) : 'unknown';
    sendResponse(false, "Invalid icon file type. Detected: $ext");
}
if (strpos($iconPath, 'ERR_MOVE') === 0)
    sendResponse(false, "Failed to save icon file. Debug: $iconPath");
if (strpos($iconPath, 'ERR_UPLOAD') === 0)
    sendResponse(false, 'Icon file missing or upload error.');

// Upload App APK
$appPath = handleUpload('app_file', 'apps', ['apk']);
if (strpos($appPath, 'ERR_TYPE') === 0)
    sendResponse(false, 'Invalid app file type. Must be .apk');
if (strpos($appPath, 'ERR_MOVE') === 0)
    sendResponse(false, "Failed to save app file. Debug: $appPath");
if (strpos($appPath, 'ERR_UPLOAD') === 0)
    sendResponse(false, 'App file missing or upload error.');

// Save Metadata
// First, delete old files if they exist to save space
if (file_exists(DATA_FILE)) {
    $currentData = json_decode(file_get_contents(DATA_FILE), true);
    if ($currentData) {
        // Delete old icon if it exists and is different (though uniqid ensures different)
        if (!empty($currentData['icon_url'])) {
            $oldIconPath = __DIR__ . '/' . $currentData['icon_url'];
            if (file_exists($oldIconPath)) {
                unlink($oldIconPath);
            }
        }
        // Delete old apk if it exists
        if (!empty($currentData['app_url'])) {
            $oldAppPath = __DIR__ . '/' . $currentData['app_url'];
            if (file_exists($oldAppPath)) {
                unlink($oldAppPath);
            }
        }
    }
}

$newData = [
    'version' => $appVersion,
    'icon_url' => $iconPath,
    'app_url' => $appPath,
    'uploaded_at' => date('c')
];

// Perform atomic write typically, but simple file_put_contents is fine for this scale
if (file_put_contents(DATA_FILE, json_encode($newData, JSON_PRETTY_PRINT))) {
    sendResponse(true, 'Upload successful!', $newData);
} else {
    sendResponse(false, 'Failed to save metadata.');
}
?>