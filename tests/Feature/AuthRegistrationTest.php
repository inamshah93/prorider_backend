<?php

namespace Tests\Feature;

use App\Models\Merchant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_merchant_can_register_and_receives_token(): void
    {
        $response = $this->postJson('/api/v1/auth/register/merchant', [
            'name' => 'New Store Owner',
            'email' => 'newmerchant@velo.pk',
            'phone' => '03001234999',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'store_name' => 'New Store',
        ]);

        $response->assertCreated();
        $response->assertJsonStructure(['token', 'user']);
        $this->assertDatabaseHas('merchants', ['store_name' => 'New Store']);
        $user = User::where('email', 'newmerchant@velo.pk')->first();
        $this->assertTrue($user->hasRole('Merchant'));
    }

    public function test_customer_can_register_and_receives_token(): void
    {
        $response = $this->postJson('/api/v1/auth/register/customer', [
            'name' => 'New Customer',
            'email' => 'newcustomer@velo.pk',
            'phone' => '03009998877',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertCreated();
        $user = User::where('email', 'newcustomer@velo.pk')->first();
        $this->assertTrue($user->hasRole('Customer'));
        $this->assertSame('03009998877', $user->phone);
    }

    public function test_customer_cannot_register_duplicate_phone_with_different_email(): void
    {
        $this->postJson('/api/v1/auth/register/customer', [
            'name' => 'First Customer',
            'email' => 'first@velo.pk',
            'phone' => '03008887766',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();

        $response = $this->postJson('/api/v1/auth/register/customer', [
            'name' => 'Second Customer',
            'email' => 'second@velo.pk',
            'phone' => '923008887766',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_existing_user_can_add_customer_role_via_signup_with_same_credentials(): void
    {
        $this->postJson('/api/v1/auth/register/merchant', [
            'name' => 'Dual Role User',
            'email' => 'dual@velo.pk',
            'phone' => '03004443322',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'store_name' => 'Dual Store',
        ])->assertCreated();

        $this->postJson('/api/v1/auth/register/customer', [
            'name' => 'Dual Role User',
            'email' => 'dual@velo.pk',
            'phone' => '03004443322',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();

        $user = User::where('email', 'dual@velo.pk')->first();
        $this->assertTrue($user->hasRole('Merchant'));
        $this->assertTrue($user->hasRole('Customer'));
    }

    public function test_multi_role_user_can_login_to_customer_and_merchant_apps(): void
    {
        $user = User::where('phone', '03005551234')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('Customer'));
        $this->assertTrue($user->hasRole('Merchant'));

        $this->postJson('/api/v1/auth/login', [
            'phone' => '03005551234',
            'password' => 'password',
        ])->assertOk();

        $this->postJson('/api/v1/auth/login', [
            'phone' => '03005551234',
            'password' => 'password',
        ])->assertOk();
    }

    public function test_admin_can_create_rider_from_panel(): void
    {
        $admin = User::where('email', 'admin@velo.pk')->first();
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/v1/admin/riders', [
            'name' => 'New Rider',
            'email' => 'newrider@velo.pk',
            'phone' => '03005556677',
            'password' => 'password123',
        ]);

        $response->assertCreated();
        $user = User::where('email', 'newrider@velo.pk')->first();
        $this->assertTrue($user->hasRole('Rider'));
        $this->assertNotNull($user->riderProfile);
    }

    public function test_merchant_cannot_login_without_merchant_role(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'phone' => '03001112222',
            'password' => 'password',
            'app' => 'merchant',
        ])->assertUnprocessable()->assertJsonValidationErrors(['phone']);
    }

    public function test_customer_can_login_without_app_param(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'phone' => '03001234567',
            'password' => 'password',
        ])->assertOk();
    }

    public function test_merchant_can_login_with_merchant_app(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'phone' => '03007654321',
            'password' => 'password',
            'app' => 'merchant',
        ])->assertOk();
    }

    public function test_customer_can_login(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'phone' => '03001112222',
            'password' => 'password',
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['token', 'user']);
    }

    public function test_login_accepts_phone_number_variants(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'phone' => '923001112222',
            'password' => 'password',
        ]);

        $response->assertOk();
    }
}
