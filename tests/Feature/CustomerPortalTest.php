<?php

namespace Tests\Feature;

use App\Models\Merchant;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerPortalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_admin_can_list_customers(): void
    {
        $admin = User::where('email', 'admin@velo.pk')->first();
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/admin/customers');

        $response->assertOk();
        $response->assertJsonFragment(['email' => 'customer@velo.pk']);
    }

    public function test_customer_sees_order_history_by_phone(): void
    {
        $customer = User::where('email', 'customer@velo.pk')->first();
        $merchant = Merchant::first();

        Order::create([
            'order_reference_number' => 'PR-TEST1234',
            'merchant_id' => $merchant->id,
            'customer_user_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
            'delivery_address' => 'Test Address',
            'target_city_id' => 1,
            'cod_amount' => 500,
            'delivery_charge' => 400,
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'order_status' => 'created',
            'merchant_prep_status' => 'created',
        ]);

        Sanctum::actingAs($customer);

        $this->getJson('/api/v1/customer/profile')->assertOk();
        $orders = $this->getJson('/api/v1/customer/orders');
        $orders->assertOk();
        $this->assertGreaterThanOrEqual(1, count($orders->json('data')));
    }

    public function test_customer_registration_links_prior_orders_by_phone(): void
    {
        $merchant = Merchant::first();

        Order::create([
            'order_reference_number' => 'PR-OLD9999',
            'merchant_id' => $merchant->id,
            'customer_user_id' => null,
            'customer_name' => 'Guest Buyer',
            'customer_phone' => '03006665544',
            'delivery_address' => 'Old Address',
            'target_city_id' => 1,
            'cod_amount' => 1200,
            'delivery_charge' => 400,
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'order_status' => 'created',
            'merchant_prep_status' => 'created',
        ]);

        $this->postJson('/api/v1/auth/register/customer', [
            'name' => 'Returning Customer',
            'email' => 'returning@velo.pk',
            'phone' => '03006665544',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();

        $user = User::where('email', 'returning@velo.pk')->first();
        $this->assertDatabaseHas('orders', [
            'order_reference_number' => 'PR-OLD9999',
            'customer_user_id' => $user->id,
        ]);
    }
}
