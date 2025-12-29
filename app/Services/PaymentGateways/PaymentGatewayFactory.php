<?php

namespace App\Services\PaymentGateways;

use App\Models\UserGateway;
use InvalidArgumentException;

/**
 * Factory to create payment gateway instances
 */
class PaymentGatewayFactory
{
    /**
     * Create a gateway instance from UserGateway model
     */
    public static function fromUserGateway(UserGateway $userGateway): PaymentGatewayInterface
    {
        $gatewayCode = $userGateway->gateway->code;
        $credentials = $userGateway->credentials;
        
        // Ensure credentials is an array, not null
        if (!is_array($credentials)) {
            $credentials = [];
        }

        return self::create($gatewayCode, $credentials);
    }

    /**
     * Create a gateway instance by code
     */
    public static function create(string $code, array $credentials): PaymentGatewayInterface
    {
        return match ($code) {
            'qiospay' => new QiosPayGateway($credentials),
            'pakasir' => new PakasirGateway($credentials),
            'atlantic' => new AtlanticGateway($credentials),
            'atlantic_fast' => new AtlanticGateway(array_merge($credentials, ['metode' => 'QRISFAST', 'gateway_name' => 'Atlantic QRIS Fast'])),
            'orderkuota' => new OrderKuotaGateway($credentials),
            default => throw new InvalidArgumentException("Unknown payment gateway: {$code}"),
        };
    }

    /**
     * Get list of available gateway codes
     */
    public static function getAvailableCodes(): array
    {
        return ['qiospay', 'pakasir', 'atlantic', 'atlantic_fast', 'orderkuota'];
    }
}

