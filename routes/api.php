<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\BotApiController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| These routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group.
|
*/

// Bot API Routes (authenticated by X-API-Key header, rate limited)
Route::prefix('bot')
    ->middleware(['api.rate_limit:60,1', 'api.key']) // Validate API Key + Rate limit
    ->group(function () {
        // Get bot settings
        Route::get('/settings', [BotApiController::class, 'getSettings']);

        // Transaction management
        Route::post('/transactions', [BotApiController::class, 'createTransaction']);
        Route::get('/transactions/{orderId}', [BotApiController::class, 'getTransaction']);
        Route::post('/transactions/{orderId}/status', [BotApiController::class, 'updateTransactionStatus']);
        Route::post('/transactions/{orderId}/check', [BotApiController::class, 'checkTransactionStatus']);

        // Centralized Payment Gateway
        Route::post('/payments/create', [BotApiController::class, 'createPayment']);
        Route::get('/payments/{paymentId}/status', [BotApiController::class, 'checkPaymentStatus']);

        // Product Sync (Bot pushes products to Dashboard)
        Route::post('/products/sync', [BotApiController::class, 'syncProducts']);
        Route::post('/products/sync-single', [BotApiController::class, 'syncProductSingle']);
        Route::post('/products/update-stock', [BotApiController::class, 'updateProductStock']);

        // Stock sold notification (Bot notifies when stock is sold)
        Route::post('/stocks/sold', [BotApiController::class, 'markStocksSold']);
    });

// Payment Webhooks (no auth, no rate limit - called by payment gateways)
Route::post('/payments/webhook/{gateway}', [BotApiController::class, 'handleWebhook']);

// ============================================================
// PUBLIC API ROUTES (Authenticated by X-API-Key header)
// ============================================================
use App\Http\Controllers\Api\PublicApiController;

Route::prefix('public')
    ->middleware(['api.key'])
    ->group(function () {
        // Payment endpoints
        Route::post('/payments/create', [PublicApiController::class, 'createPayment']);
        Route::get('/payments/{transactionId}/status', [PublicApiController::class, 'checkStatus']);
        Route::get('/payments/history', [PublicApiController::class, 'getHistory']);

        // Gateway info
        Route::get('/gateways', [PublicApiController::class, 'getGateways']);
    });

// ============================================================
// DASHBOARD API ROUTES (For Frontend SPA)
// ============================================================
use App\Http\Controllers\Api\AuthApiController;

// Auth routes (no auth required)
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthApiController::class, 'login']);
    Route::post('/register', [AuthApiController::class, 'register']);

    // Email verification
    Route::post('/email/verify', [AuthApiController::class, 'verifyEmail']);
    Route::post('/email/resend', [AuthApiController::class, 'resendVerificationEmail']);

    // Forgot password flow
    Route::post('/forgot-password', [AuthApiController::class, 'forgotPassword']);
    Route::post('/forgot-password/verify', [AuthApiController::class, 'verifyResetOtp']);
    Route::post('/forgot-password/resend', [AuthApiController::class, 'resendResetOtp']);
    Route::post('/forgot-password/set-password', [AuthApiController::class, 'setNewPassword']);
});

