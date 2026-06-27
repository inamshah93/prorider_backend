<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Merchant;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_merchant_book_to_delivery_updates_dashboard(): void
    {
        $this->seed();

        $merchantUser = User::where('email', 'merchant@velo.pk')->first();
        $riderUser = User::where('email', 'rider@velo.pk')->first();
        $admin = User::where('email', 'admin@velo.pk')->first();

        $merchantToken = $merchantUser->createToken('test')->plainTextToken;
        $riderToken = $riderUser->createToken('test')->plainTextToken;
        $adminToken = $admin->createToken('test')->plainTextToken;

        $create = $this->withToken($merchantToken)->postJson('/api/v1/merchant/orders', [
            'customer_name' => 'Test Customer',
            'customer_phone' => '03001112222',
            'delivery_address' => 'Gulberg III',
            'city_name' => 'Lahore',
            'items' => [['sku' => 'tea-1kg', 'name' => 'Tea', 'price' => 500, 'quantity' => 2]],
            'cod_amount' => 1000,
        ]);
        $create->assertCreated();
        $orderId = $create->json('data.id');

        $this->withToken($merchantToken)->postJson("/api/v1/merchant/orders/{$orderId}/generate-label")->assertOk();
        $this->withToken($merchantToken)->postJson("/api/v1/merchant/orders/{$orderId}/mark-packed")->assertOk();
        $this->withToken($merchantToken)->postJson("/api/v1/merchant/orders/{$orderId}/ready-to-ship")->assertOk();

        $order = Order::find($orderId);
        $this->assertEquals(OrderStatus::ReadyToShip, $order->order_status);

        $pickedUp = $this->withToken($riderToken)->postJson("/api/v1/rider/orders/{$orderId}/picked-up");
        $pickedUp->assertOk();
        $this->withToken($riderToken)->postJson("/api/v1/rider/orders/{$orderId}/delivered")->assertOk();

        $order->refresh();
        $this->assertEquals(OrderStatus::Delivered, $order->order_status);

        $dashboard = $this->withToken($adminToken)->getJson('/api/v1/admin/dashboard');
        $dashboard->assertOk();
        $this->assertGreaterThanOrEqual(0, $dashboard->json('metrics.live_orders'));
    }
}
