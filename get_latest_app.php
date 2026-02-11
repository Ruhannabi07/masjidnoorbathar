<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Allow CORS if needed, though mostly same-origin

define('DATA_FILE', 'data/app_data.json');

if (file_exists(DATA_FILE)) {
    readfile(DATA_FILE);
} else {
    echo json_encode([]);
}
?>
