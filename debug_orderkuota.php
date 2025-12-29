<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\UserGateway;
use App\Models\PaymentGateway;

echo "=== Debug Order Kuota Token ===\n\n";

// Get orderkuota gateway
$gateway = PaymentGateway::where('code', 'orderkuota')->first();
echo "Order Kuota Gateway ID: " . ($gateway ? $gateway->id : 'NOT FOUND') . "\n";

if ($gateway) {
    // Get all user gateways for orderkuota
    $userGateways = UserGateway::where('gateway_id', $gateway->id)->get();

    echo "User Gateways for Order Kuota: " . $userGateways->count() . "\n\n";

    foreach ($userGateways as $ug) {
        echo "--- User Gateway ID: {$ug->id} ---\n";
        echo "User ID: {$ug->user_id}\n";
        echo "Is Active: " . ($ug->is_active ? 'Yes' : 'No') . "\n";
        echo "Credentials type: " . gettype($ug->credentials) . "\n";

        if (is_array($ug->credentials)) {
            echo "Credentials keys: " . implode(', ', array_keys($ug->credentials)) . "\n";
            echo "Token exists: " . (isset($ug->credentials['token']) ? 'Yes' : 'No') . "\n";
            if (isset($ug->credentials['token'])) {
                echo "Token (first 20 chars): " . substr($ug->credentials['token'], 0, 20) . "...\n";
            }
            echo "Username: " . ($ug->credentials['username'] ?? 'NOT SET') . "\n";
            echo "QRIS String exists: " . (isset($ug->credentials['qris_string']) ? 'Yes' : 'No') . "\n";
        } else {
            echo "Credentials (raw): " . json_encode($ug->credentials) . "\n";
        }
        echo "\n";
    }
}

echo "\n=== End Debug ===\n";
