<?php

namespace App\Http\Controllers;

use App\Models\Tool;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ToolController extends Controller
{
    public function index()
    {
        $tools = Tool::orderBy('name')->get();
        $userToolIds = Auth::user()->tools()->pluck('tools.id')->toArray();

        return view('dashboard.tools', [
            'tools' => $tools,
            'userToolIds' => $userToolIds,
        ]);
    }

    public function toggle(Request $request, Tool $tool)
    {
        Auth::user()->tools()->toggle([$tool->id => ['is_active' => true]]);

        $enabled = Auth::user()->tools()->where('tool_id', $tool->id)->exists();
        $message = $enabled
            ? "{$tool->name} has been added to your account."
            : "{$tool->name} has been removed from your account.";

        return redirect()->route('tools.index')->with('success', $message);
    }

    private function authorizeAdmin(): void
    {
        abort_unless(Auth::user()->email === 'admin@juki-tools.test', 403, 'Admin access required.');
    }

    public function adminIndex()
    {
        $this->authorizeAdmin();

        $tools = Tool::withCount(['users' => function ($q) {
            $q->where('tool_user.is_active', true);
        }])->orderBy('name')->get();

        return view('admin.tools', ['tools' => $tools]);
    }

    public function adminToggleActive(Request $request, Tool $tool)
    {
        $this->authorizeAdmin();

        $tool->update(['is_active' => !$tool->is_active]);

        $status = $tool->is_active ? 'activated' : 'deactivated';
        return redirect()->route('admin.tools')->with('success', "Tool {$status} successfully.");
    }

    public function adminUsers(Tool $tool)
    {
        $this->authorizeAdmin();

        $users = User::with(['tools' => function ($q) use ($tool) {
            $q->where('tool_id', $tool->id);
        }])->orderBy('name')->paginate(20);

        return view('admin.tool-users', ['tool' => $tool, 'users' => $users]);
    }

    public function adminToggleUser(Request $request, Tool $tool, User $user)
    {
        $this->authorizeAdmin();

        $user->tools()->toggle($tool->id);

        return redirect()->route('admin.tool-users', $tool)
            ->with('success', "Access for {$user->name} updated.");
    }
}
