<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\BotController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\PaymentGatewayController;
use App\Http\Controllers\ApiIntegrationController;
use App\Http\Controllers\CreateTransactionController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Public routes
Route::get('/', function () {
    return redirect('/login');
});

// Auth routes with throttle protection
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])
        ->middleware('throttle:5,1');
    
    Route::get('/register', [RegisterController::class, 'show'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])
        ->middleware('throttle:3,1');
    
    // Forgot Password routes (3-step flow)
    Route::get('/forgot-password', [\App\Http\Controllers\Auth\ForgotPasswordController::class, 'show'])
        ->name('password.request');
    Route::post('/forgot-password', [\App\Http\Controllers\Auth\ForgotPasswordController::class, 'sendOtp'])
        ->middleware('throttle:3,1')
        ->name('password.email');
    
    // Step 2: Verify OTP
    Route::get('/forgot-password/verify', [\App\Http\Controllers\Auth\ForgotPasswordController::class, 'showVerifyForm'])
        ->name('password.verify.form');
    Route::post('/forgot-password/verify', [\App\Http\Controllers\Auth\ForgotPasswordController::class, 'verifyOtp'])
        ->name('password.verify');
    
    // Step 3: New password
    Route::get('/forgot-password/new-password', [\App\Http\Controllers\Auth\ForgotPasswordController::class, 'showNewPasswordForm'])
        ->name('password.new.form');
    Route::post('/forgot-password/set-password', [\App\Http\Controllers\Auth\ForgotPasswordController::class, 'setNewPassword'])
        ->name('password.update');
    
    // Resend OTP
    Route::get('/forgot-password/resend', [\App\Http\Controllers\Auth\ForgotPasswordController::class, 'resendOtp'])
        ->middleware('throttle:3,1')
        ->name('password.resend');
});

// Email verification routes (OTP based)
use App\Http\Controllers\Auth\EmailVerificationController;

Route::get('/email/verify', [EmailVerificationController::class, 'show'])->name('verification.notice');
Route::post('/email/verify', [EmailVerificationController::class, 'verify'])->name('verification.verify');
Route::post('/email/verification-notification', [EmailVerificationController::class, 'sendCode'])
    ->middleware('throttle:3,1')
    ->name('verification.send');

