<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

echo "=== Atlantic Gateways in Database ===\n";

$gateways = DB::table('user_gateways')
    ->join('payment_gateways', 'user_gateways.gateway_id', '=', 'payment_gateways.id')
    ->where('payment_gateways.code', 'LIKE', '%atlantic%')
    ->select('user_gateways.*', 'payment_gateways.code', 'payment_gateways.name as gateway_name')
    ->get();

foreach ($gateways as $gw) {
    echo "ID: {$gw->id}\n";
    echo "User ID: {$gw->user_id}\n";
    echo "Gateway ID: {$gw->gateway_id}\n";
    echo "Gateway Code: {$gw->code}\n";
    echo "Gateway Name: {$gw->gateway_name}\n";
    echo "Active: " . ($gw->is_active ? 'Yes' : 'No') . "\n";

    $creds = json_decode($gw->credentials, true);
    if ($creds) {
        $apiKeyPreview = isset($creds['api_key']) ? substr($creds['api_key'], 0, 15) . '...' : 'N/A';
        $metodeValue = $creds['metode'] ?? 'N/A';
        echo "API Key: {$apiKeyPreview}\n";
        echo "Metode: {$metodeValue}\n";
    }
    echo "---\n";
}

echo "\n\n=== Testing Atlantic API with real credentials ===\n";

// Get first active atlantic gateway
$activeGateway = DB::table('user_gateways')
    ->join('payment_gateways', 'user_gateways.gateway_id', '=', 'payment_gateways.id')
    ->where('payment_gateways.code', 'LIKE', '%atlantic%')
    ->where('user_gateways.is_active', true)
    ->select('user_gateways.*', 'payment_gateways.code')
    ->first();

if (!$activeGateway) {
    echo "No active Atlantic gateway found!\n";
    exit;
}

$creds = json_decode($activeGateway->credentials, true);
$apiKey = $creds['api_key'] ?? '';
$metode = $creds['metode'] ?? 'qris';

echo "Using API Key: " . substr($apiKey, 0, 15) . "...\n";
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
