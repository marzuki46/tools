@extends('layouts.app')

@section('title', 'Edit Website')

@section('content')
<div class="max-w-lg mx-auto">
    <div class="mb-6">
        <a href="{{ route('websites.show', $website) }}" class="text-indigo-600 text-sm hover:underline">&larr; Back</a>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-8">
        <h1 class="text-2xl font-bold mb-2">Edit Website</h1>
        <p class="text-gray-500 text-sm mb-6">Update your domain details.</p>

        <form method="POST" action="{{ route('websites.update', $website) }}">
            @csrf @method('PUT')

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Domain</label>
                <input type="text" name="domain" value="{{ old('domain', $website->domain) }}" required
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                @error('domain')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Website Name</label>
                <input type="text" name="name" value="{{ old('name', $website->name) }}" required
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                <textarea name="description" rows="3"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">{{ old('description', $website->description) }}</textarea>
            </div>

            <div class="flex justify-end space-x-3">
                <a href="{{ route('websites.show', $website) }}"
                    class="px-4 py-2.5 text-sm font-medium text-gray-700 hover:text-gray-900 transition">Cancel</a>
                <button type="submit"
                    class="px-6 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">Save</button>
            </div>
        </form>
    </div>
</div>
@endsection
