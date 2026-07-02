<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Merchant;
use App\Models\Order;
use App\Models\PlatformSetting;
use App\Models\RiderProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RiderPaymentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('public');
    }

    public function test_order_creation_snapshots_platform_default_delivery_charge(): void
    {
        PlatformSetting::query()->update(['default_delivery_charge' => 400]);

        $merchantUser = User::where('email', 'merchant@velo.pk')->first();
        Sanctum::actingAs($merchantUser);

        $response = $this->postJson('/api/v1/merchant/orders', [
            'customer_name' => 'Test Customer',
            'customer_phone' => '03001112222',
            'delivery_address' => 'Gulberg III',
            'city_name' => 'Lahore',
            'items' => [['sku' => 'tea-1kg', 'name' => 'Tea', 'price' => 500, 'quantity' => 2]],
            'cod_amount' => 1000,
        ]);

        $response->assertCreated();
        $order = Order::find($response->json('data.id'));
        $this->assertEquals(400.0, (float) $order->delivery_charge);
    }

    public function test_merchant_delivery_charge_override_is_snapshotted_on_order(): void
    {
        $merchantUser = User::where('email', 'merchant@velo.pk')->first();
        $merchant = Merchant::where('user_id', $merchantUser->id)->first();
        $merchant->update(['delivery_charge' => 300]);

        Sanctum::actingAs($merchantUser);

        $response = $this->postJson('/api/v1/merchant/orders', [
            'customer_name' => 'Test Customer',
            'customer_phone' => '03001112222',
            'delivery_address' => 'Gulberg III',
            'city_name' => 'Lahore',
            'items' => [['sku' => 'tea-1kg', 'name' => 'Tea', 'price' => 500, 'quantity' => 2]],
            'cod_amount' => 1500,
        ]);

        $response->assertCreated();
        $order = Order::find($response->json('data.id'));
        $this->assertEquals(300.0, (float) $order->delivery_charge);
    }

    public function test_commission_is_delivery_charge_times_rate_on_delivery(): void
    {
        $merchantUser = User::where('email', 'merchant@velo.pk')->first();
        $riderUser = User::where('email', 'rider@velo.pk')->first();
        $merchant = Merchant::where('user_id', $merchantUser->id)->first();
        $merchant->update(['delivery_charge' => 300]);

        $profile = RiderProfile::where('user_id', $riderUser->id)->first();
        $profile->update(['commission_rate' => 0.10, 'cash_in_hand' => 0]);

        Sanctum::actingAs($merchantUser);
        $create = $this->postJson('/api/v1/merchant/orders', [
            'customer_name' => 'Test Customer',
            'customer_phone' => '03001112222',
            'delivery_address' => 'Gulberg III',
            'city_name' => 'Lahore',
            'items' => [['sku' => 'tea-1kg', 'name' => 'Tea', 'price' => 500, 'quantity' => 2]],
            'cod_amount' => 1500,
        ]);
        $orderId = $create->json('data.id');

        $this->postJson("/api/v1/merchant/orders/{$orderId}/generate-label")->assertOk();
        $this->postJson("/api/v1/merchant/orders/{$orderId}/mark-packed")->assertOk();
        $this->postJson("/api/v1/merchant/orders/{$orderId}/ready-to-ship")->assertOk();

        Sanctum::actingAs($riderUser);
        $this->postJson("/api/v1/rider/orders/{$orderId}/picked-up")->assertOk();
        $this->postJson("/api/v1/rider/orders/{$orderId}/delivered")->assertOk();

        $order = Order::find($orderId)->fresh();
        $this->assertEquals($riderUser->id, $order->rider_id);
        $this->assertEquals(30.0, (float) $order->rider_commission_amount);

        $profile->refresh();
        $this->assertEquals(1470.0, (float) $profile->cash_in_hand);
    }

    public function test_batch_pickup_marks_multiple_orders_picked_up(): void
    {
        $merchantUser = User::where('email', 'merchant@velo.pk')->first();
        $riderUser = User::where('email', 'rider@velo.pk')->first();

        Sanctum::actingAs($merchantUser);
        $orderIds = [];
        foreach (range(1, 2) as $i) {
            $create = $this->postJson('/api/v1/merchant/orders', [
                'customer_name' => "Customer {$i}",
                'customer_phone' => '0300111222'.$i,
                'delivery_address' => 'Gulberg III',
                'city_name' => 'Lahore',
                'items' => [['sku' => 'tea-1kg', 'name' => 'Tea', 'price' => 500, 'quantity' => 1]],
                'cod_amount' => 500,
            ]);
            $orderId = $create->json('data.id');
            $orderIds[] = $orderId;
            $this->postJson("/api/v1/merchant/orders/{$orderId}/generate-label")->assertOk();
            $this->postJson("/api/v1/merchant/orders/{$orderId}/mark-packed")->assertOk();
            $this->postJson("/api/v1/merchant/orders/{$orderId}/ready-to-ship")->assertOk();
        }

        Sanctum::actingAs($riderUser);
        $response = $this->postJson('/api/v1/rider/orders/batch-picked-up', [
            'order_ids' => $orderIds,
        ]);

        $response->assertOk();
        foreach ($orderIds as $orderId) {
            $this->assertEquals(OrderStatus::PickedUp, Order::find($orderId)->order_status);
            $this->assertEquals($riderUser->id, Order::find($orderId)->rider_id);
        }
    }

    public function test_settlement_reduces_rider_cash_in_hand(): void
    {
        $admin = User::where('email', 'admin@velo.pk')->first();
        $riderUser = User::where('email', 'rider@velo.pk')->first();
        $profile = RiderProfile::where('user_id', $riderUser->id)->first();
        $profile->update(['cash_in_hand' => 1470]);

        Sanctum::actingAs($admin);
        $response = $this->post("/api/v1/admin/riders/{$profile->id}/settlements", [
            'amount' => 1470,
            'notes' => 'Full remittance',
            'proof_image' => UploadedFile::fake()->image('proof.jpg'),
        ]);

        $response->assertCreated();
        $profile->refresh();
        $this->assertEquals(0.0, (float) $profile->cash_in_hand);

        Sanctum::actingAs($riderUser);
        $wallet = $this->getJson('/api/v1/rider/wallet');
        $wallet->assertOk();
        $this->assertEquals(0.0, (float) $wallet->json('data.remaining_to_pay'));
        $this->assertEquals(1470.0, (float) $wallet->json('data.total_settled'));
    }
}
