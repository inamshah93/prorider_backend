<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Validation\ValidationException;

class AppRole
{
    public const MERCHANT = 'merchant';

    public const RIDER = 'rider';

    public static function assertForApp(User $user, string $app): void
    {
        match ($app) {
            self::MERCHANT => self::assertMerchant($user),
            self::RIDER => self::assertRider($user),
            default => null,
        };
    }

    public static function assertMerchant(User $user): void
    {
        if (! $user->hasRole('Merchant')) {
            throw ValidationException::withMessages([
                'phone' => ['This account is not a merchant account.'],
            ]);
        }
    }

    public static function assertRider(User $user): void
    {
        if (! $user->hasRole('Rider')) {
            throw ValidationException::withMessages([
                'phone' => ['This account is not a rider account.'],
            ]);
        }
    }
}
