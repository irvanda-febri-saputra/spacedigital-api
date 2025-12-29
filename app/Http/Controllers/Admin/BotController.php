<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bot;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class BotController extends Controller
{
    /**
     * Display listing of all bots (admin view)
     */
    public function index(Request $request)
    {
        // Check if user is super_admin
        if (auth()->user()->role !== 'super_admin') {
            abort(403, 'Unauthorized');
        }

        $query = Bot::with('user')
            ->withCount('transactions')
            ->withSum(['transactions' => function($q) {
                $q->where('status', 'success');
            }], 'total_price');

        // Search filter
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('bot_username', 'like', "%{$search}%");
            });
        }

        // Status filter
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Get paginated bots
        $bots = $query->orderBy('created_at', 'desc')
                     ->paginate(15)
                     ->withQueryString();

        // Transform dates and add total revenue
        $bots->getCollection()->transform(function ($bot) {
            $bot->created_at = Carbon::parse($bot->created_at)->format('M d, Y');
            $bot->total_revenue = (int) ($bot->transactions_sum_total_price ?? 0);
            return $bot;
        });

        // Stats
        $stats = [
            'total' => Bot::count(),
            'active' => Bot::where('status', 'active')->count(),
            'inactive' => Bot::where('status', 'inactive')->count(),
            'transactions' => \App\Models\Transaction::count(),
        ];

        return Inertia::render('Admin/Bots/Index', [
            'bots' => $bots,
            'stats' => $stats,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    /**
     * Update bot status
     */
    public function updateStatus(Request $request, Bot $bot)
    {
        if (auth()->user()->role !== 'super_admin') {
            abort(403, 'Unauthorized');
        }

        $validated = $request->validate([
            'status' => 'required|in:active,inactive',
        ]);

        $bot->update(['status' => $validated['status']]);

        return back()->with('success', 'Bot status updated successfully');
    }

    /**
     * Delete a bot
     */
    public function destroy(Bot $bot)
    {
        if (auth()->user()->role !== 'super_admin') {
            abort(403, 'Unauthorized');
        }

        $bot->delete();

        return back()->with('success', 'Bot deleted successfully');
    }

    // ============================================================
    // API Methods for React SPA
    // ============================================================

    public function apiIndex(Request $request)
    {
        if ($request->user()->role !== 'super_admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $query = Bot::with('user')
            ->withCount('transactions')
            ->withSum(['transactions' => function($q) {
                $q->where('status', 'success');
            }], 'total_price');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('bot_username', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $bots = $query->orderBy('created_at', 'desc')
                     ->paginate(15)
                     ->withQueryString();

        $bots->getCollection()->transform(function ($bot) {
            return [
                'id' => $bot->id,
                'name' => $bot->name,
                'bot_username' => $bot->bot_username,
                'status' => $bot->status,
                'user' => $bot->user ? [
                    'id' => $bot->user->id,
                    'name' => $bot->user->name,
                    'email' => $bot->user->email,
                ] : null,
                'transactions_count' => $bot->transactions_count ?? 0,
                'total_revenue' => (int) ($bot->transactions_sum_total_price ?? 0),
                'created_at' => Carbon::parse($bot->created_at)->format('d M Y'),
            ];
        });

        $stats = [
            'total' => Bot::count(),
            'active' => Bot::where('status', 'active')->count(),
            'inactive' => Bot::where('status', 'inactive')->count(),
            'transactions' => \App\Models\Transaction::count(),
        ];

        return response()->json([
            'bots' => $bots,
            'stats' => $stats,
        ]);
    }

    public function apiUpdateStatus(Request $request, Bot $bot)
    {
        if ($request->user()->role !== 'super_admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'status' => 'required|in:active,inactive',
        ]);

        $bot->update(['status' => $validated['status']]);

        return response()->json(['success' => true, 'message' => 'Bot status updated successfully']);
    }

    public function apiDestroy(Request $request, Bot $bot)
    {
        if ($request->user()->role !== 'super_admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $bot->delete();

        return response()->json(['success' => true, 'message' => 'Bot deleted successfully']);
    }
}
