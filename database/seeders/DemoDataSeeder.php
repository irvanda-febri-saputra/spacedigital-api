<?php

namespace Database\Seeders;

use App\Models\Bot;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'admin@spacedigital.com')->first();
        
        if (!$user) {
            return;
        }

        // Create demo bot if not exists
        $bot = Bot::firstOrCreate(
            ['name' => 'Demo Store Bot', 'user_id' => $user->id],
            [
                'bot_username' => 'demostore_bot',
                'bot_token' => '123456789:ABCdefGHIjklMNOpqrSTUvwxYZ',
                'payment_gateway' => 'qiospay',
                'pg_merchant_code' => 'QP039185',
                'pg_api_key' => '7a7f56f92ba61155fcc27a98e1f9e2346654bb32615ec4cd1a352d31a0245307',
                'status' => 'active',
            ]
        );

        // Update existing bot if api key is missing
        if (!$bot->pg_api_key) {
            $bot->update([
                'pg_api_key' => '7a7f56f92ba61155fcc27a98e1f9e2346654bb32615ec4cd1a352d31a0245307',
                'pg_merchant_code' => 'QP039185',
            ]);
        }

        // Create demo transactions
        $products = [
            ['name' => 'Netflix Premium', 'price' => 55000],
            ['name' => 'Spotify Family', 'price' => 35000],
            ['name' => 'YouTube Premium', 'price' => 45000],
            ['name' => 'Disney+ Hotstar', 'price' => 40000],
            ['name' => 'HBO Max', 'price' => 50000],
            ['name' => 'Apple Music', 'price' => 30000],
        ];

        $statuses = ['success', 'success', 'success', 'pending', 'expired'];
        $usernames = ['john_doe', 'jane_smith', 'buyer123', 'customer456', 'newuser'];

        for ($i = 0; $i < 25; $i++) {
            $product = $products[array_rand($products)];
            $status = $statuses[array_rand($statuses)];
            $qty = rand(1, 3);

            Transaction::create([
                'bot_id' => $bot->id,
                'order_id' => 'ORD-' . str_pad($i + 1, 6, '0', STR_PAD_LEFT),
                'telegram_user_id' => rand(100000000, 999999999),
                'telegram_username' => $usernames[array_rand($usernames)],
                'product_name' => $product['name'],
                'variant' => rand(0, 1) ? '1 Month' : '3 Months',
                'quantity' => $qty,
                'price' => $product['price'],
                'total_price' => $product['price'] * $qty,
                'payment_gateway' => 'qiospay',
                'payment_ref' => $status === 'success' ? 'QIOS' . rand(10000, 99999) : null,
                'status' => $status,
                'paid_at' => $status === 'success' ? now()->subHours(rand(1, 72)) : null,
                'created_at' => now()->subHours(rand(1, 168)),
            ]);
        }
    }
}