// Protected routes
Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    
    // Bot Management
    Route::resource('bots', BotController::class);
    
    // Transaction History
    Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions.index');
    Route::get('/transactions/export', [TransactionController::class, 'export'])->name('transactions.export');
    Route::get('/transactions/{transaction}', [TransactionController::class, 'show'])->name('transactions.show');
    
    // Payment Gateway Management
    Route::get('/payment-gateways', [PaymentGatewayController::class, 'index'])->name('payment-gateways.index');
    Route::get('/payment-gateways/{gateway}/configure', [PaymentGatewayController::class, 'configure'])->name('payment-gateways.configure');
    Route::post('/payment-gateways', [PaymentGatewayController::class, 'store'])->name('payment-gateways.store');
    Route::post('/payment-gateways/assign', [PaymentGatewayController::class, 'assignToBot'])->name('payment-gateways.assign');
    Route::delete('/payment-gateways/{gateway}', [PaymentGatewayController::class, 'destroy'])->name('payment-gateways.destroy');
    Route::post('/bots/{bot}/remove-gateway', [PaymentGatewayController::class, 'removeFromBot'])->name('bots.remove-gateway');
    
    // API Integration
    Route::get('/api-integration', [ApiIntegrationController::class, 'index'])->name('api-integration');
    Route::post('/api-integration/regenerate-key', [ApiIntegrationController::class, 'regenerateKey'])->name('api-integration.regenerate');
    
    // Create Transaction (Manual Testing)
    Route::get('/create-transaction', [CreateTransactionController::class, 'index'])->name('create-transaction');
    Route::post('/create-transaction', [CreateTransactionController::class, 'store'])->name('create-transaction.store');
    Route::get('/create-transaction/{orderId}/status', [CreateTransactionController::class, 'checkStatus'])->name('create-transaction.status');
    
    // Profile Settings
    Route::get('/profile', [ProfileController::class, 'index'])->name('profile');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::put('/profile/webhook', [ProfileController::class, 'updateWebhook'])->name('profile.webhook');
    
    // Settings (API Key, Change Password, Account Info)
    Route::get('/settings', [ProfileController::class, 'settings'])->name('settings');
    Route::post('/settings/regenerate-api-key', [ProfileController::class, 'regenerateApiKey'])->name('settings.regenerate-api-key');
    
    // Notifications
    Route::get('/notifications', [\App\Http\Controllers\NotificationController::class, 'showPage'])->name('notifications.index');
    Route::get('/notifications/api', [\App\Http\Controllers\NotificationController::class, 'index'])->name('notifications.api');
    Route::post('/notifications/{id}/read', [\App\Http\Controllers\NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [\App\Http\Controllers\NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
    Route::delete('/notifications/{id}', [\App\Http\Controllers\NotificationController::class, 'destroy'])->name('notifications.destroy');
    
    // Atlantic Withdraw (Disbursement)
    Route::get('/atlantic/withdraw', [\App\Http\Controllers\AtlanticWithdrawController::class, 'index'])->name('atlantic.withdraw');
    Route::get('/atlantic/withdraw/balance', [\App\Http\Controllers\AtlanticWithdrawController::class, 'getBalance'])->name('atlantic.withdraw.balance');
    Route::get('/atlantic/withdraw/banks', [\App\Http\Controllers\AtlanticWithdrawController::class, 'getBanks'])->name('atlantic.withdraw.banks');
    Route::post('/atlantic/withdraw/verify', [\App\Http\Controllers\AtlanticWithdrawController::class, 'verifyAccount'])->name('atlantic.withdraw.verify');
    Route::post('/atlantic/withdraw', [\App\Http\Controllers\AtlanticWithdrawController::class, 'submit'])->name('atlantic.withdraw.submit');
    
    // Order Kuota Tools
    Route::get('/orderkuota-tools', [\App\Http\Controllers\OrderKuotaToolsController::class, 'index'])->name('orderkuota-tools');
    Route::post('/orderkuota-tools/request-otp', [\App\Http\Controllers\OrderKuotaToolsController::class, 'requestOtp'])->name('orderkuota-tools.request-otp');
    Route::post('/orderkuota-tools/verify-otp', [\App\Http\Controllers\OrderKuotaToolsController::class, 'verifyOtp'])->name('orderkuota-tools.verify-otp');
    Route::get('/orderkuota-tools/check-token', [\App\Http\Controllers\OrderKuotaToolsController::class, 'checkToken'])->name('orderkuota-tools.check-token');
    Route::get('/orderkuota-tools/mutations', [\App\Http\Controllers\OrderKuotaToolsController::class, 'getMutations'])->name('orderkuota-tools.mutations');
    Route::post('/orderkuota-tools/generate-qris', [\App\Http\Controllers\OrderKuotaToolsController::class, 'generateQris'])->name('orderkuota-tools.generate-qris');
    Route::post('/orderkuota-tools/save-qris-string', [\App\Http\Controllers\OrderKuotaToolsController::class, 'saveQrisString'])->name('orderkuota-tools.save-qris-string');
    
    // Admin Routes (Super Admin only)
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users/{user}/approve', [UserController::class, 'approve'])->name('users.approve');
        Route::post('/users/{user}/suspend', [UserController::class, 'suspend'])->name('users.suspend');
        Route::put('/users/{user}/role', [UserController::class, 'updateRole'])->name('users.role');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
        
        // Admin Bots
        Route::get('/bots', [\App\Http\Controllers\Admin\BotController::class, 'index'])->name('bots.index');
        Route::put('/bots/{bot}/status', [\App\Http\Controllers\Admin\BotController::class, 'updateStatus'])->name('bots.status');
        Route::delete('/bots/{bot}', [\App\Http\Controllers\Admin\BotController::class, 'destroy'])->name('bots.destroy');
    });
});