// Protected dashboard routes (require Bearer token)
Route::middleware('auth.api.token')->prefix('dashboard')->group(function () {
    // Auth
    Route::get('/me', [AuthApiController::class, 'me']);
    Route::post('/logout', [AuthApiController::class, 'logout']);

    // Profile
    Route::put('/profile', [AuthApiController::class, 'updateProfile']);
    Route::put('/profile/avatar', [AuthApiController::class, 'updateAvatar']);
    Route::put('/password', [AuthApiController::class, 'updatePassword']);

    // Settings
    Route::post('/settings/regenerate-api-key', [AuthApiController::class, 'regenerateApiKey']);

    // Notifications
    Route::get('/notifications', [\App\Http\Controllers\NotificationController::class, 'apiIndex']);
    Route::post('/notifications/{notification}/read', [\App\Http\Controllers\NotificationController::class, 'apiMarkAsRead']);
    Route::post('/notifications/read-all', [\App\Http\Controllers\NotificationController::class, 'apiMarkAllAsRead']);
    Route::delete('/notifications/{notification}', [\App\Http\Controllers\NotificationController::class, 'apiDestroy']);

    // Atlantic Withdraw
    Route::get('/atlantic/balance', [\App\Http\Controllers\AtlanticController::class, 'apiBalance']);
    Route::get('/atlantic/banks', [\App\Http\Controllers\AtlanticController::class, 'apiBanks']);
    Route::post('/atlantic/verify', [\App\Http\Controllers\AtlanticController::class, 'apiVerify']);
    Route::post('/atlantic/withdraw', [\App\Http\Controllers\AtlanticController::class, 'apiWithdraw']);

    // Bots
    Route::get('/bots', [\App\Http\Controllers\BotController::class, 'apiIndex']);
    Route::post('/bots', [\App\Http\Controllers\BotController::class, 'apiStore']);
    Route::get('/bots/{bot}', [\App\Http\Controllers\BotController::class, 'apiShow']);
    Route::put('/bots/{bot}', [\App\Http\Controllers\BotController::class, 'apiUpdate']);
    Route::delete('/bots/{bot}', [\App\Http\Controllers\BotController::class, 'apiDestroy']);

    // Transactions
    Route::get('/transactions', [\App\Http\Controllers\TransactionController::class, 'apiIndex']);
    Route::get('/transactions/stats', [\App\Http\Controllers\TransactionController::class, 'apiStats']);

    // Payment Gateways
    Route::get('/gateways', [\App\Http\Controllers\PaymentGatewayController::class, 'apiIndex']);
    Route::get('/gateways/user', [\App\Http\Controllers\PaymentGatewayController::class, 'apiUserGateways']);
    Route::post('/gateways/{gateway}/configure', [\App\Http\Controllers\PaymentGatewayController::class, 'apiConfigure']);
    Route::post('/gateways/{gateway}/set-default', [\App\Http\Controllers\PaymentGatewayController::class, 'apiSetDefault']);
    Route::post('/gateways/{gateway}/toggle-active', [\App\Http\Controllers\PaymentGatewayController::class, 'apiToggleActive']);
    Route::post('/gateways/assign-to-bot', [\App\Http\Controllers\PaymentGatewayController::class, 'apiAssignToBot']);
    Route::delete('/gateways/{id}', [\App\Http\Controllers\PaymentGatewayController::class, 'apiDestroy']);

    // Products
    Route::get('/products', [\App\Http\Controllers\ProductController::class, 'apiIndex']);
    Route::get('/products/categories', [\App\Http\Controllers\ProductController::class, 'apiCategories']);
    Route::post('/products', [\App\Http\Controllers\ProductController::class, 'apiStore']);
    Route::get('/products/{product}', [\App\Http\Controllers\ProductController::class, 'apiShow']);
    Route::put('/products/{product}', [\App\Http\Controllers\ProductController::class, 'apiUpdate']);
    Route::post('/products/{product}/stock', [\App\Http\Controllers\ProductController::class, 'addStock']);
    Route::delete('/products/{product}', [\App\Http\Controllers\ProductController::class, 'apiDestroy']);
    Route::post('/products/bulk-update', [\App\Http\Controllers\ProductController::class, 'apiBulkUpdate']);

    // Product Variants
    Route::get('/products/{product}/variants', [\App\Http\Controllers\ProductVariantController::class, 'apiIndex']);
    Route::post('/products/{product}/variants', [\App\Http\Controllers\ProductVariantController::class, 'apiStore']);
    Route::put('/variants/{variant}', [\App\Http\Controllers\ProductVariantController::class, 'apiUpdate']);
    Route::delete('/variants/{variant}', [\App\Http\Controllers\ProductVariantController::class, 'apiDestroy']);

    // Stock Items
    Route::get('/stocks', [\App\Http\Controllers\StockController::class, 'apiIndex']);
    Route::post('/stocks', [\App\Http\Controllers\StockController::class, 'apiStore']);
    Route::put('/stocks/{stock}', [\App\Http\Controllers\StockController::class, 'apiUpdate']);
    Route::delete('/stocks/{stock}', [\App\Http\Controllers\StockController::class, 'apiDestroy']);
    Route::post('/stocks/bulk-import', [\App\Http\Controllers\StockController::class, 'apiBulkImport']);
    Route::get('/stocks/stats', [\App\Http\Controllers\StockController::class, 'apiStats']);
    Route::post('/stocks/hastebin', [\App\Http\Controllers\StockController::class, 'apiGenerateHastebin']);

    // Broadcast
    Route::post('/broadcast', [\App\Http\Controllers\BroadcastController::class, 'send']);

    // Image Upload (Catbox proxy)
    Route::post('/upload-image', [\App\Http\Controllers\ImageUploadController::class, 'uploadToCatbox']);

    // Create Transaction (Test Gateway Credentials)
    Route::post('/test-transaction', [\App\Http\Controllers\CreateTransactionController::class, 'apiStore']);
    Route::get('/test-transaction/{orderId}/status', [\App\Http\Controllers\CreateTransactionController::class, 'apiCheckStatus']);
    Route::post('/test-transaction/{orderId}/check', [\App\Http\Controllers\CreateTransactionController::class, 'apiCheckPayment']);

    // Order Kuota Tools
    Route::get('/orderkuota/status', [\App\Http\Controllers\OrderKuotaToolsController::class, 'apiStatus']);
    Route::post('/orderkuota/request-otp', [\App\Http\Controllers\OrderKuotaToolsController::class, 'apiRequestOtp']);
    Route::post('/orderkuota/verify-otp', [\App\Http\Controllers\OrderKuotaToolsController::class, 'apiVerifyOtp']);
    Route::get('/orderkuota/check-token', [\App\Http\Controllers\OrderKuotaToolsController::class, 'apiCheckToken']);
    Route::get('/orderkuota/mutations', [\App\Http\Controllers\OrderKuotaToolsController::class, 'apiGetMutations']);
    Route::post('/orderkuota/generate-qris', [\App\Http\Controllers\OrderKuotaToolsController::class, 'apiGenerateQris']);
    Route::post('/orderkuota/save-qris', [\App\Http\Controllers\OrderKuotaToolsController::class, 'apiSaveQrisString']);

    // Admin Routes (Super Admin Only)
    Route::prefix('admin')->group(function () {
        // Users Management
        Route::get('/users', [\App\Http\Controllers\Admin\UserController::class, 'apiIndex']);
        Route::post('/users/{user}/approve', [\App\Http\Controllers\Admin\UserController::class, 'apiApprove']);
        Route::post('/users/{user}/suspend', [\App\Http\Controllers\Admin\UserController::class, 'apiSuspend']);
        Route::put('/users/{user}/role', [\App\Http\Controllers\Admin\UserController::class, 'apiUpdateRole']);
        Route::delete('/users/{user}', [\App\Http\Controllers\Admin\UserController::class, 'apiDestroy']);

        // Bots Management
        Route::get('/bots', [\App\Http\Controllers\Admin\BotController::class, 'apiIndex']);
        Route::put('/bots/{bot}/status', [\App\Http\Controllers\Admin\BotController::class, 'apiUpdateStatus']);
        Route::delete('/bots/{bot}', [\App\Http\Controllers\Admin\BotController::class, 'apiDestroy']);
    });
});
