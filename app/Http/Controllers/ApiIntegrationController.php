<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class ApiIntegrationController extends Controller
{
    /**
     * Show API Integration page
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        return Inertia::render('ApiIntegration/Index', [
            'apiKey' => $user->api_key,
            'userName' => $user->name,
            'baseUrl' => config('app.url') . '/api/public',
            'endpoints' => [
                [
                    'method' => 'POST',
                    'path' => '/payments/create',
                    'description' => 'Create a new payment transaction',
                    'params' => [
                        ['name' => 'amount', 'type' => 'integer', 'required' => true, 'description' => 'Payment amount in IDR (min: 100)'],
                        ['name' => 'gateway', 'type' => 'string', 'required' => true, 'description' => 'Gateway code (e.g., atlantic, qiospay, pakasir)'],
                        ['name' => 'order_id', 'type' => 'string', 'required' => false, 'description' => 'Custom order ID (auto-generated if not provided)'],
                        ['name' => 'customer_name', 'type' => 'string', 'required' => false, 'description' => 'Customer name (optional)'],
                    ],
                    'response' => [
                        'success' => true,
                        'data' => [
                            'transaction_id' => 'TRX-XXXXX',
                            'qr_string' => '00020101021226...',
                            'qr_image' => 'https://...',
                            'amount' => 10000,
                            'expires_at' => '2025-12-05T12:00:00Z',
                        ],
                    ],
                ],
                [
                    'method' => 'GET',
                    'path' => '/payments/{transaction_id}/status',
                    'description' => 'Check payment status',
                    'params' => [],
                    'response' => [
                        'success' => true,
                        'data' => [
                            'transaction_id' => 'TRX-XXXXX',
                            'status' => 'success', // pending, success, expired, failed
                            'amount' => 10000,
                            'paid_at' => '2025-12-05T11:55:00Z',
                        ],
                    ],
                ],
                [
                    'method' => 'GET',
                    'path' => '/payments/history',
                    'description' => 'Get payment history',
                    'params' => [
                        ['name' => 'page', 'type' => 'integer', 'required' => false, 'description' => 'Page number (default: 1)'],
                        ['name' => 'status', 'type' => 'string', 'required' => false, 'description' => 'Filter by status (pending, success, expired)'],
                    ],
                    'response' => [
                        'success' => true,
                        'data' => [
                            'transactions' => '[]',
                            'total' => 100,
                            'page' => 1,
                            'per_page' => 20,
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * Regenerate API Key
     */
    public function regenerateKey(Request $request)
    {
        $user = $request->user();
        $newKey = $user->regenerateApiKey();
        
        return back()->with('success', 'API Key regenerated successfully!');
    }
}

