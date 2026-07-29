@extends('layouts.app')

@section('title', 'Admin - Tools')

@section('content')
<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold">Tool Management</h1>
        <p class="text-gray-500 text-sm mt-1">Manage available tools and their active status</p>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b">
                <tr class="text-left text-gray-500">
                    <th class="px-6 py-3 font-medium">Tool</th>
                    <th class="px-6 py-3 font-medium">Package</th>
                    <th class="px-6 py-3 font-medium">Active Users</th>
                    <th class="px-6 py-3 font-medium">Status</th>
                    <th class="px-6 py-3 font-medium">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @foreach ($tools as $tool)
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-6 py-4 font-medium">{{ $tool->name }}</td>
                        <td class="px-6 py-4 text-gray-500 font-mono text-xs">{{ $tool->package_name ?? '-' }}</td>
                        <td class="px-6 py-4">{{ $tool->users_count }}</td>
                        <td class="px-6 py-4">
                            @if ($tool->is_active)
                                <span class="text-green-700 bg-green-50 px-2 py-0.5 rounded-full text-xs font-medium">Active</span>
                            @else
                                <span class="text-red-700 bg-red-50 px-2 py-0.5 rounded-full text-xs font-medium">Inactive</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 space-x-2">
                            <form method="POST" action="{{ route('admin.tools.toggle-active', $tool) }}" class="inline">
                                @csrf
                                <button type="submit" class="text-indigo-600 hover:text-indigo-800 text-xs font-medium">
                                    {{ $tool->is_active ? 'Deactivate' : 'Activate' }}
                                </button>
                            </form>
                            <a href="{{ route('admin.tools.users', $tool) }}" class="text-gray-500 hover:text-gray-700 text-xs font-medium">
                                Manage Users
                            </a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
