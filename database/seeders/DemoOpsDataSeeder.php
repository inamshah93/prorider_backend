<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\City;
use App\Models\DayEndSnapshot;
use App\Models\FinancialLedger;
use App\Models\Merchant;
use App\Models\Order;
use App\Models\RiderProfile;
use App\Models\RiderSettlement;
use App\Models\User;
use App\Services\DayEndAccountingService;
use App\Services\OrderStateMachine;
use App\Services\PaymentOverrideService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DemoOpsDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->info('Seeding demo ops data (vendors, riders, orders, ledgers, settlements)…');

        $admin = User::where('email', 'admin@velo.pk')->firstOrFail();
        $customer = User::where('email', 'customer@velo.pk')->firstOrFail();

        $lahore = City::where('name', 'Lahore')->first() ?? City::query()->firstOrFail();
        $karachi = City::where('name', 'Karachi')->first() ?? City::query()->firstOrFail();
        $islamabad = City::where('name', 'Islamabad')->first() ?? City::query()->firstOrFail();

        $sm = app(OrderStateMachine::class);
        $dayEnd = app(DayEndAccountingService::class);
        $overrideSvc = app(PaymentOverrideService::class);

        DB::transaction(function () use (
            $admin,
            $customer,
            $lahore,
            $karachi,
            $islamabad,
            $sm,
            $dayEnd,
            $overrideSvc,
        ) {
            $now = now();

            // Clean previous demo records to keep seeder repeatable.
            $demoOrders = Order::query()
                ->where('order_reference_number', 'like', 'PR-DEMO%')
                ->pluck('id');

            if ($demoOrders->isNotEmpty()) {
                FinancialLedger::query()->whereIn('order_id', $demoOrders)->delete();
                Order::query()->whereIn('id', $demoOrders)->delete();
            }

            FinancialLedger::query()->where('reference', 'like', 'SETTLE-DEMO-%')->delete();
            RiderSettlement::query()->where('notes', 'like', 'Demo settlement%')->delete();

            // Seed vendors (10).
            $vendors = collect();
            for ($i = 1; $i <= 10; $i++) {
                $email = "vendor{$i}@velo.pk";
                $phone = '0300700'.str_pad((string) $i, 4, '0', STR_PAD_LEFT);

                $user = User::firstOrCreate(
                    ['email' => $email],
                    [
                        'name' => "Vendor {$i}",
                        'phone' => $phone,
                        'password' => bcrypt('password'),
                        'status' => 'active',
                    ],
                );
                $user->assignRole('Merchant');

                $merchant = Merchant::firstOrCreate(
                    ['user_id' => $user->id],
                    [
                        'store_name' => "Vendor Store {$i}",
                        'delivery_charge' => 350 + ($i * 10),
                    ],
                );
                $vendors->push($merchant);
            }

            // Seed riders (6).
            $riders = collect();
            $cities = collect([$lahore, $karachi, $islamabad]);
            for ($i = 1; $i <= 6; $i++) {
                $email = "rider{$i}@velo.pk";
                $phone = '0300900'.str_pad((string) $i, 4, '0', STR_PAD_LEFT);

                $user = User::firstOrCreate(
                    ['email' => $email],
                    [
                        'name' => "Rider {$i}",
                        'phone' => $phone,
                        'password' => bcrypt('password'),
                        'status' => 'active',
                    ],
                );
                $user->assignRole('Rider');

                $city = $cities[($i - 1) % $cities->count()];
                RiderProfile::updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'is_online' => $i % 2 === 0,
                        'documents_verified' => true,
                        'assigned_city_id' => $city->id,
                        'current_lat' => 31.5204,
                        'current_lng' => 74.3587,
                        'cash_in_hand' => 0,
                        'commission_rate' => null,
                    ],
                );

                $riders->push($user);
            }

            // Create ~100 orders with mixed statuses.
            $makeRef = fn () => 'PR-DEMO'.strtoupper(Str::random(8));
            $addresses = [
                'Model Town, Lahore',
                'Johar Town, Lahore',
                'Gulshan, Karachi',
                'Clifton, Karachi',
                'F-10, Islamabad',
                'G-11, Islamabad',
            ];
            $itemPool = [
                [['name' => 'Premium Tea 1kg', 'quantity' => 1, 'price' => 850]],
                [['name' => 'Basmati Rice 5kg', 'quantity' => 1, 'price' => 2200]],
                [['name' => 'Cooking Oil 1L', 'quantity' => 2, 'price' => 650]],
                [['name' => 'Fashion Items', 'quantity' => 3, 'price' => 1200]],
                [['name' => 'Electronics Bundle', 'quantity' => 1, 'price' => 15000]],
            ];
            $cityPool = [$lahore, $karachi, $islamabad];

            $deliveredCount = 0;
            $pendingCodCount = 0;
            $bankPendingCount = 0;
            $cancelledCount = 0;

            for ($i = 1; $i <= 100; $i++) {
                $merchant = $vendors[($i - 1) % $vendors->count()];
                $rider = $riders[($i - 1) % $riders->count()];
                $city = $cityPool[($i - 1) % count($cityPool)];
                $items = $itemPool[($i - 1) % count($itemPool)];
                $cod = match (true) {
                    $i % 9 === 0 => 15000,
                    $i % 5 === 0 => 3600,
                    default => 2200,
                };

                $createdAt = $now->copy()->subDays(random_int(0, 13))->subHours(random_int(0, 23));
                $statusRoll = $i % 10; // deterministic distribution

                $paymentMethod = PaymentMethod::Cod;
                $paymentStatus = PaymentStatus::Pending;
                $orderStatus = OrderStatus::Created;
                $merchantPrep = ['created', 'label_generated', 'packed'][($i - 1) % 3];
                $assignRider = true;

                if ($statusRoll <= 4) {
                    // Delivered COD (50%)
                    $orderStatus = OrderStatus::Created;
                    $paymentMethod = PaymentMethod::Cod;
                    $paymentStatus = PaymentStatus::Paid;
                    $deliveredCount++;
                } elseif ($statusRoll <= 6) {
                    // Pending COD (20%)
                    $orderStatus = OrderStatus::Dispatched;
                    $paymentMethod = PaymentMethod::Cod;
                    $paymentStatus = PaymentStatus::Pending;
                    $pendingCodCount++;
                } elseif ($statusRoll === 7) {
                    // Bank transfer pending (10%)
                    $orderStatus = OrderStatus::ReadyToShip;
                    $paymentMethod = PaymentMethod::BankTransfer;
                    $paymentStatus = PaymentStatus::Pending;
                    $assignRider = false;
                    $bankPendingCount++;
                } elseif ($statusRoll === 8) {
                    // Cancelled (10%)
                    $orderStatus = OrderStatus::Cancelled;
                    $paymentMethod = PaymentMethod::Cod;
                    $paymentStatus = PaymentStatus::Failed;
                    $assignRider = false;
                    $cancelledCount++;
                } else {
                    // Live / picked-up (10%)
                    $orderStatus = OrderStatus::PickedUp;
                    $paymentMethod = PaymentMethod::Cod;
                    $paymentStatus = PaymentStatus::Pending;
                }

                $order = Order::create([
                    'order_reference_number' => $makeRef(),
                    'merchant_id' => $merchant->id,
                    'rider_id' => $assignRider ? $rider->id : null,
                    'customer_user_id' => $customer->id,
                    'customer_name' => $customer->name,
                    'customer_phone' => $customer->phone,
                    'delivery_address' => $addresses[($i - 1) % count($addresses)],
                    'target_city_id' => $city->id,
                    'parcel_weight' => (float) (random_int(50, 400) / 100),
                    'item_details' => $items,
                    'cod_amount' => $cod,
                    'delivery_charge' => (float) ($merchant->delivery_charge ?? 400),
                    'payment_method' => $paymentMethod,
                    'payment_status' => $paymentStatus,
                    'order_status' => $orderStatus,
                    'merchant_prep_status' => $merchantPrep,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt->copy()->addHours(random_int(1, 36)),
                ]);

                // If delivered, use transitions to populate events + ledgers.
                if ($deliveredCount > 0 && $statusRoll <= 4 && $order->rider_id) {
                    $sm->transition($order, OrderStatus::ReadyToShip, $admin);
                    $sm->transition($order, OrderStatus::Dispatched, $admin);
                    $sm->transition($order, OrderStatus::PickedUp, $rider);
                    $sm->transition($order, OrderStatus::Delivered, $rider);
                    $dayEnd->recordDeliveryLedger($order->fresh());
                }

                // For a few delivered orders, add manual override entry too (simulate verification).
                if ($statusRoll === 4 && $order->payment_method === PaymentMethod::Cod) {
                    $overrideSvc->override($order->fresh(), $admin, PaymentStatus::Paid, 'Demo: verified payment');
                }
            }

            // Seed some settlements across riders based on cash in hand.
            foreach ($riders as $idx => $r) {
                $profile = RiderProfile::where('user_id', $r->id)->first();
                if (! $profile) continue;
                $cashBefore = (float) $profile->cash_in_hand;
                $amount = min(700 + ($idx * 100), $cashBefore);
                if ($amount <= 0) continue;

                $profile->update(['cash_in_hand' => $cashBefore - $amount]);

                RiderSettlement::create([
                    'rider_id' => $r->id,
                    'admin_id' => $admin->id,
                    'amount' => $amount,
                    'cash_before' => $cashBefore,
                    'cash_after' => $cashBefore - $amount,
                    'proof_image_path' => 'rider-settlements/demo-proof.png',
                    'notes' => 'Demo settlement batch',
                    'created_at' => $now->copy()->subDays(1),
                    'updated_at' => $now->copy()->subDays(1),
                ]);

                FinancialLedger::create([
                    'rider_id' => $r->id,
                    'entry_type' => 'rider_settlement',
                    'amount' => $amount,
                    'reference' => 'SETTLE-DEMO-'.($idx + 1),
                    'notes' => 'Demo settlement batch',
                    'created_by' => $admin->id,
                    'created_at' => $now->copy()->subDays(1),
                    'updated_at' => $now->copy()->subDays(1),
                ]);
            }

            // Compile day-end snapshots for last 14 days so reports exist.
            for ($d = 1; $d <= 14; $d++) {
                $dayEnd->compile(now()->subDays($d));
            }

            DayEndSnapshot::updateOrCreate(
                ['snapshot_date' => now()->subDays(30)->toDateString()],
                [
                    'total_cod_collected' => 0,
                    'total_rider_cash' => RiderProfile::sum('cash_in_hand'),
                    'total_merchant_payables' => 0,
                    'platform_net_profit' => 0,
                    'orders_delivered' => 0,
                    'metadata' => ['seeded' => true],
                ],
            );
        });

        $this->command?->info('Demo ops data seeded.');
    }
}

