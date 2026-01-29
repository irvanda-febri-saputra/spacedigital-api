<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\Bot;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // Semua user hanya lihat transaksi dari bot miliknya
        $botIds = $user->bots()->pluck('id');

        // Build query with filters
        $query = Transaction::whereIn('bot_id', $botIds)
            ->with('bot:id,name');

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by bot
        if ($request->filled('bot_id')) {
            $query->where('bot_id', $request->bot_id);
        }

        // Filter by date range
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        // Search by order_id or product
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('order_id', 'like', "%{$search}%")
                  ->orWhere('product_name', 'like', "%{$search}%")
                  ->orWhere('telegram_username', 'like', "%{$search}%");
            });
        }

        $transactions = $query->latest()
            ->paginate(20)
            ->through(fn ($tx) => [
                'id' => $tx->id,
                'order_id' => $tx->order_id,
                'telegram_username' => $tx->telegram_username,
                'product_name' => $tx->product_name,
                'variant' => $tx->variant,
                'quantity' => $tx->quantity,
                'total_price' => $tx->total_price,
                'payment_gateway' => $tx->payment_gateway,
                'status' => $tx->status,
                'bot' => $tx->bot ? ['id' => $tx->bot->id, 'name' => $tx->bot->name] : null,
                'created_at' => $tx->created_at->format('d M Y H:i'),
                'paid_at' => $tx->paid_at?->format('d M Y H:i'),
            ]);

        // Stats
        $stats = [
            'total' => Transaction::whereIn('bot_id', $botIds)->count(),
            'success' => Transaction::whereIn('bot_id', $botIds)->where('status', 'success')->count(),
            'pending' => Transaction::whereIn('bot_id', $botIds)->where('status', 'pending')->count(),
            'revenue' => (int) Transaction::whereIn('bot_id', $botIds)->where('status', 'success')->sum('total_price'),
        ];

        // User's bots for filter dropdown
        $bots = Bot::whereIn('id', $botIds)->get(['id', 'name']);

        return Inertia::render('Transactions/Index', [
            'transactions' => $transactions,
            'stats' => $stats,
            'bots' => $bots,
            'filters' => $request->only(['status', 'bot_id', 'from', 'to', 'search']),
        ]);
    }

    public function show(Transaction $transaction)
    {
        $user = request()->user();

        // Authorization check - semua user hanya bisa lihat transaksi dari bot miliknya
        if ($transaction->bot->user_id !== $user->id) {
            abort(403);
        }

        return Inertia::render('Transactions/Show', [
            'transaction' => [
                'id' => $transaction->id,
                'order_id' => $transaction->order_id,
                'telegram_user_id' => $transaction->telegram_user_id,
                'telegram_username' => $transaction->telegram_username,
                'product_name' => $transaction->product_name,
                'variant' => $transaction->variant,
                'quantity' => $transaction->quantity,
                'price' => $transaction->price,
                'total_price' => $transaction->total_price,
                'payment_gateway' => $transaction->payment_gateway,
                'payment_ref' => $transaction->payment_ref,
                'status' => $transaction->status,
                'bot' => $transaction->bot ? ['id' => $transaction->bot->id, 'name' => $transaction->bot->name] : null,
                'created_at' => $transaction->created_at->format('d M Y H:i:s'),
                'paid_at' => $transaction->paid_at?->format('d M Y H:i:s'),
                'expired_at' => $transaction->expired_at?->format('d M Y H:i:s'),
            ],
        ]);
    }

    public function export(Request $request)
    {
        $user = $request->user();

        // Semua user hanya export transaksi dari bot miliknya
        $botIds = $user->bots()->pluck('id');

        // Build query with filters (same as index)
        $query = Transaction::whereIn('bot_id', $botIds)
            ->with('bot:id,name');

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by bot
        if ($request->filled('bot_id')) {
            $query->where('bot_id', $request->bot_id);
        }

        // Filter by date range
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('order_id', 'like', "%{$search}%")
                  ->orWhere('product_name', 'like', "%{$search}%")
                  ->orWhere('telegram_username', 'like', "%{$search}%");
            });
        }

        $filename = 'transactions-' . date('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');

            // Add BOM for Excel compatibility
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            // Header
            fputcsv($handle, [
                'Order ID',
                'Date',
                'Bot Name',
                'Customer Username',
                'Customer ID',
                'Product',
                'Variant',
                'Quantity',
                'Price',
                'Total',
                'Status',
                'Payment Ref'
            ]);

            // Chunking to avoid memory issues
            $query->latest()->chunk(100, function ($transactions) use ($handle) {
                foreach ($transactions as $tx) {
                    fputcsv($handle, [
                        $tx->order_id,
                        $tx->created_at->format('Y-m-d H:i:s'),
                        $tx->bot->name ?? 'Unknown',
                        $tx->telegram_username,
                        $tx->telegram_user_id,
                        $tx->product_name,
                        $tx->variant,
                        $tx->quantity,
                        $tx->price,
                        $tx->total_price,
                        ucfirst($tx->status),
                        $tx->payment_ref
                    ]);
                }
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    // ============================================================
    // API METHODS (for SPA Dashboard)
    // ============================================================

    /**
     * API: Get transactions with pagination
     */
    public function apiIndex(Request $request)
    {
        $user = $request->user();

        // Semua user hanya lihat transaksi dari bot miliknya
        $botIds = $user->bots()->pluck('id');

        $query = Transaction::whereIn('bot_id', $botIds)->with('bot:id,name');

        // Filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('bot_id')) {
            $query->where('bot_id', $request->bot_id);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('order_id', 'like', "%{$search}%")
                  ->orWhere('product_name', 'like', "%{$search}%")
                  ->orWhere('telegram_username', 'like', "%{$search}%");
            });
        }

        $transactions = $query->latest()
            ->paginate($request->get('limit', 20))
            ->through(fn ($tx) => [
                'id' => $tx->id,
                'order_id' => $tx->order_id,
                'invoice_id' => $tx->order_id, // Alias for compatibility
                'product_name' => $tx->product_name,
                'amount' => $tx->total_price,
                'total_price' => $tx->total_price,
                'status' => $tx->status,
                'payment_gateway' => $tx->payment_gateway,
                'bot' => $tx->bot ? ['id' => $tx->bot->id, 'name' => $tx->bot->name] : null,
                'created_at' => $tx->created_at->toIso8601String(),
                'paid_at' => $tx->paid_at?->toIso8601String(),
            ]);

        return response()->json($transactions);
    }

    /**
     * API: Get transaction stats
     */
    public function apiStats(Request $request)
    {
        $user = $request->user();

        // Semua user hanya lihat statistik dari bot miliknya
        $botIds = $user->bots()->pluck('id');

        $stats = [
            'total_transactions' => Transaction::whereIn('bot_id', $botIds)->count(),
            'total_revenue' => (int) Transaction::whereIn('bot_id', $botIds)
                ->where('status', 'success')
                ->sum('total_price'),
            'pending_count' => Transaction::whereIn('bot_id', $botIds)
                ->where('status', 'pending')
                ->count(),
            'success_count' => Transaction::whereIn('bot_id', $botIds)
                ->where('status', 'success')
                ->count(),
        ];

        return response()->json($stats);
    }

    /**
     * API: Create transaction manually
     */
    public function apiStore(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'bot_id' => 'required|exists:bots,id',
            'product_id' => 'nullable|exists:products,id',
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'nullable|string|max:20',
            'customer_telegram_id' => 'nullable|string|max:50',
            'amount' => 'required|numeric|min:0',
            'quantity' => 'required|integer|min:1',
            'total_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'status' => 'required|in:pending,processing,completed,cancelled'
        ]);

        // Check if user owns this bot
        $bot = Bot::find($validated['bot_id']);
        if ($bot->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Generate order ID
        $orderId = 'ORD-' . strtoupper(uniqid());

        // Get product name if product_id provided
        $productName = 'Manual Order';
        if ($validated['product_id']) {
            $product = \App\Models\Product::find($validated['product_id']);
            if ($product) {
                $productName = $product->name;
            }
        }

        $transaction = Transaction::create([
            'bot_id' => $validated['bot_id'],
            'order_id' => $orderId,
            'invoice_id' => 'INV-' . strtoupper(uniqid()),
            'telegram_user_id' => $validated['customer_telegram_id'] ?? null,
            'telegram_username' => $validated['customer_name'],
            'product_name' => $productName,
            'price' => $validated['amount'],
            'quantity' => $validated['quantity'],
            'total_price' => $validated['total_amount'] ?? ($validated['amount'] * $validated['quantity']),
            'status' => $validated['status'],
            'notes' => $validated['notes'] ?? null,
            'payment_gateway' => 'manual',
            'created_at' => now(),
        ]);

        return response()->json([
            'message' => 'Transaction created successfully',
            'data' => $transaction
        ], 201);
    }
}
