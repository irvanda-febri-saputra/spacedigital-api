<?php

namespace App\Services\PaymentGateways;

/**
 * Interface for all payment gateway implementations
 * Each gateway must implement these methods
 */
interface PaymentGatewayInterface
{
    /**
     * Create a new payment/QRIS
     * 
     * @param array $data Payment data including:
     *   - amount: int (required)
     *   - order_id: string (required)
     *   - customer_name: string (optional)
     *   - callback_url: string (optional)
     * @return array Response with:
     *   - success: bool
     *   - payment_id: string (gateway's payment ID)
     *   - qr_string: string (QRIS string)
     *   - qr_image: string (URL to QR image, if available)
     *   - expires_at: string (ISO date)
     *   - error: string (if failed)
     */
    public function createPayment(array $data): array;

    /**
     * Check payment status
     * 
     * @param string $paymentId Gateway's payment ID
     * @return array Response with:
     *   - success: bool
     *   - status: string (pending, success, expired, failed)
     *   - paid_at: string|null (ISO date if paid)
     *   - amount: int
     *   - error: string (if failed to check)
     */
    public function checkStatus(string $paymentId): array;

    /**
     * Validate webhook signature/payload
     * 
     * @param array $payload Webhook payload from gateway
     * @param string $signature Signature header (if any)
     * @return bool Whether the webhook is valid
     */
    public function validateWebhook(array $payload, ?string $signature = null): bool;

    /**
     * Parse webhook payload into standard format
     * 
     * @param array $payload Raw webhook payload
     * @return array Parsed data with:
     *   - payment_id: string
     *   - status: string (success, failed)
     *   - amount: int
     *   - paid_at: string|null
     */
    public function parseWebhook(array $payload): array;

    /**
     * Get gateway code
     * @return string e.g., 'qiospay', 'pakasir'
     */
    public function getCode(): string;

    /**
     * Get gateway display name
     * @return string e.g., 'QiosPay QRIS'
     */
    public function getName(): string;
}
