<?php

namespace App\Http\Controllers;

use App\Models\Bot;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BotController extends Controller
{
    /**
     * Display a listing of bots
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // Semua user hanya lihat bot miliknya sendiri
        $query = $user->bots()->with(['activeGateway.gateway']);

        $bots = $query->latest()->get()->map(function ($bot) {
            $activeGateway = $bot->activeGateway;
            $gateway = $activeGateway ? $activeGateway->gateway : null;

            return [
                'id' => $bot->id,
                'name' => $bot->name,
                'bot_username' => $bot->bot_username,
                'masked_token' => $bot->masked_token,
                'payment_gateway' => $bot->payment_gateway,
                'active_gateway' => $gateway ? [
                    'name' => $gateway->name,
                    'code' => $gateway->code,
                    'logo' => $gateway->logo,
                ] : null,
                'status' => $bot->status,
                'user' => $bot->user ? [
                    'id' => $bot->user->id,
                    'name' => $bot->user->name,
                ] : null,
                'created_at' => $bot->created_at->format('d M Y'),
                'settings' => $bot->settings,
            ];
        });

        return Inertia::render('Bots/Index', [
            'bots' => $bots,
        ]);
    }

    /**
     * Show the form for creating a new bot
     */
    public function create(Request $request)
    {
        $user = $request->user();

        // Get user's configured and active gateways
        $userGateways = \App\Models\UserGateway::where('user_id', $user->id)
            ->where('is_active', true)
            ->with('gateway')
            ->get()
            ->map(function ($ug) {
                return [
                    'id' => $ug->id,
                    'code' => $ug->gateway->code,
                    'name' => strtoupper($ug->label ?? $ug->gateway->name),
                ];
            });

        return Inertia::render('Bots/Create', [
            'paymentGateways' => $userGateways,
        ]);
    }

    /**
     * Store a newly created bot
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'bot_token' => 'nullable|string',
            'bot_username' => 'nullable|string|max:255',
            'payment_gateway' => 'required|string|max:50',
            'pg_merchant_code' => 'nullable|string|max:255',
            'pg_api_key' => 'nullable|string',
            'pg_qr_string' => 'nullable|string',
        ]);

        $validated['user_id'] = $request->user()->id;
        $validated['status'] = 'active';

        Bot::create($validated);

        return redirect()->route('bots.index')->with('success', 'Bot created successfully!');
    }

    /**
     * Show the form for editing the specified bot
     */
    public function edit(Bot $bot)
    {
        // Check authorization (semua user hanya bisa edit bot miliknya)
        if ($bot->user_id !== request()->user()->id) {
            abort(403);
        }

        // Get user's configured payment gateways with gateway info
        $userGateways = request()->user()->userGateways()->with('gateway')->get()->map(fn($gw) => [
            'id' => $gw->id,
            'gateway_type' => $gw->gateway->code ?? $gw->label ?? 'unknown',
            'merchant_code' => $gw->getCredential('merchant_code') ?? $gw->label ?? 'N/A',
        ]);

        return Inertia::render('Bots/Edit', [
            'bot' => [
                'id' => $bot->id,
                'name' => $bot->name,
                'bot_token' => $bot->bot_token,
                'bot_username' => $bot->bot_username,
                'user_gateway_id' => $bot->active_gateway_id,
                'status' => $bot->status,
            ],
            'userGateways' => $userGateways,
        ]);
    }

    /**
     * Update the specified bot
     */
    public function update(Request $request, Bot $bot)
    {
        // Check authorization (semua user hanya bisa update bot miliknya)
        if ($bot->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'bot_token' => 'nullable|string',
            'bot_username' => 'nullable|string|max:255',
            'user_gateway_id' => 'nullable|exists:user_gateways,id',
            'status' => 'required|in:active,inactive,suspended',
        ]);

        // Map user_gateway_id to active_gateway_id
        $bot->update([
            'name' => $validated['name'],
            'bot_token' => $validated['bot_token'],
            'bot_username' => $validated['bot_username'],
            'active_gateway_id' => $validated['user_gateway_id'] ?? null,
            'status' => $validated['status'],
        ]);

        return redirect()->route('bots.index')->with('success', 'Bot updated successfully!');
    }

    /**
     * Remove the specified bot
     */
    public function destroy(Bot $bot)
    {
        // Check authorization (semua user hanya bisa hapus bot miliknya)
        if ($bot->user_id !== request()->user()->id) {
            abort(403);
        }

        $bot->delete();

        return redirect()->route('bots.index')->with('success', 'Bot deleted successfully!');
    }

    // ============================================================
    // API METHODS (for SPA Dashboard)
    // ============================================================

    /**
     * API: Get list of bots
     */
    public function apiIndex(Request $request)
    {
        $user = $request->user();

        // Semua user hanya lihat bot miliknya sendiri
        $query = $user->bots()->with(['activeGateway.gateway']);

        $bots = $query->withCount('transactions')
            ->withSum('transactions', 'total_price')
            ->latest()
            ->get()
            ->map(function ($bot) {
                $activeGateway = $bot->activeGateway;
                $gateway = $activeGateway ? $activeGateway->gateway : null;

                return [
                    'id' => $bot->id,
                    'name' => $bot->name,
                    'bot_username' => $bot->bot_username,
                    'masked_token' => $bot->masked_token,
                    'active_gateway_id' => $bot->active_gateway_id,
                    'active_gateway' => $gateway ? [
                        'id' => $activeGateway->id,
                        'name' => $gateway->name,
                        'code' => $gateway->code,
                        'logo' => $gateway->logo,
                    ] : null,
                    'status' => $bot->status,
                    'transactions_count' => $bot->transactions_count ?? 0,
                    'total_revenue' => $bot->transactions_sum_total_price ?? 0,
                    'created_at' => $bot->created_at->format('d M Y'),
                ];
            });

        return response()->json($bots);
    }

    /**
     * API: Get single bot
     */
    public function apiShow(Request $request, Bot $bot)
    {
        $user = $request->user();

        // Semua user hanya bisa lihat bot miliknya
        if ($bot->user_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->json([
            'id' => $bot->id,
            'name' => $bot->name,
            'bot_token' => $bot->bot_token,
            'bot_username' => $bot->bot_username,
            'status' => $bot->status,
            'settings' => $bot->settings,
            'user_gateway_id' => $bot->active_gateway_id,
            'active_gateway_id' => $bot->active_gateway_id,
            'created_at' => $bot->created_at?->format('d/m/Y, H.i.s'),
        ]);
    }

    /**
     * API: Create bot
     */
    public function apiStore(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'bot_token' => 'required|string',
            'user_gateway_id' => 'nullable|integer|exists:user_gateways,id',
        ]);

        // Map user_gateway_id to active_gateway_id
        if (isset($validated['user_gateway_id'])) {
            $validated['active_gateway_id'] = $validated['user_gateway_id'];
            unset($validated['user_gateway_id']);
        }

        $bot = $request->user()->bots()->create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Bot created successfully',
            'data' => $bot,
        ], 201);
    }

    /**
     * API: Update bot
     */
    public function apiUpdate(Request $request, Bot $bot)
    {
        $user = $request->user();

        // Semua user hanya bisa update bot miliknya
        if ($bot->user_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'bot_token' => 'sometimes|string',
            'status' => 'sometimes|in:active,inactive',
            'user_gateway_id' => 'nullable|integer|exists:user_gateways,id',
        ]);

        // Map user_gateway_id to active_gateway_id
        if (array_key_exists('user_gateway_id', $validated)) {
            $validated['active_gateway_id'] = $validated['user_gateway_id'];
            unset($validated['user_gateway_id']);
        }

        $bot->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Bot updated successfully',
            'data' => $bot,
        ]);
    }

    /**
     * API: Delete bot
     */
    public function apiDestroy(Request $request, Bot $bot)
    {
        $user = $request->user();

        // Semua user hanya bisa hapus bot miliknya
        if ($bot->user_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $bot->delete();

        return response()->json([
            'success' => true,
            'message' => 'Bot deleted successfully',
        ]);
    }
}
