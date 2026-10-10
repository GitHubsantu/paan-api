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
use App\Http\Controllers\Api\V1\Customer\OrderController;
use App\Http\Controllers\Api\V1\Customer\ProductController;
use App\Http\Controllers\Api\V1\Customer\ShopController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // ---- Public auth ----
    Route::prefix('auth')->group(function () {
        Route::post('otp/send', [AuthController::class, 'sendOtp'])->middleware('throttle:otp');
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
        Route::post('orders', [OrderController::class, 'store'])->middleware('throttle:orders');
        Route::get('orders', [OrderController::class, 'index']);
        Route::get('orders/{order}', [OrderController::class, 'show'])->whereNumber('order');
        Route::get('orders/{order}/status', [OrderController::class, 'status'])->whereNumber('order');
    });

    // ---- Admin (Phase 11) ----
    Route::prefix('admin')->middleware(['auth:sanctum', 'active', 'admin'])->group(function () {
        Route::get('dashboard', [DashboardController::class, 'show']);
        Route::get('orders', [AdminOrderController::class, 'index']);
        Route::get('orders/{order}', [AdminOrderController::class, 'show'])->whereNumber('order');
        Route::patch('orders/{order}/status', [AdminOrderController::class, 'updateStatus'])->whereNumber('order');
        Route::post('orders/{order}/cancel', [AdminOrderController::class, 'cancel'])->whereNumber('order');
        Route::get('customers', [CustomerController::class, 'index']);
        Route::get('settings', [SettingController::class, 'index']);
        Route::put('settings', [SettingController::class, 'update']);
        Route::get('categories', [AdminCategoryController::class, 'index']);
        Route::post('categories', [AdminCategoryController::class, 'store']);
        Route::put('categories/{category}', [AdminCategoryController::class, 'update']);
        Route::delete('categories/{category}', [AdminCategoryController::class, 'destroy']);
        Route::get('addons', [AdminAddonController::class, 'index']);
        Route::post('addons', [AdminAddonController::class, 'store']);
        Route::put('addons/{addon}', [AdminAddonController::class, 'update']);
        Route::delete('addons/{addon}', [AdminAddonController::class, 'destroy']);
        Route::get('products', [AdminProductController::class, 'index']);
        Route::post('products', [AdminProductController::class, 'store']);
        Route::put('products/{product}', [AdminProductController::class, 'update']);
        Route::delete('products/{product}', [AdminProductController::class, 'destroy']);
    });
});
