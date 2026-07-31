<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') - Juki Tools</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="bg-gray-50 text-gray-900 antialiased flex min-h-screen">
    {{-- Sidebar --}}
    <aside class="w-72 bg-gray-900 text-gray-300 flex flex-col flex-shrink-0 min-h-screen">
        <div class="p-5 border-b border-gray-800">
            <a href="{{ route('dashboard') }}" class="text-xl font-bold text-indigo-400">Juki Tools</a>
            <p class="text-xs text-gray-500 mt-0.5">Tool Library Platform</p>
        </div>

        @auth
        <nav class="flex-1 overflow-y-auto p-3 space-y-1">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider px-3 pt-4 pb-1">Main</p>
            <a href="{{ route('dashboard') }}"
               class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('dashboard') ? 'bg-indigo-600 text-white' : 'text-gray-400 hover:text-white hover:bg-gray-800' }} transition">
                <span>📊</span> Dashboard
            </a>

            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider px-3 pt-4 pb-1">Management</p>
            <a href="{{ route('websites.index') }}"
               class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('websites.*') ? 'bg-indigo-600 text-white' : 'text-gray-400 hover:text-white hover:bg-gray-800' }} transition">
                <span>🌐</span> Websites
            </a>
            <a href="{{ route('api-keys.index') }}"
               class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('api-keys.*') ? 'bg-indigo-600 text-white' : 'text-gray-400 hover:text-white hover:bg-gray-800' }} transition">
                <span>🔑</span> API Keys
            </a>

            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider px-3 pt-4 pb-1">Tools</p>
            <a href="{{ route('documentation.index') }}"
               class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('documentation.*') ? 'bg-indigo-600 text-white' : 'text-gray-400 hover:text-white hover:bg-gray-800' }} transition">
                <span>📖</span> Dokumentasi
            </a>
            <a href="{{ route('tools.index') }}"
               class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('tools.index') || request()->routeIs('tools.toggle') ? 'bg-indigo-600 text-white' : 'text-gray-400 hover:text-white hover:bg-gray-800' }} transition">
                <span>🧰</span> Tool Settings
            </a>
            @if (\Illuminate\Support\Facades\Auth::user()->hasToolAccess('meta-ads-generator'))
                <a href="{{ route('metaadsimagegenerator.index') }}"
                   class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('metaadsimagegenerator.*') && !request()->routeIs('metaadsimagegenerator.brand-kits.*') && !request()->routeIs('metaadsimagegenerator.presets.*') ? 'bg-indigo-600 text-white' : 'text-gray-400 hover:text-white hover:bg-gray-800' }} transition">
                    <span>📸</span> Meta Ads
                </a>
                <a href="{{ route('metaadsimagegenerator.brand-kits.index') }}"
                   class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('metaadsimagegenerator.brand-kits.*') ? 'bg-indigo-600 text-white' : 'text-gray-400 hover:text-white hover:bg-gray-800' }} transition">
                    <span>🎨</span> Brand Kits
                </a>
                <a href="{{ route('metaadsimagegenerator.presets.index') }}"
                   class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('metaadsimagegenerator.presets.*') ? 'bg-indigo-600 text-white' : 'text-gray-400 hover:text-white hover:bg-gray-800' }} transition">
                    <span>📋</span> Presets
                </a>
            @endif
            @if (\Illuminate\Support\Facades\Auth::user()->hasToolAccess('keyword-research'))
                <a href="{{ route('keywordresearch.index') }}"
                   class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('keywordresearch.*') ? 'bg-indigo-600 text-white' : 'text-gray-400 hover:text-white hover:bg-gray-800' }} transition">
                    <span>🔍</span> Keyword Research
                </a>
            @endif
            @if (\Illuminate\Support\Facades\Auth::user()->hasToolAccess('content-generator'))
                <a href="{{ route('contentgenerator.index') }}"
                   class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('contentgenerator.*') ? 'bg-indigo-600 text-white' : 'text-gray-400 hover:text-white hover:bg-gray-800' }} transition">
                    <span>📝</span> Content Generator
                </a>
            @endif
            @if (\Illuminate\Support\Facades\Auth::user()->hasToolAccess('keyword-clusters'))
                <a href="{{ route('seocluster.index') }}"
                   class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('seocluster.*') ? 'bg-indigo-600 text-white' : 'text-gray-400 hover:text-white hover:bg-gray-800' }} transition">
                    <span>🗂️</span> Keyword Clusters
                </a>
            @endif
            @if (\Illuminate\Support\Facades\Auth::user()->hasToolAccess('content-analyzer'))
                <a href="{{ route('agentconnector.analyzer') }}"
                   class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('agentconnector.analyzer') ? 'bg-indigo-600 text-white' : 'text-gray-400 hover:text-white hover:bg-gray-800' }} transition">
                    <span>📊</span> Content Analyzer
                </a>
            @endif
            @if (\Illuminate\Support\Facades\Auth::user()->hasToolAccess('agent-connector'))
                <a href="{{ route('agentconnector.index') }}"
                   class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('agentconnector.index') ? 'bg-indigo-600 text-white' : 'text-gray-400 hover:text-white hover:bg-gray-800' }} transition">
                    <span>🤖</span> Agent Connector
                </a>
            @endif
            <a href="{{ route('business-profiles.index') }}"
               class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('business-profiles.*') ? 'bg-indigo-600 text-white' : 'text-gray-400 hover:text-white hover:bg-gray-800' }} transition">
                <span>🏢</span> Profil Bisnis
            </a>
            @if (\Illuminate\Support\Facades\Auth::user()->hasToolAccess('seo-analyzer'))
            <a href="{{ route('seo-analyzer.index') }}"
               class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('seo-analyzer.*') ? 'bg-indigo-600 text-white' : 'text-gray-400 hover:text-white hover:bg-gray-800' }} transition">
                <span>🔍</span> SEO Analyzer
            </a>
            @endif
            @if (\Illuminate\Support\Facades\Auth::user()->hasToolAccess('schema-markup'))
            <a href="{{ route('schema-markup.index') }}"
               class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('schema-markup.*') ? 'bg-indigo-600 text-white' : 'text-gray-400 hover:text-white hover:bg-gray-800' }} transition">
                <span>📋</span> Schema Markup
            </a>
            @endif

            @if (\Illuminate\Support\Facades\Auth::user()->is_admin)
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider px-3 pt-4 pb-1">Admin</p>
                <a href="{{ route('admin.users.index') }}"
                   class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('admin.users.*') ? 'bg-indigo-600 text-white' : 'text-gray-400 hover:text-white hover:bg-gray-800' }} transition">
                    <span>👥</span> Users
                </a>
                <a href="{{ route('admin.tools') }}"
                   class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('admin.tools*') ? 'bg-indigo-600 text-white' : 'text-gray-400 hover:text-white hover:bg-gray-800' }} transition">
                    <span>⚙️</span> Admin Tools
                </a>
                <a href="{{ route('admin.settings') }}"
                   class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('admin.settings*') ? 'bg-indigo-600 text-white' : 'text-gray-400 hover:text-white hover:bg-gray-800' }} transition">
                    <span>🔧</span> AI Settings
                </a>
                <a href="{{ route('admin.api-guide') }}"
                   class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('admin.api-guide*') ? 'bg-indigo-600 text-white' : 'text-gray-400 hover:text-white hover:bg-gray-800' }} transition">
                    <span>🔌</span> API Guide
                </a>
            @endif
        </nav>

        <div class="p-4 border-t border-gray-800">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-8 h-8 rounded-full bg-indigo-600 flex items-center justify-center text-white text-xs font-bold flex-shrink-0">
                    {{ substr(Auth::user()->name, 0, 2) }}
                </div>
                <div class="min-w-0">
                    <p class="text-sm font-medium text-gray-200 truncate">{{ Auth::user()->name }}</p>
                    <p class="text-xs text-gray-500 truncate">{{ Auth::user()->email }}</p>
                </div>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="w-full text-left text-sm text-gray-500 hover:text-red-400 transition px-2 py-1">🚪 Logout</button>
            </form>
        </div>
        @endauth

        @guest
        <nav class="p-3 space-y-1">
            <a href="{{ route('login') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-gray-400 hover:text-white hover:bg-gray-800 transition"><span>🔐</span> Login</a>
            <a href="{{ route('register') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-gray-400 hover:text-white hover:bg-gray-800 transition"><span>📝</span> Register</a>
        </nav>
        @endguest
    </aside>

    {{-- Main --}}
    <div class="flex-1 min-w-0">
        @auth
        <header class="bg-white border-b border-gray-200 px-8 py-4 flex items-center justify-between">
            <h2 class="text-lg font-semibold text-gray-900">@yield('title', 'Dashboard')</h2>
            @if (Auth::user()->is_admin)
                <span class="px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-700">Admin</span>
            @endif
        </header>
        @endauth

        <main class="p-8 max-w-7xl mx-auto">
            @if (session('success'))
                <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg text-sm text-green-700">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">{{ session('error') }}</div>
            @endif
            @yield('content')
        </main>

        <footer class="text-center text-xs text-gray-400 py-6 border-t border-gray-200">&copy; {{ date('Y') }} Juki Tools. All rights reserved.</footer>
    </div>
@stack('scripts')
</body>
</html>
