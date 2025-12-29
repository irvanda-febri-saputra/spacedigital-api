<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    /**
     * Show dashboard page
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        // Each user sees stats for their own bots - with transaction count
        $bots = $user->bots()->withCount('transactions')->latest()->get();
        $botIds = $bots->pluck('id');
        
        // Calculate stats from real data
        $totalTransactions = \App\Models\Transaction::whereIn('bot_id', $botIds)->count();
        $totalRevenue = (int) \App\Models\Transaction::whereIn('bot_id', $botIds)->where('status', 'success')->sum('total_price');
        $pendingTransactions = \App\Models\Transaction::whereIn('bot_id', $botIds)->where('status', 'pending')->count();
        $activeBots = $bots->where('status', 'active')->count();
        
        $stats = [
            'totalBots' => $bots->count(),
            'totalTransactions' => $totalTransactions,
            'totalRevenue' => $totalRevenue,
            'pendingTransactions' => $pendingTransactions,
            'activeBots' => $activeBots,
        ];

        // Recent transactions
        $recentTransactions = \App\Models\Transaction::whereIn('bot_id', $botIds)
            ->with('bot:id,name')
            ->latest()
            ->take(5)
            ->get()
            ->map(fn($t) => [
                'order_id' => $t->order_id,
                'product' => $t->product_name ?: ($t->variant ?: 'Unknown Product'),
                'amount' => $t->total_price ?? 0,
                'status' => $t->status ?? 'pending',
                'bot_name' => $t->bot->name ?? 'Unknown',
                'date' => $t->created_at->diffForHumans(),
            ]);

        // Add revenue to each bot for card display
        $botsWithRevenue = $bots->map(function($bot) {
            $bot->total_revenue = (int) \App\Models\Transaction::where('bot_id', $bot->id)
                ->where('status', 'success')
                ->sum('total_price');
            return $bot;
        });

        return Inertia::render('Dashboard', [
            'stats' => $stats,
            'recentTransactions' => $recentTransactions,
            'bots' => $botsWithRevenue, // Pass bots with transaction count and revenue
            'userApiKey' => $bots->first() ? $bots->first()->pg_api_key : ('eca2' . substr(md5($user->id . $user->email), 0, 16) . '0102'),
        ]);
    }
}
