<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Payment Gateways ===\n";
$gateways = \App\Models\PaymentGateway::all();
foreach ($gateways as $g) {
    echo $g->id . ' | ' . $g->code . ' | ' . $g->name . ' | active=' . $g->is_active . "\n";
    echo "   required_fields: " . json_encode($g->required_fields) . "\n";
}

echo "\n=== User Gateways (user_id=4) ===\n";
$userGateways = \App\Models\UserGateway::where('user_id', 4)->with('gateway')->get();
foreach ($userGateways as $ug) {
    echo $ug->id . ' | gateway_id=' . $ug->gateway_id . ' | ' . ($ug->gateway->code ?? 'N/A') . ' | active=' . $ug->is_active . ' | default=' . $ug->is_default . "\n";
    echo "   credentials: " . json_encode($ug->credentials) . "\n";
}
