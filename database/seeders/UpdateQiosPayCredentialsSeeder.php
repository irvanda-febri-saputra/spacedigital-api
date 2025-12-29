<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\UserGateway;

class UpdateQiosPayCredentialsSeeder extends Seeder
{
    public function run(): void
    {
        // Get QR String from bot's .env
        $qrString = '00020101021126670016COM.NOBUBANK.WWW01189360050300000907180214515407176589440303UMI51440014ID.CO.QRIS.WWW0215ID20254555471610303UMI5204541153033605802ID5921Toko Kelontong Faisal6006BEKASI61051711162070703A016304992B';

        $userGateway = UserGateway::find(1);
        
        if ($userGateway) {
            $credentials = $userGateway->credentials ?? [];
            $credentials['qr_string'] = $qrString;
            $userGateway->credentials = $credentials;
            $userGateway->save();
            
            $this->command->info('Updated UserGateway credentials with qr_string!');
        } else {
            $this->command->error('UserGateway not found!');
        }
    }
}
