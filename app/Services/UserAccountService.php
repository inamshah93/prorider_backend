<?php

namespace App\Services;

use App\Models\Merchant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UserAccountService
{
    /** @param  list<string>  $roleNames */
    public static function syncRoles(User $user, array $roleNames, ?string $storeName = null): User
    {
        $user->syncRoles($roleNames);

        if (in_array('Merchant', $roleNames, true)) {
            static::ensureMerchantProfile($user, $storeName);
        }

        if (in_array('Customer', $roleNames, true)) {
            CustomerOrderLinkService::linkOrdersToUser($user);
        }

        return $user->fresh()->load('roles', 'merchant', 'riderProfile');
    }

    public static function assignRole(User $user, string $roleName, ?string $storeName = null): User
    {
        if (! $user->hasRole($roleName)) {
            $user->assignRole($roleName);
        }

        if ($roleName === 'Merchant') {
            static::ensureMerchantProfile($user, $storeName);
        }

        if ($roleName === 'Customer') {
            CustomerOrderLinkService::linkOrdersToUser($user);
        }

        return $user->fresh()->load('roles', 'merchant', 'riderProfile');
    }

    public static function ensureMerchantProfile(User $user, ?string $storeName = null): Merchant
    {
        if ($user->merchant) {
            return $user->merchant;
        }

        return Merchant::create([
            'user_id' => $user->id,
            'store_name' => $storeName ?: ($user->name.' Store'),
        ]);
    }

    /**
     * Add an app role to an existing account (same phone + password + email).
     *
     * @param  array{name: string, email: string, password: string, store_name?: string}  $data
     */
    public static function enrollExistingUser(User $user, array $data, string $roleName, ?string $storeName = null): User
    {
        if (! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'phone' => ['This phone is already registered. Sign in with your existing password, or enter the same password here to add access.'],
            ]);
        }

        if (strtolower(trim($data['email'])) !== strtolower(trim($user->email))) {
            throw ValidationException::withMessages([
                'email' => ["Email must match your existing account ({$user->email})."],
            ]);
        }

        if ($user->hasRole($roleName)) {
            throw ValidationException::withMessages([
                'phone' => ['This account already has access. Please sign in.'],
            ]);
        }

        return static::assignRole($user, $roleName, $storeName ?? ($data['store_name'] ?? null));
    }
}
