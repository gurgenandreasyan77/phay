<?php

use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\V1\AdditionalServiceController;
use App\Http\Controllers\Api\V1\Admin\AdditionalServiceController as AdminAdditionalServiceController;
use App\Http\Controllers\Api\V1\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Api\V1\Admin\CalculatorSettingController as AdminCalculatorSettingController;
use App\Http\Controllers\Api\V1\Admin\ContactChannelController as AdminContactChannelController;
use App\Http\Controllers\Api\V1\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Api\V1\Admin\FeatureFlagController as AdminFeatureFlagController;
use App\Http\Controllers\Api\V1\Admin\LeadController as AdminLeadController;
use App\Http\Controllers\Api\V1\Admin\ProductCategoryController as AdminProductCategoryController;
use App\Http\Controllers\Api\V1\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Api\V1\Admin\ServiceCategoryController as AdminServiceCategoryController;
use App\Http\Controllers\Api\V1\Admin\ServiceController as AdminServiceController;
use App\Http\Controllers\Api\V1\Admin\ServiceGalleryItemController as AdminServiceGalleryItemController;
use App\Http\Controllers\Api\V1\Admin\ServicePackageController as AdminServicePackageController;
use App\Http\Controllers\Api\V1\Admin\ServiceVideoController as AdminServiceVideoController;
use App\Http\Controllers\Api\V1\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Api\V1\Admin\SiteController as AdminSiteController;
use App\Http\Controllers\Api\V1\Admin\TaskPageController as AdminTaskPageController;
use App\Http\Controllers\Api\V1\Admin\TenantController as AdminTenantController;
use App\Http\Controllers\Api\V1\Admin\TenantUserController as AdminTenantUserController;
use App\Http\Controllers\Api\V1\Admin\UploadController as AdminUploadController;
use App\Http\Controllers\Api\V1\CalculatorSettingController;
use App\Http\Controllers\Api\V1\LeadController;
use App\Http\Controllers\Api\V1\PostController;
use App\Http\Controllers\Api\V1\ProductCategoryController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ServiceCategoryController;
use App\Http\Controllers\Api\V1\ServiceController;
use App\Http\Controllers\Api\V1\ServiceGalleryItemController;
use App\Http\Controllers\Api\V1\ServicePackageController;
use App\Http\Controllers\Api\V1\ServiceVideoController;
use App\Http\Controllers\Api\V1\SettingsController;
use App\Http\Controllers\Api\V1\TaskPageController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);

