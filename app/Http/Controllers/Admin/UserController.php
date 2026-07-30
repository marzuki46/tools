<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tools\Tool;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    private function authorizeAdmin(): void
    {
        abort_unless(auth()->user()?->is_admin, 403, 'Unauthorized access.');
    }

    public function index(Request $request)
    {
        $this->authorizeAdmin();

        $query = User::withCount('apiKeys');

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->orderBy('created_at', 'desc')->paginate(20);

        return view('admin.users.index', compact('users'));
    }

    public function edit(User $user)
    {
        $this->authorizeAdmin();
        $user->loadCount('apiKeys');
        $user->load('tools');
        $tools = Tool::where('is_active', true)->orderBy('name')->get();
        return view('admin.users.edit', compact('user', 'tools'));
    }

    public function update(Request $request, User $user)
    {
        $this->authorizeAdmin();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $user->id,
            'is_admin' => 'boolean',
            'password' => 'nullable|string|min:8',
        ]);

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->is_admin = $validated['is_admin'] ?? false;

        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        return redirect()->route('admin.users.edit', $user)
            ->with('success', 'User updated successfully.');
    }

    public function suspend(User $user)
    {
        $this->authorizeAdmin();

        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot suspend your own account.');
        }

        $user->suspended_at = now();
        $user->save();

        return redirect()->route('admin.users.edit', $user)
            ->with('success', "User {$user->name} has been suspended.");
    }

    public function unsuspend(User $user)
    {
        $this->authorizeAdmin();

        $user->suspended_at = null;
        $user->save();

        return redirect()->route('admin.users.edit', $user)
            ->with('success', "User {$user->name} has been restored.");
    }

    public function toggleTool(User $user, Tool $tool)
    {
        $this->authorizeAdmin();

        $user->tools()->toggle($tool->id);

        $hasAccess = $user->tools()->where('tool_id', $tool->id)->exists();
        $msg = $hasAccess
            ? "{$tool->name} activated for {$user->name}."
            : "{$tool->name} deactivated for {$user->name}.";

        return redirect()->route('admin.users.edit', $user)
            ->with('success', $msg);
    }

    public function apiKeys(User $user)
    {
        $this->authorizeAdmin();
        $keys = $user->apiKeys()->with('user')->orderBy('created_at', 'desc')->paginate(20);
        return view('admin.users.api_keys', compact('user', 'keys'));
    }

    public function destroy(User $user)
    {
        $this->authorizeAdmin();

        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        $user->apiKeys()->delete();
        $user->tools()->detach();
        $user->delete();

        return redirect()->route('admin.users.index')
            ->with('success', "User {$user->name} has been deleted.");
    }
}
