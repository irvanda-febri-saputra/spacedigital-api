<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\UserGateway;
use App\Models\PaymentGateway;

$userId = 4;

echo "=== Debug Query for User ID: {$userId} ===\n\n";

// Test 1: Simple query
echo "Test 1 - All active gateways for user:\n";
$all = UserGateway::where('user_id', $userId)->where('is_active', true)->with('gateway')->get();
foreach ($all as $ug) {
    echo "  - Gateway: " . ($ug->gateway->code ?? 'N/A') . " | Has token: " . (isset($ug->credentials['token']) ? 'Yes' : 'No') . "\n";
}

echo "\nTest 2 - whereHas with orderkuota:\n";
$test2 = UserGateway::where('user_id', $userId)
    ->where('is_active', true)
    ->whereHas('gateway', fn($q) => $q->where('code', 'like', '%orderkuota%'))
    ->first();
echo "  Result: " . ($test2 ? "Found ID: {$test2->id}" : "NOT FOUND") . "\n";

echo "\nTest 3 - whereHas with OR:\n";
$test3 = UserGateway::where('user_id', $userId)
    ->where('is_active', true)
    ->whereHas('gateway', fn($q) => $q->where('code', 'like', '%orderkuota%')
        ->orWhere('code', 'like', '%pakasir%'))
    ->get();
echo "  Results count: " . $test3->count() . "\n";
foreach ($test3 as $ug) {
    echo "  - ID: {$ug->id} | Gateway: " . ($ug->gateway->code ?? 'N/A') . "\n";
}

echo "\nTest 4 - Get orderkuota gateway directly:\n";
$gateway = PaymentGateway::where('code', 'orderkuota')->first();
if ($gateway) {
    $ug = UserGateway::where('user_id', $userId)
        ->where('gateway_id', $gateway->id)
        ->where('is_active', true)
        ->first();
    echo "  Gateway ID: {$gateway->id}\n";
    echo "  UserGateway: " . ($ug ? "Found ID: {$ug->id}" : "NOT FOUND") . "\n";
    if ($ug) {
        echo "  Token: " . (isset($ug->credentials['token']) ? 'EXISTS' : 'NOT SET') . "\n";
    }
}

echo "\n=== End Debug ===\n";
