<?php

namespace App\Http\Controllers;

use App\Models\BusinessProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BusinessProfileController extends Controller
{
    public function index()
    {
        $profiles = BusinessProfile::forUser(auth()->id())
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get();

        return view('business-profiles.index', compact('profiles'));
    }

    public function create()
    {
        return view('business-profiles.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'business_name' => 'nullable|string|max:255',
            'website_url' => 'nullable|url|max:255',
            'description' => 'nullable|string',
            'products_services' => 'nullable|string',
            'target_audience' => 'nullable|string|max:255',
            'usp' => 'nullable|string',
            'business_hours' => 'nullable|string|max:255',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:255',
            'social_media' => 'nullable|array',
            'is_default' => 'boolean',
        ]);

        if ($request->boolean('is_default')) {
            BusinessProfile::forUser(auth()->id())->update(['is_default' => false]);
        }

        $validated['user_id'] = auth()->id();
        $validated['is_default'] = $request->boolean('is_default');

        BusinessProfile::create($validated);

        return redirect()->route('business-profiles.index')
            ->with('success', 'Profil bisnis berhasil dibuat.');
    }

    public function edit(BusinessProfile $businessProfile)
    {
        $this->authorizeAccess($businessProfile);
        return view('business-profiles.edit', ['profile' => $businessProfile]);
    }

    public function update(Request $request, BusinessProfile $businessProfile)
    {
        $this->authorizeAccess($businessProfile);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'business_name' => 'nullable|string|max:255',
            'website_url' => 'nullable|url|max:255',
            'description' => 'nullable|string',
            'products_services' => 'nullable|string',
            'target_audience' => 'nullable|string|max:255',
            'usp' => 'nullable|string',
            'business_hours' => 'nullable|string|max:255',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:255',
            'social_media' => 'nullable|array',
            'is_default' => 'boolean',
        ]);

        if ($request->boolean('is_default')) {
            BusinessProfile::forUser(auth()->id())->where('id', '!=', $businessProfile->id)->update(['is_default' => false]);
        }

        $validated['is_default'] = $request->boolean('is_default');
        $businessProfile->update($validated);

        return redirect()->route('business-profiles.index')
            ->with('success', 'Profil bisnis berhasil diperbarui.');
    }

    public function destroy(BusinessProfile $businessProfile)
    {
        $this->authorizeAccess($businessProfile);
        $businessProfile->delete();

        return redirect()->route('business-profiles.index')
            ->with('success', 'Profil bisnis berhasil dihapus.');
    }

    public function apiList(Request $request)
    {
        $userId = $request->user()->id ?? auth()->id();
        $profiles = BusinessProfile::forUser($userId)->active()->get()->map(fn($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'business_name' => $p->business_name,
            'website_url' => $p->website_url,
            'description' => $p->description,
            'products_services' => $p->products_services,
            'target_audience' => $p->target_audience,
            'usp' => $p->usp,
        ]);

        return response()->json(['data' => $profiles]);
    }

    private function authorizeAccess(BusinessProfile $profile): void
    {
        abort_if($profile->user_id !== auth()->id(), 403);
    }
}