Route::prefix('v1')->group(function () {
    // Public API: the X-Site-Key header decides which business's content is
    // served (no header: the default business).
    Route::middleware('tenant.site')->group(function () {
        Route::get('/categories', [ServiceCategoryController::class, 'index']);
        Route::get('/services', [ServiceController::class, 'index']);
        Route::get('/services/{slug}', [ServiceController::class, 'show']);
        Route::get('/service-packages', [ServicePackageController::class, 'index']);
        Route::get('/service-packages/{slug}', [ServicePackageController::class, 'show']);
        Route::get('/service-gallery', [ServiceGalleryItemController::class, 'index']);
        Route::get('/service-videos', [ServiceVideoController::class, 'index']);
        Route::get('/additional-services', [AdditionalServiceController::class, 'index']);

        // Products, their categories and SEO task pages are exclusively the
        // "Market" section — unlike auto-care (a ServiceCategory shared with
        // other categories' endpoints), these routes serve nothing else, so the
        // whole group can be gated directly.
        Route::middleware('feature:market')->group(function () {
            Route::get('/products', [ProductController::class, 'index']);
            Route::get('/products/{categorySlug}/{productSlug}', [ProductController::class, 'show']);
            Route::get('/product-categories', [ProductCategoryController::class, 'index']);
            Route::get('/product-categories/{slug}', [ProductCategoryController::class, 'show']);
            Route::get('/task-pages', [TaskPageController::class, 'index']);
            Route::get('/task-pages/{slug}', [TaskPageController::class, 'show']);
        });

        Route::get('/calculator-settings', [CalculatorSettingController::class, 'show']);
        Route::get('/posts', [PostController::class, 'index']);
        Route::get('/posts/{post:slug}', [PostController::class, 'show']);
        Route::get('/settings', [SettingsController::class, 'show']);
        Route::post('/leads', [LeadController::class, 'store'])->middleware('throttle:leads');
    });

    Route::prefix('admin')->group(function () {
        Route::post('/login', [AdminAuthController::class, 'login'])->middleware('throttle:admin-login');

        // Admin API: the signed-in user's current business.
        Route::middleware(['auth:sanctum', 'tenant.admin'])->group(function () {
            Route::post('/logout', [AdminAuthController::class, 'logout']);
            Route::get('/me', [AdminAuthController::class, 'me']);
            Route::post('/switch-tenant', [AdminAuthController::class, 'switchTenant']);

            // Platform screens: a business owner gets 403 here.
            Route::middleware('super.admin')->group(function () {
                Route::get('/tenants', [AdminTenantController::class, 'index']);
                Route::post('/tenants', [AdminTenantController::class, 'store']);
                Route::get('/tenants/{tenant}/users', [AdminTenantUserController::class, 'index']);
                Route::post('/tenants/{tenant}/users', [AdminTenantUserController::class, 'store']);
                Route::patch('/tenants/{tenant}/users/{user}', [AdminTenantUserController::class, 'update']);
                Route::delete('/tenants/{tenant}/users/{user}', [AdminTenantUserController::class, 'destroy']);
                Route::get('/sites', [AdminSiteController::class, 'index']);
                Route::post('/sites', [AdminSiteController::class, 'store']);
                Route::patch('/sites/{site}', [AdminSiteController::class, 'update']);
                Route::post('/sites/{site}/rotate-key', [AdminSiteController::class, 'rotateKey']);
                Route::patch('/feature-flags/{key}', [AdminFeatureFlagController::class, 'update']);
            });

            Route::get('/dashboard', AdminDashboardController::class);
            Route::get('/leads', [AdminLeadController::class, 'index']);
            Route::post('/leads', [AdminLeadController::class, 'store']);
            Route::get('/leads/{lead}', [AdminLeadController::class, 'show']);
            Route::patch('/leads/{lead}', [AdminLeadController::class, 'update']);
            Route::get('/settings', [AdminSettingsController::class, 'show']);
            Route::put('/settings', [AdminSettingsController::class, 'update']);
            Route::get('/feature-flags', [AdminFeatureFlagController::class, 'index']);
            Route::post('/contact-channels', [AdminContactChannelController::class, 'store']);
            Route::patch('/contact-channels/{contactChannel}', [AdminContactChannelController::class, 'update']);
            Route::delete('/contact-channels/{contactChannel}', [AdminContactChannelController::class, 'destroy']);
            Route::get('/additional-services', [AdminAdditionalServiceController::class, 'index']);
            Route::post('/additional-services', [AdminAdditionalServiceController::class, 'store']);
            Route::patch('/additional-services/{additionalService}', [AdminAdditionalServiceController::class, 'update']);
            Route::delete('/additional-services/{additionalService}', [AdminAdditionalServiceController::class, 'destroy']);
            Route::get('/products', [AdminProductController::class, 'index']);
            Route::post('/products', [AdminProductController::class, 'store']);
            Route::patch('/products/{product}', [AdminProductController::class, 'update']);
            Route::delete('/products/{product}', [AdminProductController::class, 'destroy']);
            Route::get('/product-categories', [AdminProductCategoryController::class, 'index']);
            Route::post('/product-categories', [AdminProductCategoryController::class, 'store']);
            Route::patch('/product-categories/{productCategory}', [AdminProductCategoryController::class, 'update']);
            Route::delete('/product-categories/{productCategory}', [AdminProductCategoryController::class, 'destroy']);
            Route::get('/task-pages', [AdminTaskPageController::class, 'index']);
            Route::post('/task-pages', [AdminTaskPageController::class, 'store']);
            Route::patch('/task-pages/{taskPage}', [AdminTaskPageController::class, 'update']);
            Route::delete('/task-pages/{taskPage}', [AdminTaskPageController::class, 'destroy']);
            Route::get('/calculator-settings', [AdminCalculatorSettingController::class, 'show']);
            Route::put('/calculator-settings', [AdminCalculatorSettingController::class, 'update']);
            Route::get('/service-categories', [AdminServiceCategoryController::class, 'index']);
            Route::post('/service-categories', [AdminServiceCategoryController::class, 'store']);
            Route::patch('/service-categories/{serviceCategory}', [AdminServiceCategoryController::class, 'update']);
            Route::delete('/service-categories/{serviceCategory}', [AdminServiceCategoryController::class, 'destroy']);
            Route::get('/services', [AdminServiceController::class, 'index']);
            Route::post('/services', [AdminServiceController::class, 'store']);
            Route::patch('/services/{service}', [AdminServiceController::class, 'update']);
            Route::delete('/services/{service}', [AdminServiceController::class, 'destroy']);
            Route::get('/service-packages', [AdminServicePackageController::class, 'index']);
            Route::post('/service-packages', [AdminServicePackageController::class, 'store']);
            Route::patch('/service-packages/{servicePackage}', [AdminServicePackageController::class, 'update']);
            Route::delete('/service-packages/{servicePackage}', [AdminServicePackageController::class, 'destroy']);
            Route::get('/service-gallery-items', [AdminServiceGalleryItemController::class, 'index']);
            Route::post('/service-gallery-items', [AdminServiceGalleryItemController::class, 'store']);
            Route::patch('/service-gallery-items/{serviceGalleryItem}', [AdminServiceGalleryItemController::class, 'update']);
            Route::delete('/service-gallery-items/{serviceGalleryItem}', [AdminServiceGalleryItemController::class, 'destroy']);
            Route::get('/service-videos', [AdminServiceVideoController::class, 'index']);
            Route::post('/service-videos', [AdminServiceVideoController::class, 'store']);
            Route::patch('/service-videos/{serviceVideo}', [AdminServiceVideoController::class, 'update']);
            Route::delete('/service-videos/{serviceVideo}', [AdminServiceVideoController::class, 'destroy']);
            Route::post('/uploads', [AdminUploadController::class, 'store']);
        });
    });
});
