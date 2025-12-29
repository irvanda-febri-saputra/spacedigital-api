<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\UserGateway;

echo "=== All UserGateways for User 4 ===\n\n";

$gateways = UserGateway::where('user_id', 4)->with('gateway')->get();

foreach ($gateways as $g) {
    echo "ID: {$g->id}\n";
    echo "  Gateway: {$g->gateway->code} (gateway_id: {$g->gateway_id})\n";
    echo "  Active: " . ($g->is_active ? 'Yes' : 'No') . "\n";
    echo "  Credential Keys: " . implode(', ', array_keys($g->credentials ?? [])) . "\n";
    echo "  Has token: " . (isset($g->credentials['token']) ? 'Yes' : 'No') . "\n";
    echo "\n";
}

// Check orderkuota gateway
$okGateway = App\Models\PaymentGateway::where('code', 'orderkuota')->first();
echo "Order Kuota PaymentGateway ID: {$okGateway->id}\n";

// Which one should be used?
echo "\nUserGateway with token:\n";
$withToken = UserGateway::where('user_id', 4)
    ->where('is_active', true)
    ->get()
    ->filter(fn($g) => isset($g->credentials['token']));

foreach ($withToken as $g) {
    echo "  ID: {$g->id}, Gateway ID: {$g->gateway_id}\n";
}
