<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use App\Support\PhoneNormalizer;
use Illuminate\Database\Eloquent\Builder;

class CustomerOrderLinkService
{
    public static function ordersForUserQuery(User $user): Builder
    {
        $phone = PhoneNormalizer::normalize($user->phone);
        $variants = PhoneNormalizer::variants($user->phone);

        return Order::query()->where(function ($q) use ($user, $variants) {
            $q->where('customer_user_id', $user->id);
            if ($variants !== []) {
                $q->orWhereIn('customer_phone', $variants);
            }
        });
    }

    public static function linkOrdersToUser(User $user): int
    {
        $variants = PhoneNormalizer::variants($user->phone);
        if ($variants === []) {
            return 0;
        }

        return Order::query()
            ->whereIn('customer_phone', $variants)
            ->where(function ($q) use ($user) {
                $q->whereNull('customer_user_id')
                    ->orWhere('customer_user_id', $user->id);
            })
            ->update(['customer_user_id' => $user->id]);
    }

    public static function phoneAlreadyRegistered(?string $phone, ?int $ignoreUserId = null): bool
    {
        $variants = PhoneNormalizer::variants($phone);
        if ($variants === []) {
            return false;
        }

        $query = User::query()->whereIn('phone', $variants);
        if ($ignoreUserId) {
            $query->where('id', '!=', $ignoreUserId);
        }

        return $query->exists();
    }
}
