<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$user = App\Models\User::find(4);

echo "User: " . $user->name . "\n";
echo "DB api_token: " . $user->api_token . "\n";
echo "Expected hash: " . hash('sha256', 'dbce57237b9652472d5786feca0631627830ccf83dacc426598f4831475b6d81') . "\n";
echo "Match: " . ($user->api_token === hash('sha256', 'dbce57237b9652472d5786feca0631627830ccf83dacc426598f4831475b6d81') ? 'YES' : 'NO') . "\n";
