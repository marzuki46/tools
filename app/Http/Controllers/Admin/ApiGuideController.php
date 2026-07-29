<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tools\Tool;
use App\Models\User;
use Illuminate\Http\Request;

class ApiGuideController extends Controller
{
    public function __construct()
    {
        abort_unless(auth()->user()?->is_admin, 403, 'Admin access required.');
    }

    public function index()
    {
        $tools = Tool::with(['users' => function ($q) {
            $q->wherePivot('is_active', true)->orderBy('name');
        }])->where('is_active', true)->orderBy('name')->get();

        return view('admin.api-guide.index', compact('tools'));
    }
}
