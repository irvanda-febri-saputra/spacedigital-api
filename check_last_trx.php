<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$transactions = App\Models\Transaction::latest()->take(5)->get();

foreach ($transactions as $trx) {
    echo "Order ID: " . $trx->order_id . PHP_EOL;
    echo "Gateway: " . $trx->payment_gateway . PHP_EOL;
    echo "Amount: " . $trx->total_price . PHP_EOL;
    echo "Status: " . $trx->status . PHP_EOL;
    echo "Created: " . $trx->created_at->format('Y-m-d H:i:s') . PHP_EOL;
    echo "OrderKuota TRX ID: " . ($trx->orderkuota_trx_id ?? 'NULL') . PHP_EOL;
    echo "---" . PHP_EOL;
}
