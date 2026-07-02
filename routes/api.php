<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\RegisterController;
use App\Http\Controllers\Api\V1\Admin\CityController as AdminCityController;
use App\Http\Controllers\Api\V1\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Api\V1\Admin\MerchantController as AdminMerchantController;
use App\Http\Controllers\Api\V1\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Api\V1\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Api\V1\Admin\RiderController as AdminRiderController;
use App\Http\Controllers\Api\V1\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Api\V1\Admin\StaffController as AdminStaffController;
use App\Http\Controllers\Api\V1\Admin\RoleController as AdminRoleController;
use App\Http\Controllers\Api\V1\Admin\UserRoleController as AdminUserRoleController;
use App\Http\Controllers\Api\V1\Admin\ReportsController as AdminReportsController;
use App\Http\Controllers\Api\V1\Admin\ActivityLogController as AdminActivityLogController;
use App\Http\Controllers\Api\V1\Admin\CustomerController as AdminCustomerController;
use App\Http\Controllers\Api\V1\Customer\OrderController as CustomerOrderController;
use App\Http\Controllers\Api\V1\Customer\ProfileController as CustomerProfileController;
use App\Http\Controllers\Api\V1\Customer\TrackingController;
use App\Http\Controllers\Api\V1\Merchant\CatalogController;
use App\Http\Controllers\Api\V1\Merchant\DashboardController as MerchantDashboardController;
use App\Http\Controllers\Api\V1\Merchant\OrderController as MerchantOrderController;
use App\Http\Controllers\Api\V1\Rider\AssignmentController;
use App\Http\Controllers\Api\V1\Rider\OrderHistoryController;
use App\Http\Controllers\Api\V1\Rider\OrderController as RiderOrderController;
use App\Http\Controllers\Api\V1\Rider\ProfileController as RiderProfileController;
use App\Http\Controllers\Api\V1\Rider\WalletController as RiderWalletController;
use App\Http\Controllers\Api\V1\Webhook\BankWebhookController;
use App\Http\Controllers\Api\V1\Webhook\ShopifyWebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login']);
    Route::post('auth/register/merchant', [RegisterController::class, 'merchant']);
    Route::post('auth/register/customer', [RegisterController::class, 'customer']);

    Route::get('customer/track/{orderReference}', [TrackingController::class, 'track']);

    Route::post('webhooks/shopify/orders-create', [ShopifyWebhookController::class, 'ordersCreate']);
    Route::post('webhooks/bank/payment-success', [BankWebhookController::class, 'paymentSuccess']);

    Route::middleware(['auth:sanctum', 'sanctum.guard'])->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::put('auth/device-token', [AuthController::class, 'updateDeviceToken']);

        Route::prefix('admin')->group(function () {
            Route::get('dashboard', [AdminDashboardController::class, 'index']);

            Route::middleware('permission:manage-cities')->group(function () {
                Route::get('cities', [AdminCityController::class, 'index']);
                Route::post('cities', [AdminCityController::class, 'store']);
                Route::put('cities/{city}', [AdminCityController::class, 'update']);
                Route::get('city-aliases', [AdminCityController::class, 'aliases']);
                Route::post('city-aliases', [AdminCityController::class, 'storeAlias']);
                Route::get('settings/pricing', [AdminSettingsController::class, 'pricing']);
                Route::put('settings/pricing', [AdminSettingsController::class, 'updatePricing']);
            });

            Route::middleware('permission:manage-vendors')->group(function () {
                Route::get('merchants', [AdminMerchantController::class, 'index']);
                Route::post('merchants', [AdminMerchantController::class, 'store']);
                Route::get('merchants/{merchant}', [AdminMerchantController::class, 'show']);
                Route::put('merchants/{merchant}/delivery-charge', [AdminMerchantController::class, 'updateDeliveryCharge']);
                Route::post('merchants/{merchant}/shopify', [AdminMerchantController::class, 'connectShopify']);
                Route::get('merchants/{merchant}/orders', [AdminMerchantController::class, 'orders']);
                Route::get('merchants/{merchant}/orders/stats', [AdminMerchantController::class, 'orderStats']);
                Route::get('customers', [AdminCustomerController::class, 'index']);
            });

            Route::middleware('permission:manage-riders')->group(function () {
                Route::get('riders', [AdminRiderController::class, 'index']);
                Route::post('riders', [AdminRiderController::class, 'store']);
                Route::post('riders/{rider}/approve', [AdminRiderController::class, 'approveDocuments']);
                Route::put('riders/{rider}/online-status', [AdminRiderController::class, 'updateOnlineStatus']);
                Route::put('riders/{rider}/user', [AdminRiderController::class, 'updateRiderUser']);
                Route::put('riders/{rider}/city', [AdminRiderController::class, 'assignCity']);
                Route::put('riders/{rider}/commission-rate', [AdminRiderController::class, 'updateCommissionRate']);
                Route::get('riders/{rider}/wallet', [AdminRiderController::class, 'wallet']);
                Route::get('riders/{rider}/settlements', [AdminRiderController::class, 'settlements']);
                Route::post('riders/{rider}/settlements', [AdminRiderController::class, 'storeSettlement']);
                Route::get('riders/{rider}/orders', [AdminRiderController::class, 'orders']);
                Route::get('riders/{rider}/orders/stats', [AdminRiderController::class, 'orderStats']);
            });

            Route::get('activity-logs', [AdminActivityLogController::class, 'index']);

            Route::get('orders', [AdminOrderController::class, 'index']);
            Route::get('orders/{order}', [AdminOrderController::class, 'show']);
            Route::post('orders/{order}/assign-rider', [AdminOrderController::class, 'assignRider'])
                ->middleware('permission:assign-riders');
            Route::post('orders/{order}/cancel', [AdminOrderController::class, 'cancel']);

            Route::middleware('permission:manage-staff')->group(function () {
                Route::get('staff', [AdminStaffController::class, 'index']);
                Route::post('staff', [AdminStaffController::class, 'store']);
                Route::put('staff/{user}/permissions', [AdminStaffController::class, 'updatePermissions']);
                Route::get('permissions', [AdminStaffController::class, 'permissions']);
                Route::get('roles', [AdminRoleController::class, 'index']);
                Route::post('roles', [AdminRoleController::class, 'store']);
                Route::get('users/search', [AdminUserRoleController::class, 'search']);
                Route::put('users/{user}/roles', [AdminUserRoleController::class, 'updateRoles']);
            });

            Route::middleware('permission:verify-payments')->group(function () {
                Route::get('payments/pending', [AdminPaymentController::class, 'pending']);
                Route::post('payments/override', [AdminPaymentController::class, 'override']);
            });

            Route::middleware('permission:view-analytics')->group(function () {
                Route::get('reports/day-end', [AdminReportsController::class, 'dayEnd']);
                Route::get('reports/riders', [AdminReportsController::class, 'riders']);
            });
        });

        Route::prefix('merchant')->group(function () {
            Route::get('dashboard', [MerchantDashboardController::class, 'index']);
            Route::get('catalog', [CatalogController::class, 'show']);
            Route::put('catalog', [CatalogController::class, 'update']);
            Route::get('orders', [MerchantOrderController::class, 'index']);
            Route::post('orders', [MerchantOrderController::class, 'store']);
            Route::get('orders/{order}', [MerchantOrderController::class, 'show']);
            Route::post('orders/{order}/generate-label', [MerchantOrderController::class, 'generateLabel']);
            Route::post('orders/{order}/mark-packed', [MerchantOrderController::class, 'markPacked']);
            Route::post('orders/{order}/ready-to-ship', [MerchantOrderController::class, 'readyToShip']);
        });

        Route::prefix('rider')->group(function () {
            Route::get('profile', [RiderProfileController::class, 'show']);
            Route::put('location', [RiderProfileController::class, 'updateLocation']);
            Route::put('online-status', [RiderProfileController::class, 'updateOnlineStatus']);
            Route::get('earnings', [RiderProfileController::class, 'earnings']);
            Route::get('stats', [OrderHistoryController::class, 'stats']);
            Route::get('orders', [OrderHistoryController::class, 'index']);
            Route::get('assignments', [AssignmentController::class, 'index']);
            Route::post('orders/batch-picked-up', [RiderOrderController::class, 'batchPickedUp']);
            Route::post('orders/{order}/picked-up', [RiderOrderController::class, 'pickedUp']);
            Route::get('wallet', [RiderWalletController::class, 'show']);
            Route::get('settlements', [RiderWalletController::class, 'settlements']);
            Route::post('orders/{order}/delivered', [RiderOrderController::class, 'delivered']);
            Route::get('orders/{order}/checkout', [RiderOrderController::class, 'checkout']);
        });

        Route::prefix('customer')->group(function () {
            Route::get('profile', [CustomerProfileController::class, 'show']);
            Route::get('orders', [CustomerOrderController::class, 'index']);
            Route::get('orders/{order}', [CustomerOrderController::class, 'show']);
        });
    });
});
