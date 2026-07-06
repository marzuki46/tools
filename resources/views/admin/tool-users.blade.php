@extends('layouts.app')

@section('title', 'Manage Users - ' . $tool->name)

@section('content')
<div class="space-y-6">
    <div class="flex items-center space-x-3">
        <a href="{{ route('admin.tools') }}" class="text-gray-400 hover:text-gray-600 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <div>
            <h1 class="text-2xl font-bold">{{ $tool->name }}</h1>
            <p class="text-gray-500 text-sm mt-1">Manage user access for this tool</p>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b">
                <tr class="text-left text-gray-500">
                    <th class="px-6 py-3 font-medium">Name</th>
                    <th class="px-6 py-3 font-medium">Email</th>
                    <th class="px-6 py-3 font-medium">Access</th>
                    <th class="px-6 py-3 font-medium">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @foreach ($users as $user)
                    @php $hasAccess = $user->tools->isNotEmpty(); @endphp
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-6 py-4 font-medium">{{ $user->name }}</td>
                        <td class="px-6 py-4 text-gray-500">{{ $user->email }}</td>
                        <td class="px-6 py-4">
                            @if ($hasAccess)
                                <span class="text-green-700 bg-green-50 px-2 py-0.5 rounded-full text-xs font-medium">Granted</span>
                            @else
                                <span class="text-gray-500 bg-gray-50 px-2 py-0.5 rounded-full text-xs font-medium">None</span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <form method="POST" action="{{ route('admin.tool-users.toggle', [$tool, $user]) }}" class="inline">
                                @csrf
                                <button type="submit" class="text-xs font-medium {{ $hasAccess ? 'text-red-500 hover:text-red-700' : 'text-indigo-600 hover:text-indigo-800' }}">
                                    {{ $hasAccess ? 'Revoke' : 'Grant Access' }}
                                </button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $users->links() }}
    </div>
</div>
@endsection
