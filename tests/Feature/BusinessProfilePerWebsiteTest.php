<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\ApiKeyWebsite;
use App\Models\BusinessProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessProfilePerWebsiteTest extends TestCase
{
    use RefreshDatabase;

    private function makeKey(User $user, ?int $maxSites = null): ApiKey
    {
        $prefix = 'juki_testkey_'.uniqid();
        return ApiKey::create([
            'user_id' => $user->id,
            'name' => 'Test Key',
            'key' => hash('sha256', $prefix),
            'key_prefix' => $prefix,
            'is_active' => true,
            'max_sites' => $maxSites,
        ]);
    }

    public function test_profile_is_scoped_and_unique_per_website(): void
    {
        $user = User::factory()->create();
        $key = $this->makeKey($user);

        $headers = [
            'X-API-Key' => $key->key_prefix,
            'X-Site-Domain' => 'web-a.test',
            'Accept' => 'application/json',
        ];

        $createdWebsite = $key->websites()->firstOrCreate(
            ['domain' => 'web-a.test'],
            ['last_ip' => '127.0.0.1']
        );

        // Empty initially
        $this->getJson('/api/v1/business-profiles', $headers)
            ->assertOk()
            ->assertJsonCount(0, 'data');

        // Create profile -> bound to web-a.test
        $this->postJson('/api/v1/business-profiles', [
            'name' => 'Profil Web A',
            'business_name' => 'Bisnis A',
            'is_default' => true,
        ], $headers)->assertCreated();

        $this->assertDatabaseCount('business_profiles', 1);

        // Second create for same website -> upsert, not duplicate
        $this->postJson('/api/v1/business-profiles', [
            'name' => 'Profil Web A Updated',
            'business_name' => 'Bisnis A',
            'is_default' => true,
        ], $headers)->assertOk();

        $this->assertDatabaseCount('business_profiles', 1);
        $this->assertSame('Profil Web A Updated', BusinessProfile::first()->name);
        $this->assertSame(1, (int) BusinessProfile::first()->api_key_website_id);

        // Different website with same key -> sees no profiles
        $otherHeaders = array_replace($headers, ['X-Site-Domain' => 'web-b.test']);
        $this->getJson('/api/v1/business-profiles', $otherHeaders)
            ->assertOk()
            ->assertJsonCount(0, 'data');

        // Different website creates its own profile -> total 2, one per website
        $this->postJson('/api/v1/business-profiles', [
            'name' => 'Profil Web B',
        ], $otherHeaders)->assertCreated();

        $this->assertDatabaseCount('business_profiles', 2);
        $this->assertNotSame(
            BusinessProfile::where('name', 'Profil Web A Updated')->first()->api_key_website_id,
            BusinessProfile::where('name', 'Profil Web B')->first()->api_key_website_id,
        );
    }

    public function test_api_update_rejects_other_websites_profile(): void
    {
        $user = User::factory()->create();
        $key = $this->makeKey($user);

        $websiteA = $key->websites()->create(['domain' => 'web-a.test', 'is_active' => true]);
        $profile = BusinessProfile::create([
            'user_id' => $user->id,
            'api_key_website_id' => $websiteA->id,
            'name' => 'Profil A',
        ]);

        $this->putJson("/api/v1/business-profiles/{$profile->id}", [
            'name' => 'Profil A Edited',
        ], [
            'X-API-Key' => $key->key_prefix,
            'X-Site-Domain' => 'web-b.test',
            'Accept' => 'application/json',
        ])->assertForbidden();

        $this->assertSame('Profil A', $profile->fresh()->name);
    }
}