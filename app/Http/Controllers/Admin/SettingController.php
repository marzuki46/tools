<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SettingController extends Controller
{
    public function __construct()
    {
        abort_unless(Auth::user()->is_admin, 403, 'Admin access required.');
    }

    public function index()
    {
        $groups = Setting::get()->groupBy('group');

        return view('admin.settings.index', ['groups' => $groups]);
    }

    public function update(Request $request)
    {
        $settings = $request->input('settings', []);

        foreach ($settings as $key => $value) {
            Setting::setValue($key, $value);
        }

        return redirect()->route('admin.settings')
            ->with('success', 'Settings updated successfully.');
    }
}
