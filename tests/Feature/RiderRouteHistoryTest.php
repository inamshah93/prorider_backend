<?php

namespace Tests\Feature;

use App\Models\RiderLocationPing;
use App\Models\RiderProfile;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RiderRouteHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_rider_location_updates_append_history(): void
    {
        $riderUser = User::where('email', 'rider@velo.pk')->first();
        $this->assertNotNull($riderUser);
        Sanctum::actingAs($riderUser);

        $this->putJson('/api/v1/rider/location', [
            'lat' => 31.5204,
            'lng' => 74.3587,
            'recorded_at' => CarbonImmutable::parse('2026-07-08 09:00:00')->toISOString(),
            'accuracy_m' => 12.3,
            'speed_mps' => 3.1,
            'heading_deg' => 90,
        ])->assertOk();

        $this->assertDatabaseCount('rider_location_pings', 1);
        $ping = RiderLocationPing::first();
        $this->assertEquals(31.5204, (float) $ping->lat);
        $this->assertEquals(74.3587, (float) $ping->lng);
    }

    public function test_admin_can_fetch_location_history_and_report_for_range(): void
    {
        $admin = User::where('email', 'admin@velo.pk')->first();
        $this->assertNotNull($admin);

        $riderUser = User::where('email', 'rider@velo.pk')->first();
        $profile = RiderProfile::where('user_id', $riderUser->id)->first();
        $this->assertNotNull($profile);

        RiderLocationPing::create([
            'rider_profile_id' => $profile->id,
            'lat' => 31.5204,
            'lng' => 74.3587,
            'recorded_at' => CarbonImmutable::parse('2026-07-08 09:00:00'),
        ]);
        RiderLocationPing::create([
            'rider_profile_id' => $profile->id,
            'lat' => 31.5220,
            'lng' => 74.3800,
            'recorded_at' => CarbonImmutable::parse('2026-07-08 09:02:00'),
        ]);

        Sanctum::actingAs($admin);

        $from = CarbonImmutable::parse('2026-07-08 08:59:00')->toISOString();
        $to = CarbonImmutable::parse('2026-07-08 09:03:00')->toISOString();

        $history = $this->getJson("/api/v1/admin/riders/{$profile->id}/location-history?from={$from}&to={$to}");
        $history->assertOk();
        $history->assertJsonPath('meta.count', 2);
        $this->assertCount(2, $history->json('data'));

        $report = $this->getJson("/api/v1/admin/riders/{$profile->id}/route-report?from={$from}&to={$to}");
        $report->assertOk();
        $report->assertJsonPath('data.ping_count', 2);
        $this->assertGreaterThan(0, (float) $report->json('data.total_distance_km'));
    }
}

