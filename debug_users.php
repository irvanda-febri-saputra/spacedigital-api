<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Transaction;

echo "=== Users ===\n";
foreach (User::all() as $u) {
    echo "ID: {$u->id} | Email: {$u->email} | Name: {$u->name}\n";
}

echo "\n=== Recent Transactions ===\n";
$txs = Transaction::latest()->limit(5)->get();
foreach ($txs as $tx) {
    $bot = $tx->bot;
    echo "Order: {$tx->order_id} | Status: {$tx->status} | Gateway: {$tx->payment_gateway} | Bot User ID: " . ($bot ? $bot->user_id : 'N/A') . "\n";
}
