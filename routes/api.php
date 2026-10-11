<?php

use App\Http\Controllers\Api\V1\Admin\AddonController as AdminAddonController;
use App\Http\Controllers\Api\V1\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Api\V1\Admin\CustomerController;
use App\Http\Controllers\Api\V1\Admin\DashboardController;
use App\Http\Controllers\Api\V1\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Api\V1\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Api\V1\Admin\SettingController;
use App\Http\Controllers\Api\V1\Auth\AdminAuthController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Customer\CategoryController;
use App\Http\Controllers\Api\V1\Customer\DeviceTokenController;
use App\Http\Controllers\Api\V1\Customer\NotificationController;
use App\Http\Controllers\Api\V1\Customer\OrderController;
use App\Http\Controllers\Api\V1\Customer\ProductController;
use App\Http\Controllers\Api\V1\Customer\ProfileController;
use App\Http\Controllers\Api\V1\Customer\ShopController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // ---- Public auth ----
    Route::prefix('auth')->group(function () {
        Route::post('otp/send', [AuthController::class, 'sendOtp'])->middleware('throttle:otp');
        Route::post('otp/verify', [AuthController::class, 'verifyOtp'])->middleware('throttle:otp-verify');
        Route::post('register', [AuthController::class, 'register'])->middleware('throttle:auth');
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:auth');
    });
    Route::post('admin/login', [AdminAuthController::class, 'login'])->middleware('throttle:auth');
    // ---- Public catalog ----
    Route::middleware('throttle:60,1')->group(function () {
        Route::get('shop', [ShopController::class, 'show']);
        Route::get('categories', [CategoryController::class, 'index']);
        Route::get('categories/{category}/products', [CategoryController::class, 'products']);
        Route::get('products', [ProductController::class, 'index']);
        Route::get('products/{product}', [ProductController::class, 'show']);
    });

    // ---- Authenticated customer/any user ----
    Route::middleware(['auth:sanctum', 'active'])->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::get('profile', [ProfileController::class, 'show']);
        Route::put('profile', [ProfileController::class, 'update']);
        Route::post('orders', [OrderController::class, 'store'])->middleware('throttle:orders');
        Route::get('orders', [OrderController::class, 'index']);
        Route::get('orders/{order}', [OrderController::class, 'show'])->whereNumber('order');
        Route::get('orders/{order}/status', [OrderController::class, 'status'])->whereNumber('order');
        Route::get('notifications', [NotificationController::class, 'index']);
        Route::post('notifications/{id}/read', [NotificationController::class, 'read']);
        Route::post('notifications/read-all', [NotificationController::class, 'readAll']);
        Route::post('device-tokens', [DeviceTokenController::class, 'store'])->middleware('throttle:device-tokens');
        Route::delete('device-tokens/{token}', [DeviceTokenController::class, 'destroy']);
    });

    // ---- Admin (Phase 11) ----
    Route::prefix('admin')->middleware(['auth:sanctum', 'active', 'admin'])->group(function () {
        Route::get('dashboard', [DashboardController::class, 'show']);
        Route::get('orders', [AdminOrderController::class, 'index']);
        Route::get('orders/{order}', [AdminOrderController::class, 'show'])->whereNumber('order');
        Route::patch('orders/{order}/status', [AdminOrderController::class, 'updateStatus'])->whereNumber('order')->middleware('throttle:admin-writes');
        Route::post('orders/{order}/cancel', [AdminOrderController::class, 'cancel'])->whereNumber('order')->middleware('throttle:admin-writes');
        Route::get('customers', [CustomerController::class, 'index']);
        Route::post('customers', [CustomerController::class, 'store'])->middleware('throttle:admin-writes');
        Route::get('customers/{customer}', [CustomerController::class, 'show'])->whereNumber('customer');
        Route::put('customers/{customer}', [CustomerController::class, 'update'])->whereNumber('customer')->middleware('throttle:admin-writes');
        Route::patch('customers/{customer}/block', [CustomerController::class, 'block'])->whereNumber('customer')->middleware('throttle:admin-writes');
        Route::delete('customers/{customer}', [CustomerController::class, 'destroy'])->whereNumber('customer')->middleware('throttle:admin-writes');
        Route::get('settings', [SettingController::class, 'index']);
        Route::put('settings', [SettingController::class, 'update'])->middleware('throttle:admin-writes');
        Route::get('categories', [AdminCategoryController::class, 'index']);
        Route::post('categories', [AdminCategoryController::class, 'store'])->middleware('throttle:admin-writes');
        Route::post('categories/{category}', [AdminCategoryController::class, 'update'])->middleware('throttle:admin-writes');
        Route::put('categories/{category}', [AdminCategoryController::class, 'update'])->middleware('throttle:admin-writes');
        Route::delete('categories/{category}', [AdminCategoryController::class, 'destroy'])->middleware('throttle:admin-writes');
        Route::get('addons', [AdminAddonController::class, 'index']);
        Route::post('addons', [AdminAddonController::class, 'store'])->middleware('throttle:admin-writes');
        Route::put('addons/{addon}', [AdminAddonController::class, 'update'])->middleware('throttle:admin-writes');
        Route::delete('addons/{addon}', [AdminAddonController::class, 'destroy'])->middleware('throttle:admin-writes');
        Route::get('products', [AdminProductController::class, 'index']);
        Route::get('products/{product}', [AdminProductController::class, 'show'])->whereNumber('product');
        Route::post('products', [AdminProductController::class, 'store'])->middleware('throttle:admin-writes');
        Route::post('products/{product}', [AdminProductController::class, 'update'])->whereNumber('product')->middleware('throttle:admin-writes');
        Route::put('products/{product}', [AdminProductController::class, 'update'])->middleware('throttle:admin-writes');
        Route::patch('products/{product}/availability', [AdminProductController::class, 'updateAvailability'])->middleware('throttle:admin-writes');
        Route::delete('products/{product}', [AdminProductController::class, 'destroy'])->middleware('throttle:admin-writes');
    });
});
