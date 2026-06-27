<?php

namespace Database\Seeders;

use App\Enums\UserStatus;
use App\Models\Merchant;
use App\Models\RiderProfile;
use App\Models\User;
use App\Models\City;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoUsersSeeder extends Seeder
{
    public function run(): void
    {
        $superAdmin = User::firstOrCreate(
            ['email' => 'admin@velo.pk'],
            [
                'name' => 'Super Admin',
                'phone' => '03001234567',
                'password' => Hash::make('password'),
                'status' => UserStatus::Active,
            ],
        );
        $superAdmin->assignRole('SuperAdmin');

        $merchantUser = User::firstOrCreate(
            ['email' => 'merchant@velo.pk'],
            [
                'name' => 'Kumar Stores',
                'phone' => '03007654321',
                'password' => Hash::make('password'),
                'status' => UserStatus::Active,
            ],
        );
        $merchantUser->assignRole('Merchant');

        Merchant::firstOrCreate(
            ['user_id' => $merchantUser->id],
            [
                'store_name' => 'Kumar Stores',
                'manual_saved_items' => [
                    ['sku' => 'tea-1kg', 'name' => 'Premium Tea 1kg', 'weight' => 1.0, 'price' => 850],
                    ['sku' => 'rice-5kg', 'name' => 'Basmati Rice 5kg', 'weight' => 5.0, 'price' => 2200],
                    ['sku' => 'oil-1l', 'name' => 'Cooking Oil 1L', 'weight' => 1.0, 'price' => 650],
                ],
            ],
        );

        $riderUser = User::firstOrCreate(
            ['email' => 'rider@velo.pk'],
            [
                'name' => 'Ali Rider',
                'phone' => '03009876543',
                'password' => Hash::make('password'),
                'status' => UserStatus::Active,
            ],
        );
        $riderUser->assignRole('Rider');

        $lahore = City::where('name', 'Lahore')->first();

        RiderProfile::firstOrCreate(
            ['user_id' => $riderUser->id],
            [
                'is_online' => true,
                'documents_verified' => true,
                'assigned_city_id' => $lahore?->id,
                'current_lat' => 31.5204,
                'current_lng' => 74.3587,
            ],
        );

        $customer = User::firstOrCreate(
            ['email' => 'customer@velo.pk'],
            [
                'name' => 'Test Customer',
                'phone' => '03001112222',
                'password' => Hash::make('password'),
                'status' => UserStatus::Active,
            ],
        );
        $customer->assignRole('Customer');
    }
}
