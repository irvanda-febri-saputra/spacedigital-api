<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\UserGateway;
use Illuminate\Support\Facades\Http;

echo "=== Testing Atlantic via Cloudflare Proxy ===\n";

// Get atlantic credentials
$activeGateway = UserGateway::with('gateway')
    ->whereHas('gateway', function($q) {
        $q->where('code', 'LIKE', '%atlantic%');
    })
    ->where('is_active', true)
    ->first();

if (!$activeGateway) {
    echo "No active Atlantic gateway found!\n";
    exit;
}

$creds = $activeGateway->credentials;
$apiKey = $creds['api_key'] ?? '';
$metode = $creds['metode'] ?? 'qris';

echo "API Key: " . substr($apiKey, 0, 20) . "...\n";
echo "Metode: {$metode}\n";

// Test via Cloudflare Worker proxy
$proxyUrl = 'https://workers.czel.me';

$requestData = [
    'target_url' => 'https://atlantich2h.com/deposit/create',
    'api_key' => $apiKey,
    'reff_id' => 'TEST-' . time(),
    'nominal' => 1000,
    'type' => 'ewallet',
    'metode' => $metode,
    'callback_url' => 'https://spacedigital.czel.me/api/payments/webhook/atlantic',
];

echo "\nSending request via proxy: {$proxyUrl}\n";
echo "Target: https://atlantich2h.com/deposit/create\n";

try {
    $response = Http::asForm()->timeout(30)->post($proxyUrl, $requestData);
    
    echo "\nResponse Status: " . $response->status() . "\n";
    echo "Response Body:\n";
    $body = $response->body();
    
    // Check if HTML
    if (str_contains($body, '<!DOCTYPE') || str_contains($body, '<html')) {
        echo "HTML Response (first 500 chars):\n";
        echo substr($body, 0, 500) . "\n";
    } else {
        print_r($response->json());
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
