<?php
// Temporary CORS fix untuk domain baru
// Tambahkan ini di bootstrap/app.php atau sebagai middleware

// Add CORS headers for new domain
header("Access-Control-Allow-Origin: https://client.veincloud.net");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Credentials: true");

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Alternative: Multiple domains
/*
$allowed_origins = [
    'https://spacedash.czel.me',
    'https://client.veincloud.net',
    'http://localhost:3000'
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: " . $origin);
}
*/

echo "CORS headers set for client.veincloud.net\n";
?>
