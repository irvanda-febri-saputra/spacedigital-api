<?php
require 'vendor/autoload.php';

use Illuminate\Support\Facades\Http;

// Bootstrap Laravel for Http facade
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Testing Atlantic API direct call...\n";

try {
    $response = Http::asForm()
        ->timeout(15)
        ->post('https://atlantich2h.com/deposit/create', [
            'api_key' => 'test',
            'reff_id' => 'TEST123',
            'nominal' => 1000,
            'type' => 'ewallet',
            'metode' => 'qris'
        ]);

    echo "Status: " . $response->status() . "\n";
    echo "Body length: " . strlen($response->body()) . "\n";

    $body = $response->body();

    // Check if it's HTML (Cloudflare challenge)
    if (str_contains($body, '<!DOCTYPE html>') || str_contains($body, '<html')) {
        echo "DETECTED: Cloudflare challenge (HTML response)\n";
        echo "First 300 chars:\n";
        echo substr($body, 0, 300) . "\n";
    } else {
        echo "Response (first 500 chars):\n";
        echo substr($body, 0, 500) . "\n";

        // Try parse JSON
        $json = json_decode($body, true);
        if ($json) {
            echo "\nParsed JSON:\n";
            print_r($json);
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
