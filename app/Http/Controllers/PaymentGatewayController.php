<?php

namespace App\Http\Controllers;

use App\Models\PaymentGateway;
use App\Models\UserGateway;
use App\Models\Bot;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PaymentGatewayController extends Controller
{
    /**
     * Display list of available gateways
     */
    public function index()
    {
        $gateways = PaymentGateway::active()->get();

        $userGateways = UserGateway::where('user_id', auth()->id())
            ->with(['gateway', 'bots'])  // Include bots relationship to show IN USE
            ->get()
            ->keyBy('gateway_id');

        return Inertia::render('PaymentGateways/Index', [
            'gateways' => $gateways,
            'userGateways' => $userGateways,
        ]);
    }

    /**
     * Show form to configure a gateway
     */
    public function configure($gatewayId)
    {
        $gateway = PaymentGateway::findOrFail($gatewayId);

        // Parse required_fields to array for frontend
        $requiredFields = $gateway->required_fields;
        if (is_string($requiredFields)) {
            $requiredFields = json_decode($requiredFields, true) ?? [];
        }
        // Convert object format {field: {type:..}} to array of field names
        if (is_array($requiredFields) && !isset($requiredFields[0])) {
            $requiredFields = array_keys($requiredFields);
        }

        $gatewayData = $gateway->toArray();
        $gatewayData['required_fields'] = $requiredFields;

        $userGateway = UserGateway::where('user_id', auth()->id())
            ->where('gateway_id', $gatewayId)
            ->first();

        $bots = Bot::where('user_id', auth()->id())->get();

        return Inertia::render('PaymentGateways/Configure', [
            'gateway' => $gatewayData,
            'userGateway' => $userGateway,
            'bots' => $bots,
        ]);
    }

    /**
     * Save gateway configuration
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'gateway_id' => 'required|exists:payment_gateways,id',
            'credentials' => 'required|array',
            'label' => 'nullable|string|max:100',
        ]);

        $gateway = PaymentGateway::findOrFail($validated['gateway_id']);

        // Parse required_fields - can be array, object, or JSON string
        $requiredFields = $gateway->required_fields ?? [];
        if (is_string($requiredFields)) {
            $requiredFields = json_decode($requiredFields, true) ?? [];
        }
        // If it's an associative array (object), get the keys
        if (is_array($requiredFields) && !isset($requiredFields[0])) {
            $requiredFields = array_keys($requiredFields);
        }

        foreach ($requiredFields as $field) {
            if (empty($validated['credentials'][$field])) {
                return back()->withErrors([$field => "Field {$field} is required"]);
            }
        }

        $userGateway = UserGateway::updateOrCreate(
            [
                'user_id' => auth()->id(),
                'gateway_id' => $validated['gateway_id'],
            ],
            [
                'credentials' => $validated['credentials'],
                'label' => $validated['label'],
                'is_active' => true,
            ]
        );

        return redirect()->route('payment-gateways.index')
            ->with('success', 'Gateway configured successfully!');
    }

    /**
     * Assign gateway to bot
     */
    public function assignToBot(Request $request)
    {
        $validated = $request->validate([
            'bot_id' => 'required|exists:bots,id',
            'user_gateway_id' => 'required|exists:user_gateways,id',
        ]);

        $bot = Bot::where('id', $validated['bot_id'])
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $userGateway = UserGateway::where('id', $validated['user_gateway_id'])
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $bot->update(['active_gateway_id' => $userGateway->id]);

        return back()->with('success', 'Gateway assigned to bot successfully!');
    }

    /**
     * Remove gateway from bot
     */
    public function removeFromBot(Request $request, $botId)
    {
        $bot = Bot::where('id', $botId)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $bot->update(['active_gateway_id' => null]);

        return back()->with('success', 'Gateway removed from bot');
    }

    /**
     * Delete user gateway configuration
     */
    public function destroy($id)
    {
        $userGateway = UserGateway::where('id', $id)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        // Remove from all bots first
        Bot::where('active_gateway_id', $id)->update(['active_gateway_id' => null]);

        $userGateway->delete();

        return redirect()->route('payment-gateways.index')
            ->with('success', 'Gateway configuration deleted');
    }

    // ============================================================
    // API METHODS (for SPA Dashboard)
    // ============================================================

    /**
     * API: Get all available gateways
     */
    public function apiIndex()
    {
        $gateways = PaymentGateway::active()->get()->map(function ($g) {
            // Parse required_fields
            $requiredFields = $g->required_fields;
            if (is_string($requiredFields)) {
                $requiredFields = json_decode($requiredFields, true) ?? [];
            }

            return [
                'id' => $g->id,
                'name' => $g->name,
                'code' => $g->code,
                'logo' => $g->logo,
                'description' => $g->description,
                'fee_percent' => $g->fee_percent,
                'fee_flat' => $g->fee_flat,
                'required_fields' => $requiredFields,
            ];
        });

        return response()->json($gateways);
    }

    /**
     * API: Get user's configured gateways
     */
    public function apiUserGateways(Request $request)
    {
        $userGateways = UserGateway::where('user_id', $request->user()->id)
            ->with('gateway')
            ->get()
            ->map(fn ($ug) => [
                'id' => $ug->id,
                'gateway_id' => $ug->gateway_id,
                'gateway' => $ug->gateway ? [
                    'id' => $ug->gateway->id,
                    'name' => $ug->gateway->name,
                    'code' => $ug->gateway->code,
                    'logo' => $ug->gateway->logo,
                    'fee_percent' => $ug->gateway->fee_percent,
                    'fee_flat' => $ug->gateway->fee_flat,
                ] : null,
                'credentials' => $ug->credentials,
                'label' => $ug->label,
                'is_active' => $ug->is_active,
                'is_default' => $ug->is_default,
            ]);

        return response()->json($userGateways);
    }

    /**
     * API: Configure a gateway
     */
    public function apiConfigure(Request $request, PaymentGateway $gateway)
    {
        $validated = $request->validate([
            'credentials' => 'required|array',
            'label' => 'nullable|string|max:100',
        ]);

        $userGateway = UserGateway::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'gateway_id' => $gateway->id,
            ],
            [
                'credentials' => $validated['credentials'],
                'label' => $validated['label'] ?? $gateway->name,
                'is_active' => true,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Gateway configured successfully',
            'data' => $userGateway,
        ]);
    }

    /**
     * API: Set a gateway as default
     */
    public function apiSetDefault(Request $request, $gatewayId)
    {
        $userId = $request->user()->id;

        // Find user gateway
        $userGateway = UserGateway::where('user_id', $userId)
            ->where('gateway_id', $gatewayId)
            ->first();

        if (!$userGateway) {
            return response()->json([
                'success' => false,
                'message' => 'Gateway not configured yet',
            ], 404);
        }

        // Remove default from all user's gateways
        UserGateway::where('user_id', $userId)->update(['is_default' => false]);

        // Set this one as default
        $userGateway->update(['is_default' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Default gateway updated',
        ]);
    }

    /**
     * API: Toggle gateway active status
     */
    public function apiToggleActive(Request $request, $gatewayId)
    {
        $userGateway = UserGateway::where('user_id', $request->user()->id)
            ->where('gateway_id', $gatewayId)
            ->first();

        if (!$userGateway) {
            return response()->json([
                'success' => false,
                'message' => 'Gateway not configured yet',
            ], 404);
        }

        $userGateway->update(['is_active' => !$userGateway->is_active]);

        return response()->json([
            'success' => true,
            'message' => $userGateway->is_active ? 'Gateway activated' : 'Gateway deactivated',
            'is_active' => $userGateway->is_active,
        ]);
    }

    /**
     * API: Assign gateway to bot
     */
    public function apiAssignToBot(Request $request)
    {
        $validated = $request->validate([
            'bot_id' => 'required|exists:bots,id',
            'user_gateway_id' => 'required|exists:user_gateways,id',
        ]);

        $bot = Bot::where('id', $validated['bot_id'])
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$bot) {
            return response()->json([
                'success' => false,
                'message' => 'Bot not found',
            ], 404);
        }

        $userGateway = UserGateway::where('id', $validated['user_gateway_id'])
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$userGateway) {
            return response()->json([
                'success' => false,
                'message' => 'Gateway configuration not found',
            ], 404);
        }

        $bot->update(['active_gateway_id' => $userGateway->id]);

        return response()->json([
            'success' => true,
            'message' => 'Gateway assigned to bot successfully',
        ]);
    }

    /**
     * API: Delete user gateway configuration
     */
    public function apiDestroy(Request $request, $id)
    {
        $userGateway = UserGateway::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$userGateway) {
            return response()->json([
                'success' => false,
                'message' => 'Gateway configuration not found',
            ], 404);
        }

        // Remove from all bots first
        Bot::where('active_gateway_id', $id)->update(['active_gateway_id' => null]);

        $userGateway->delete();

        return response()->json([
            'success' => true,
            'message' => 'Gateway configuration deleted',
        ]);
    }
}
