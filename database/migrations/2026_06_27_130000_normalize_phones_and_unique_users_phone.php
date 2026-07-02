<?php

use App\Models\Order;
use App\Models\User;
use App\Support\PhoneNormalizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Order::query()->each(function (Order $order) {
            $normalized = PhoneNormalizer::normalize($order->customer_phone);
            if ($normalized && $normalized !== $order->customer_phone) {
                $order->update(['customer_phone' => $normalized]);
            }
        });

        $users = User::query()->whereNotNull('phone')->orderBy('id')->get();
        $seen = [];

        foreach ($users as $user) {
            $normalized = PhoneNormalizer::normalize($user->phone);
            if (! $normalized) {
                continue;
            }

            if (isset($seen[$normalized])) {
                $keeper = $seen[$normalized];
                $isCustomer = fn (User $u) => $u->roles()->where('name', 'Customer')->exists();
                if ($isCustomer($user) && $isCustomer($keeper)) {
                    Order::where('customer_user_id', $user->id)->update(['customer_user_id' => $keeper->id]);
                    $user->tokens()->delete();
                    $user->roles()->detach();
                    $user->delete();
                } else {
                    $user->update(['phone' => $normalized.'-'.$user->id]);
                }

                continue;
            }

            $user->update(['phone' => $normalized]);
            $seen[$normalized] = $user->fresh();
        }

        Schema::table('users', function (Blueprint $table) {
            $table->unique('phone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['phone']);
        });
    }
};
