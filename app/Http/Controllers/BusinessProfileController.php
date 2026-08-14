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
            'writing_rules' => 'nullable|string',
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
            'writing_rules' => 'nullable|string',
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
        $website = $request->attributes->get('api_key_website');

        $profile = BusinessProfile::forUser($userId)->active()
->forWebsiteValue($website?->id)
            ->first();

        $data = $profile ? [[
            'id' => $profile->id,
            'name' => $profile->name,
            'business_name' => $profile->business_name,
            'website_url' => $profile->website_url,
            'description' => $profile->description,
            'products_services' => $profile->products_services,
            'target_audience' => $profile->target_audience,
            'usp' => $profile->usp,
            'writing_rules' => $profile->writing_rules,
            'business_hours' => $profile->business_hours,
            'contact_email' => $profile->contact_email,
            'contact_phone' => $profile->contact_phone,
            'address' => $profile->address,
            'social_media' => $profile->social_media,
            'is_default' => $profile->is_default,
        ]] : [];

        return response()->json(['data' => $data]);
    }

    public function apiStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'business_name' => 'nullable|string|max:255',
            'website_url' => 'nullable|url|max:255',
            'description' => 'nullable|string',
            'products_services' => 'nullable|string',
            'target_audience' => 'nullable|string|max:255',
            'usp' => 'nullable|string',
            'writing_rules' => 'nullable|string',
            'business_hours' => 'nullable|string|max:255',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:255',
            'social_media' => 'nullable|array',
            'is_default' => 'boolean',
        ]);

        $userId = $request->user()->id;
        $website = $request->attributes->get('api_key_website');

        $existing = BusinessProfile::forUser($userId)->forWebsiteValue($website?->id)->first();

        $validated['user_id'] = $userId;
        $validated['api_key_website_id'] = $website?->id;
        $validated['is_default'] = $request->boolean('is_default');

        if ($existing) {
            $existing->update($validated);
            $profile = $existing->fresh();
            $message = 'Profil bisnis berhasil diperbarui.';
            $status = 200;
        } else {
            if ($request->boolean('is_default')) {
                BusinessProfile::forUser($userId)->forWebsiteValue($website?->id)->update(['is_default' => false]);
            }
            $profile = BusinessProfile::create($validated);
            $message = 'Profil bisnis berhasil dibuat.';
            $status = 201;
        }

        return response()->json(['success' => true, 'message' => $message, 'data' => $profile], $status);
    }

    public function apiUpdate(Request $request, BusinessProfile $businessProfile): JsonResponse
    {
        $userId = $request->user()->id;
        $website = $request->attributes->get('api_key_website');
        if ($businessProfile->user_id !== $userId || ($website && $businessProfile->api_key_website_id !== $website->id)) {
            return response()->json(['success' => false, 'message' => 'You do not own this profile.'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'business_name' => 'nullable|string|max:255',
            'website_url' => 'nullable|url|max:255',
            'description' => 'nullable|string',
            'products_services' => 'nullable|string',
            'target_audience' => 'nullable|string|max:255',
            'usp' => 'nullable|string',
            'writing_rules' => 'nullable|string',
            'business_hours' => 'nullable|string|max:255',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:255',
            'social_media' => 'nullable|array',
            'is_default' => 'boolean',
        ]);

        if ($request->boolean('is_default')) {
            BusinessProfile::forUser($userId)
                ->where('api_key_website_id', $website?->id)
                ->where('id', '!=', $businessProfile->id)
                ->update(['is_default' => false]);
        }

        $validated['is_default'] = $request->boolean('is_default');
        $businessProfile->update($validated);

        return response()->json(['success' => true, 'message' => 'Profil bisnis berhasil diperbarui.', 'data' => $businessProfile->fresh()]);
    }

    public function apiDestroy(Request $request, BusinessProfile $businessProfile): JsonResponse
    {
        $website = $request->attributes->get('api_key_website');
        if ($businessProfile->user_id !== $request->user()->id || ($website && $businessProfile->api_key_website_id !== $website->id)) {
            return response()->json(['success' => false, 'message' => 'You do not own this profile.'], 403);
        }

        $businessProfile->delete();

        return response()->json(['success' => true, 'message' => 'Profil bisnis berhasil dihapus.']);
    }

    private function authorizeAccess(BusinessProfile $profile): void
    {
        abort_if($profile->user_id !== auth()->id(), 403);
    }
}
