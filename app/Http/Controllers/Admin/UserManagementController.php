<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserManagementController extends Controller
{
    /**
     * Display a listing of users.
     */
    public function index(Request $request): Response
    {
        $query = User::query()->with('profile:id,user_id,aspri_name,call_preference');

        // Search
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%");
            });
        }

        // Filter by role
        if ($role = $request->get('role')) {
            $query->where('role', $role);
        }

        // Filter by status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $users = $query
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/users/Index', [
            'users' => $users,
            'filters' => $request->only(['search', 'role', 'is_active']),
        ]);
    }

    /**
     * Display user details.
     */
    public function show(User $user): Response
    {
        $user->load(['profile', 'chatThreads', 'financeAccounts']);

        $stats = [
            'total_messages' => $user->chatMessages()->count(),
            'total_transactions' => $user->financeTransactions()->count(),
            'total_schedules' => $user->schedules()->count(),
            'recent_activities' => ActivityLog::where('user_id', $user->id)
                ->orderByDesc('created_at')
                ->limit(10)
                ->get(),
        ];

        return Inertia::render('admin/users/Show', [
            'user' => $user,
            'stats' => $stats,
        ]);
    }

    /**
     * Update user details.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users')->ignore($user->id)],
            'role' => ['required', Rule::in(['user', 'admin', 'super_admin'])],
            'is_active' => ['required', 'boolean'],
        ]);

        $oldValues = $user->only(['name', 'email', 'role', 'is_active']);
        $user->update($validated);

        ActivityLog::log(
            'update',
            "Updated user: {$user->name}",
            $user,
            $oldValues,
            $validated
        );

        return back()->with('success', 'User updated successfully.');
    }

    /**
     * Toggle user active status.
     */
    public function toggleActive(User $user): RedirectResponse
    {
        // Prevent self-deactivation
        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot deactivate your own account.');
        }

        $oldStatus = $user->is_active;
        $user->update(['is_active' => ! $user->is_active]);

        $action = $user->is_active ? 'activated' : 'deactivated';
        ActivityLog::log(
            $action,
            "{$action} user: {$user->name}",
            $user,
            ['is_active' => $oldStatus],
            ['is_active' => $user->is_active]
        );

        return back()->with('success', "User {$action} successfully.");
    }

    /**
     * Reset user password.
     */
    public function resetPassword(User $user): RedirectResponse
    {
        $newPassword = Str::random(12);
        $user->update(['password' => Hash::make($newPassword)]);

        ActivityLog::log(
            'password_reset',
            "Reset password for user: {$user->name}",
            $user
        );

        // In production, you would send this via email
        return back()->with('success', "Password reset to: {$newPassword}");
    }

    /**
     * Delete a user.
     */
    public function destroy(User $user): RedirectResponse
    {
        // Prevent self-deletion
        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        $userName = $user->name;
        $user->delete();

        ActivityLog::log(
            'delete',
            "Deleted user: {$userName}"
        );

        return redirect()->route('admin.users.index')
            ->with('success', 'User deleted successfully.');
    }
}
