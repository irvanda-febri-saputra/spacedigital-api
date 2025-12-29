<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\UserGateway;
use Illuminate\Support\Facades\Http;

echo "=== Atlantic Gateways in Database ===\n";

$gateways = UserGateway::with('gateway')
    ->whereHas('gateway', function($q) {
        $q->where('code', 'LIKE', '%atlantic%');
    })
    ->get();

foreach ($gateways as $gw) {
    echo "ID: {$gw->id}\n";
    echo "User ID: {$gw->user_id}\n";
    echo "Gateway ID: {$gw->gateway_id}\n";
    echo "Gateway Code: " . ($gw->gateway->code ?? 'N/A') . "\n";
    echo "Active: " . ($gw->is_active ? 'Yes' : 'No') . "\n";

    $creds = $gw->credentials; // Auto-decrypted via cast
    if ($creds) {
        $apiKeyPreview = isset($creds['api_key']) ? substr($creds['api_key'], 0, 20) . '...' : 'N/A';
        $metodeValue = $creds['metode'] ?? 'N/A';
        echo "API Key: {$apiKeyPreview}\n";
        echo "Metode: {$metodeValue}\n";
    } else {
        echo "Credentials: EMPTY or failed to decrypt\n";
    }
    echo "---\n";
}

echo "\n\n=== Testing Atlantic API with real credentials ===\n";

// Get first active atlantic gateway
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

echo "Using API Key: " . substr($apiKey, 0, 20) . "...\n";
echo "Metode: {$metode}\n";

$requestData = [
    'api_key' => $apiKey,
    'reff_id' => 'TEST-' . time(),
    'nominal' => 1000,
    'type' => 'ewallet',
    'metode' => $metode,
    'callback_url' => 'https://spacedigital.czel.me/api/payments/webhook/atlantic',
];

echo "\nSending request to Atlantic...\n";

$response = Http::asForm()->timeout(30)->post('https://atlantich2h.com/deposit/create', $requestData);

echo "\nResponse Status: " . $response->status() . "\n";
echo "Response Body:\n";
print_r($response->json());
