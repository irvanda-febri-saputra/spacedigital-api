<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\UserGateway;
use App\Models\PaymentGateway;
use Illuminate\Support\Facades\DB;

class UpdateAtlanticApiKeySeeder extends Seeder
{
    public function run(): void
    {
        // Find Atlantic gateway
        $atlanticGateway = PaymentGateway::where('code', 'atlantic')->first();
        
        if (!$atlanticGateway) {
            $this->command->error('Atlantic gateway not found in payment_gateways table');
            return;
        }

        // Delete old entries and recreate with new API key
        DB::table('user_gateways')->where('gateway_id', $atlanticGateway->id)->delete();
        $this->command->info("Deleted old Atlantic entries");

        // Create new entry with CORRECT API key via Eloquent
        $userGateway = new UserGateway();
        $userGateway->user_id = 1; // Admin user
        $userGateway->gateway_id = $atlanticGateway->id;
        $userGateway->label = 'Atlantic';
        $userGateway->is_active = true;
        $userGateway->credentials = [
            'api_key' => 'KqW6oz3fcqXFbly7mEijGkybykgo4l0rDXbak4nJxkfwfxuMj0u0gxuzyc9IWPxzgyulASlFcEENbuI4crwXNBtkD9k51nJErAlV',
        ];
        $userGateway->save();

        $this->command->info("Created Atlantic gateway with WORKING API key, ID: {$userGateway->id}");
    }
}
