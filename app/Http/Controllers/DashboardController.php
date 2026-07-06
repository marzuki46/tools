<?php

namespace App\Http\Controllers;

use App\Models\Tool;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $stats = [
            'total_keys' => $user->apiKeys()->count(),
            'active_keys' => $user->apiKeys()
                ->where('is_active', true)
                ->where(function ($q) {
                    $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->count(),
            'tools_count' => Tool::active()->count(),
        ];

        $recentKeys = $user->apiKeys()
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        return view('dashboard.index', [
            'stats' => $stats,
            'recent_keys' => $recentKeys,
        ]);
    }
}
