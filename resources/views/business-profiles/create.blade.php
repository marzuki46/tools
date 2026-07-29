@extends('layouts.app')

@section('title', 'Tambah Profil Bisnis')

@section('content')
<div class="max-w-2xl mx-auto space-y-6">
    <div class="flex items-center gap-3">
        <a href="{{ route('business-profiles.index') }}" class="text-gray-400 hover:text-gray-600">&larr; Kembali</a>
        <h1 class="text-2xl font-bold">Tambah Profil Bisnis</h1>
    </div>

    @include('business-profiles.form', ['profile' => null])
</div>
@endsection
