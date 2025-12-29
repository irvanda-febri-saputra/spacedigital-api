<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class UserController extends Controller
{
    public function index(Request $request)
    {
        // Only super_admin can access
        if (!$request->user()->isSuperAdmin()) {
            abort(403);
        }

        $query = User::query();

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by role
        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->withCount('bots')
            ->latest()
            ->paginate(20)
            ->through(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'status' => $user->status,
                'bots_count' => $user->bots_count,
                'created_at' => $user->created_at->format('d M Y'),
            ]);

        // Stats
        $stats = [
            'total' => User::count(),
            'active' => User::where('status', 'active')->count(),
            'pending' => User::where('status', 'pending')->count(),
            'suspended' => User::where('status', 'suspended')->count(),
        ];

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'stats' => $stats,
            'filters' => $request->only(['status', 'role', 'search']),
        ]);
    }

    public function approve(User $user)
    {
        if (!request()->user()->isSuperAdmin()) {
            abort(403);
        }

        $user->update(['status' => 'active']);

        // Generate API Key if not exists
        if (!$user->api_key) {
            $user->generateApiKey();
        }

        return back()->with('success', "User {$user->name} has been approved!");
    }

    public function suspend(User $user)
    {
        if (!request()->user()->isSuperAdmin()) {
            abort(403);
        }

        // Prevent self-suspension
        if ($user->id === request()->user()->id) {
            return back()->with('error', 'You cannot suspend yourself!');
        }

        $user->update(['status' => 'suspended']);

        return back()->with('success', "User {$user->name} has been suspended!");
    }

    public function updateRole(Request $request, User $user)
    {
        if (!$request->user()->isSuperAdmin()) {
            abort(403);
        }

        // Prevent self role change
        if ($user->id === $request->user()->id) {
            return back()->with('error', 'You cannot change your own role!');
        }

        $validated = $request->validate([
            'role' => 'required|in:super_admin,user',
        ]);

        $user->update(['role' => $validated['role']]);

        return back()->with('success', "User {$user->name} role updated to {$validated['role']}!");
    }

    public function destroy(User $user)
    {
        if (!request()->user()->isSuperAdmin()) {
            abort(403);
        }

        // Prevent self-deletion
        if ($user->id === request()->user()->id) {
            return back()->with('error', 'You cannot delete yourself!');
        }

        $user->delete();

        return back()->with('success', 'User deleted successfully!');
    }

    // ============================================================
    // API Methods for React SPA
    // ============================================================

    public function apiIndex(Request $request)
    {
        if (!$request->user()->isSuperAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $query = User::query();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->withCount('bots')
            ->latest()
            ->paginate(20)
            ->through(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'status' => $user->status,
                'avatar_style' => $user->avatar_style,
                'avatar_seed' => $user->avatar_seed,
                'bots_count' => $user->bots_count,
                'created_at' => $user->created_at->format('d M Y'),
            ]);

        $stats = [
            'total' => User::count(),
            'active' => User::where('status', 'active')->count(),
            'pending' => User::where('status', 'pending')->count(),
            'suspended' => User::where('status', 'suspended')->count(),
        ];

        return response()->json([
            'users' => $users,
            'stats' => $stats,
        ]);
    }

    public function apiApprove(Request $request, User $user)
    {
        if (!$request->user()->isSuperAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $user->update(['status' => 'active']);

        if (!$user->api_key) {
            $user->generateApiKey();
        }

        return response()->json(['success' => true, 'message' => "User {$user->name} has been approved!"]);
    }

    public function apiSuspend(Request $request, User $user)
    {
        if (!$request->user()->isSuperAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if ($user->id === $request->user()->id) {
            return response()->json(['error' => 'You cannot suspend yourself!'], 400);
        }

        $user->update(['status' => 'suspended']);

        return response()->json(['success' => true, 'message' => "User {$user->name} has been suspended!"]);
    }

    public function apiUpdateRole(Request $request, User $user)
    {
        if (!$request->user()->isSuperAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if ($user->id === $request->user()->id) {
            return response()->json(['error' => 'You cannot change your own role!'], 400);
        }

        $validated = $request->validate([
            'role' => 'required|in:super_admin,user',
        ]);

        $user->update(['role' => $validated['role']]);

        return response()->json(['success' => true, 'message' => "User role updated!"]);
    }

    public function apiDestroy(Request $request, User $user)
    {
        if (!$request->user()->isSuperAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if ($user->id === $request->user()->id) {
            return response()->json(['error' => 'You cannot delete yourself!'], 400);
        }

        $user->delete();

        return response()->json(['success' => true, 'message' => 'User deleted successfully!']);
    }
}
