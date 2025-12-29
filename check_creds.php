<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$gw = DB::table('user_gateways')->where('id', 3)->first();
echo "Credentials raw:\n";
echo $gw->credentials . "\n";
echo "\nDecoded:\n";
print_r(json_decode($gw->credentials, true));
