<?php

use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\V1\AdditionalServiceController;
use App\Http\Controllers\Api\V1\Admin\AccountLinkController as AdminAccountLinkController;
use App\Http\Controllers\Api\V1\Admin\AdditionalServiceController as AdminAdditionalServiceController;
use App\Http\Controllers\Api\V1\Admin\AgentController as AdminAgentController;
use App\Http\Controllers\Api\V1\Admin\AnalyticsController as AdminAnalyticsController;
use App\Http\Controllers\Api\V1\Admin\AssistantAttachmentController as AdminAssistantAttachmentController;
use App\Http\Controllers\Api\V1\Admin\AssistantController as AdminAssistantController;
use App\Http\Controllers\Api\V1\Admin\AudienceController as AdminAudienceController;
use App\Http\Controllers\Api\V1\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Api\V1\Admin\CalculatorSettingController as AdminCalculatorSettingController;
use App\Http\Controllers\Api\V1\Admin\CalendarController as AdminCalendarController;
use App\Http\Controllers\Api\V1\Admin\CalendarRowController as AdminCalendarRowController;
use App\Http\Controllers\Api\V1\Admin\ContactChannelController as AdminContactChannelController;
use App\Http\Controllers\Api\V1\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Api\V1\Admin\DataImportController as AdminDataImportController;
use App\Http\Controllers\Api\V1\Admin\DataTableController as AdminDataTableController;
use App\Http\Controllers\Api\V1\Admin\DataTableRowController as AdminDataTableRowController;
use App\Http\Controllers\Api\V1\Admin\DuplicateRowController as AdminDuplicateRowController;
use App\Http\Controllers\Api\V1\Admin\EmployeeController as AdminEmployeeController;
use App\Http\Controllers\Api\V1\Admin\EmployeeGroupController as AdminEmployeeGroupController;
use App\Http\Controllers\Api\V1\Admin\ExpenseCategoryController as AdminExpenseCategoryController;
use App\Http\Controllers\Api\V1\Admin\ExpenseStatisticsController as AdminExpenseStatisticsController;
use App\Http\Controllers\Api\V1\Admin\ExportController as AdminExportController;
use App\Http\Controllers\Api\V1\Admin\FeatureFlagController as AdminFeatureFlagController;
use App\Http\Controllers\Api\V1\Admin\FeedbackController as AdminFeedbackController;
use App\Http\Controllers\Api\V1\Admin\FinanceController as AdminFinanceController;
use App\Http\Controllers\Api\V1\Admin\GoogleCalendarController as AdminGoogleCalendarController;
use App\Http\Controllers\Api\V1\Admin\HrChangeController as AdminHrChangeController;
use App\Http\Controllers\Api\V1\Admin\LeadController as AdminLeadController;
use App\Http\Controllers\Api\V1\Admin\ModulePriceController as AdminModulePriceController;
use App\Http\Controllers\Api\V1\Admin\NotificationController as AdminNotificationController;
use App\Http\Controllers\Api\V1\Admin\PasswordController as AdminPasswordController;
use App\Http\Controllers\Api\V1\Admin\PlanController as AdminPlanController;
use App\Http\Controllers\Api\V1\Admin\PlatformBusinessController as AdminPlatformBusinessController;
use App\Http\Controllers\Api\V1\Admin\PlatformSubscriptionController as AdminPlatformSubscriptionController;
use App\Http\Controllers\Api\V1\Admin\PlatformSupportController as AdminPlatformSupportController;
use App\Http\Controllers\Api\V1\Admin\PlatformUserController as AdminPlatformUserController;
use App\Http\Controllers\Api\V1\Admin\PostController as AdminPostController;
use App\Http\Controllers\Api\V1\Admin\ProductCategoryController as AdminProductCategoryController;
use App\Http\Controllers\Api\V1\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Api\V1\Admin\RecurringExpenseController as AdminRecurringExpenseController;
use App\Http\Controllers\Api\V1\Admin\SearchController as AdminSearchController;
use App\Http\Controllers\Api\V1\Admin\SecurityController as AdminSecurityController;
use App\Http\Controllers\Api\V1\Admin\SecurityEventController as AdminSecurityEventController;
use App\Http\Controllers\Api\V1\Admin\ServiceCategoryController as AdminServiceCategoryController;
use App\Http\Controllers\Api\V1\Admin\ServiceController as AdminServiceController;
use App\Http\Controllers\Api\V1\Admin\ServiceGalleryItemController as AdminServiceGalleryItemController;
use App\Http\Controllers\Api\V1\Admin\ServicePackageController as AdminServicePackageController;
use App\Http\Controllers\Api\V1\Admin\ServiceVideoController as AdminServiceVideoController;
use App\Http\Controllers\Api\V1\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Api\V1\Admin\SiteController as AdminSiteController;
use App\Http\Controllers\Api\V1\Admin\SocialController as AdminSocialController;
use App\Http\Controllers\Api\V1\Admin\SubscriptionController as AdminSubscriptionController;
use App\Http\Controllers\Api\V1\Admin\SupportController as AdminSupportController;
use App\Http\Controllers\Api\V1\Admin\TableAnalyticsController as AdminTableAnalyticsController;
use App\Http\Controllers\Api\V1\Admin\TableChangeController as AdminTableChangeController;
use App\Http\Controllers\Api\V1\Admin\TableReshapeController as AdminTableReshapeController;
use App\Http\Controllers\Api\V1\Admin\TaskPageController as AdminTaskPageController;
use App\Http\Controllers\Api\V1\Admin\TeamController as AdminTeamController;
use App\Http\Controllers\Api\V1\Admin\TenantAgentController as AdminTenantAgentController;
use App\Http\Controllers\Api\V1\Admin\TenantController as AdminTenantController;
use App\Http\Controllers\Api\V1\Admin\TenantFeatureController as AdminTenantFeatureController;
use App\Http\Controllers\Api\V1\Admin\TenantUserController as AdminTenantUserController;
use App\Http\Controllers\Api\V1\Admin\UploadController as AdminUploadController;
use App\Http\Controllers\Api\V1\CalculatorSettingController;
use App\Http\Controllers\Api\V1\FeedbackController;
use App\Http\Controllers\Api\V1\LeadController;
use App\Http\Controllers\Api\V1\MetaCallbackController;
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
    // Meta's callbacks (set in the Meta app): a person removed the app or
    // asked for their data to be deleted. Signed with the app secret.
    Route::middleware('throttle:meta-callbacks')->group(function () {
        Route::post('/meta/deauthorize', [MetaCallbackController::class, 'deauthorize']);
        Route::post('/meta/data-deletion', [MetaCallbackController::class, 'deletion']);
        Route::get('/meta/data-deletion/{code}', [MetaCallbackController::class, 'status'])->where('code', '[a-z0-9]{20}');
    });

    // Public API: the X-Site-Key header decides which business's content is
    // served (no header: the default business).
    Route::middleware('tenant.site')->group(function () {
        // The services module: the catalog (every kind of work the business
        // does is a category of it: car care, upholstery...), and the price
        // calculator with its add-ons for a business that has one.
        Route::middleware('feature:services')->group(function () {
            Route::get('/categories', [ServiceCategoryController::class, 'index']);
            Route::get('/services', [ServiceController::class, 'index']);
            Route::get('/services/{slug}', [ServiceController::class, 'show']);
            Route::get('/service-packages', [ServicePackageController::class, 'index']);
            Route::get('/service-packages/{slug}', [ServicePackageController::class, 'show']);
            Route::get('/additional-services', [AdditionalServiceController::class, 'index']);
            Route::get('/calculator-settings', [CalculatorSettingController::class, 'show']);
        });

        // The site module: what the business shows about itself.
        Route::middleware('feature:site')->group(function () {
            Route::get('/service-gallery', [ServiceGalleryItemController::class, 'index']);
            Route::get('/service-videos', [ServiceVideoController::class, 'index']);
            Route::get('/posts', [PostController::class, 'index']);
            Route::get('/posts/{post:slug}', [PostController::class, 'show']);
            Route::post('/feedback', [FeedbackController::class, 'store'])->middleware('throttle:leads');
        });

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

        Route::get('/settings', [SettingsController::class, 'show']);
        Route::post('/leads', [LeadController::class, 'store'])->middleware('throttle:leads');
    });

    Route::prefix('admin')->group(function () {
        Route::post('/login', [AdminAuthController::class, 'login'])->middleware('throttle:admin-login');
        Route::post('/login/two-factor', [AdminAuthController::class, 'twoFactor'])->middleware('throttle:two-factor');

        // Opened from a one-time link (the token travels in the body, never the URL).
        Route::middleware('throttle:account-links')->group(function () {
            Route::post('/invitations/lookup', [AdminAccountLinkController::class, 'showInvitation']);
            Route::post('/invitations/accept', [AdminAccountLinkController::class, 'acceptInvitation']);
            Route::post('/password/reset/lookup', [AdminAccountLinkController::class, 'showReset']);
            Route::post('/password/reset', [AdminAccountLinkController::class, 'reset']);
        });
        Route::post('/password/forgot', [AdminAccountLinkController::class, 'forgot'])->middleware('throttle:password-forgot');

        // Admin API: the signed-in user's current business. Who must use 2FA
        // and hasn't set it up yet only reaches the setup (two-factor).
        Route::middleware(['auth:sanctum', 'tenant.admin', 'two-factor'])->group(function () {
            Route::post('/logout', [AdminAuthController::class, 'logout']);
            Route::get('/me', [AdminAuthController::class, 'me']);
            Route::post('/switch-tenant', [AdminAuthController::class, 'switchTenant']);
            // Everyone changes their own password by typing the current one.
            Route::put('/password', [AdminPasswordController::class, 'update'])->middleware('throttle:6,1');

            // The person's own account security.
            Route::get('/security', [AdminSecurityController::class, 'show']);
            Route::middleware('throttle:security')->group(function () {
                Route::post('/security/two-factor', [AdminSecurityController::class, 'beginTwoFactor']);
                Route::post('/security/two-factor/confirm', [AdminSecurityController::class, 'confirmTwoFactor']);
                Route::delete('/security/two-factor', [AdminSecurityController::class, 'disableTwoFactor']);
                Route::post('/security/two-factor/recovery-codes', [AdminSecurityController::class, 'regenerateRecoveryCodes']);
                Route::post('/security/email/verify', [AdminSecurityController::class, 'verifyEmail']);
                Route::post('/security/phone/verify', [AdminSecurityController::class, 'verifyPhone']);
                Route::delete('/security/sessions/{session}', [AdminSecurityController::class, 'revokeSession'])->whereNumber('session');
                Route::delete('/security/sessions', [AdminSecurityController::class, 'revokeOtherSessions']);
                Route::put('/security/business', [AdminSecurityController::class, 'updateBusiness']);
            });
            Route::middleware('throttle:verification-codes')->group(function () {
                Route::post('/security/email/code', [AdminSecurityController::class, 'sendEmailCode']);
                Route::post('/security/phone/code', [AdminSecurityController::class, 'sendPhoneCode']);
            });
            // Unread chat messages for the menu badge (either side).
            Route::get('/support/unread', [AdminSupportController::class, 'unread']);
            // The header: the bell (open even after the subscription ended) and the search.
            Route::get('/notifications', [AdminNotificationController::class, 'index']);
            Route::post('/notifications/read-all', [AdminNotificationController::class, 'readAll']);
            Route::post('/notifications/{notification}/read', [AdminNotificationController::class, 'read'])->whereNumber('notification');
            Route::get('/search', AdminSearchController::class)->middleware('throttle:60,1');
            // The business's subscription: open to all its people, even after it
            // ended (the admin shows how to come back); asking goes to the chat.
            Route::get('/subscription', [AdminSubscriptionController::class, 'show']);
            Route::post('/subscription/request', [AdminSubscriptionController::class, 'request'])
                ->middleware(['business.can:settings', 'throttle:10,1']);

            // Onboarding (platform staff: super admin and moderators); a
            // business's own people get 403 here.
            Route::middleware('platform.staff')->group(function () {
                Route::get('/tenants', [AdminTenantController::class, 'index']);
                Route::post('/tenants', [AdminTenantController::class, 'store']);
                // One business from the platform level: activity, subscription,
                // modules, AI usage; never its orders, sales or money.
                Route::get('/tenants/{tenant}/overview', [AdminPlatformBusinessController::class, 'show']);
                Route::get('/tenants/{tenant}/users', [AdminTenantUserController::class, 'index']);
                Route::post('/tenants/{tenant}/users', [AdminTenantUserController::class, 'store']);
                Route::patch('/tenants/{tenant}/users/{user}', [AdminTenantUserController::class, 'update']);
                Route::delete('/tenants/{tenant}/users/{user}', [AdminTenantUserController::class, 'destroy']);
                Route::middleware('throttle:account-links')->group(function () {
                    Route::post('/tenants/{tenant}/users/{user}/invitation', [AdminTenantUserController::class, 'invitation']);
                    Route::post('/tenants/{tenant}/users/{user}/password-reset', [AdminTenantUserController::class, 'passwordReset']);
                });
                Route::delete('/tenants/{tenant}/users/{user}/two-factor', [AdminTenantUserController::class, 'resetTwoFactor']);
                Route::get('/tenants/{tenant}/features', [AdminTenantFeatureController::class, 'index']);
                Route::get('/sites', [AdminSiteController::class, 'index']);
                Route::post('/sites', [AdminSiteController::class, 'store']);
                Route::patch('/sites/{site}', [AdminSiteController::class, 'update']);
                Route::post('/sites/{site}/rotate-key', [AdminSiteController::class, 'rotateKey']);
                Route::post('/sites/{site}/check-revalidation', [AdminSiteController::class, 'checkRevalidation'])->middleware('throttle:10,1');
                // The chat with businesses: every conversation, and one business's.
                Route::get('/support/conversations', [AdminPlatformSupportController::class, 'conversations']);
                Route::get('/tenants/{tenant}/messages', [AdminPlatformSupportController::class, 'index']);
                Route::post('/tenants/{tenant}/messages', [AdminPlatformSupportController::class, 'store'])->middleware('throttle:30,1');
            });

            // Platform-wide switches and the platform team: super admin only.
            Route::middleware('super.admin')->group(function () {
                Route::patch('/tenants/{tenant}/features/{key}', [AdminTenantFeatureController::class, 'update']);
                // The business's AI agents, turned on like modules.
                Route::get('/tenants/{tenant}/agents', [AdminTenantAgentController::class, 'index']);
                Route::patch('/tenants/{tenant}/agents/{agent}', [AdminTenantAgentController::class, 'update']);
                Route::patch('/feature-flags/{key}', [AdminFeatureFlagController::class, 'update']);
                Route::get('/platform-users', [AdminPlatformUserController::class, 'index']);
                Route::post('/platform-users', [AdminPlatformUserController::class, 'store']);
                Route::patch('/platform-users/{user}', [AdminPlatformUserController::class, 'update']);
                Route::delete('/platform-users/{user}', [AdminPlatformUserController::class, 'destroy']);
                Route::middleware('throttle:account-links')->group(function () {
                    Route::post('/platform-users/{user}/invitation', [AdminPlatformUserController::class, 'invitation']);
                    Route::post('/platform-users/{user}/password-reset', [AdminPlatformUserController::class, 'passwordReset']);
                });
                Route::delete('/platform-users/{user}/two-factor', [AdminPlatformUserController::class, 'resetTwoFactor']);
                Route::get('/security-events', [AdminSecurityEventController::class, 'index']);

                // Subscriptions: plans, module prices and every business's subscription.
                Route::get('/plans', [AdminPlanController::class, 'index']);
                Route::post('/plans', [AdminPlanController::class, 'store']);
                Route::patch('/plans/{plan}', [AdminPlanController::class, 'update']);
                Route::delete('/plans/{plan}', [AdminPlanController::class, 'destroy']);
                Route::get('/module-prices', [AdminModulePriceController::class, 'index']);
                Route::put('/module-prices', [AdminModulePriceController::class, 'update']);
                Route::get('/subscriptions', [AdminPlatformSubscriptionController::class, 'index']);
                Route::get('/tenants/{tenant}/subscription', [AdminPlatformSubscriptionController::class, 'show']);
                Route::patch('/tenants/{tenant}/subscription', [AdminPlatformSubscriptionController::class, 'update']);
                Route::post('/tenants/{tenant}/subscription/payments', [AdminPlatformSubscriptionController::class, 'storePayment']);
                Route::delete('/tenants/{tenant}/subscription/payments/{payment}', [AdminPlatformSubscriptionController::class, 'destroyPayment']);
                Route::post('/tenants/{tenant}/subscription/free', [AdminPlatformSubscriptionController::class, 'grantFree']);
                Route::delete('/tenants/{tenant}/subscription/free', [AdminPlatformSubscriptionController::class, 'removeFree']);
                Route::post('/tenants/{tenant}/subscription/cancel', [AdminPlatformSubscriptionController::class, 'cancel']);
                Route::post('/tenants/{tenant}/subscription/resume', [AdminPlatformSubscriptionController::class, 'resume']);
                Route::post('/tenants/{tenant}/subscription/assistant-bonus', [AdminPlatformSubscriptionController::class, 'grantAssistantBonus']);
            });

            // The business admin, by role (App\Enums\BusinessRole).
            Route::get('/feature-flags', [AdminFeatureFlagController::class, 'index']);

            // The AI team: the owner (insights) or the admin (settings), checked in the controller.
            Route::get('/agents', [AdminAgentController::class, 'index']);
            Route::put('/agents/profile', [AdminAgentController::class, 'updateProfile'])->middleware('throttle:30,1');
            Route::put('/agents/{agent}/notes', [AdminAgentController::class, 'updateNotes'])->middleware('throttle:30,1');
            // The brand's pictures the agents look at (logo, examples).
            Route::post('/agents/brand-assets', [AdminAgentController::class, 'storeAsset'])->middleware('throttle:30,1');
            Route::get('/agents/brand-assets/{brandAsset}/file', [AdminAgentController::class, 'assetFile']);
            Route::delete('/agents/brand-assets/{brandAsset}', [AdminAgentController::class, 'destroyAsset']);

            Route::middleware('business.can:orders')->group(function () {
                Route::get('/dashboard', AdminDashboardController::class);

                // Orders from the site: every business has them.
                Route::get('/leads', [AdminLeadController::class, 'index']);
                Route::post('/leads', [AdminLeadController::class, 'store']);
                Route::get('/leads/{lead}', [AdminLeadController::class, 'show']);
                Route::patch('/leads/{lead}', [AdminLeadController::class, 'update']);

                // Orders by day: rows of the order tables and the site's orders.
                Route::get('/calendar', AdminCalendarController::class);
                // An order added on a day goes to the table of its month (made when missing).
                Route::post('/calendar/rows', [AdminCalendarRowController::class, 'store']);
                // While it's typed in: is the performer free, which number it gets.
                Route::post('/calendar/rows/check', [AdminCalendarRowController::class, 'check']);

                // Each person's Google Calendar, read only: connect, choose calendars, sync.
                Route::get('/google', [AdminGoogleCalendarController::class, 'show']);
                Route::post('/google/connect', [AdminGoogleCalendarController::class, 'connect'])->middleware('throttle:10,1');
                Route::post('/google/callback', [AdminGoogleCalendarController::class, 'callback'])->middleware('throttle:10,1');
                Route::put('/google/calendars', [AdminGoogleCalendarController::class, 'choose']);
                Route::post('/google/sync', [AdminGoogleCalendarController::class, 'sync'])->middleware('throttle:google-sync');
                Route::delete('/google', [AdminGoogleCalendarController::class, 'disconnect']);

                // What visitors wrote through the site's feedback form.
                Route::middleware('feature:site')->group(function () {
                    Route::get('/feedback', [AdminFeedbackController::class, 'index']);
                    Route::patch('/feedback/{feedbackMessage}', [AdminFeedbackController::class, 'update']);
                    Route::delete('/feedback/{feedbackMessage}', [AdminFeedbackController::class, 'destroy']);
                });

                // The business's own tables (orders, sales): everyone who works
                // with orders reads them and keeps their rows; the controllers
                // check the table's module.
                Route::get('/data-tables', [AdminDataTableController::class, 'index']);
                // Downloading tables as XLSX or CSV: owner and admin (several tables: one workbook).
                Route::middleware(['business.can:catalog', 'throttle:30,1'])->group(function () {
                    Route::get('/data-tables/export', [AdminExportController::class, 'tables']);
                    Route::get('/data-tables/{dataTable}/export', [AdminExportController::class, 'table']);
                });
                Route::get('/data-tables/{dataTable}', [AdminDataTableController::class, 'show']);
                Route::scopeBindings()->group(function () {
                    Route::get('/data-tables/{dataTable}/rows', [AdminDataTableRowController::class, 'index']);
                    Route::post('/data-tables/{dataTable}/rows', [AdminDataTableRowController::class, 'store']);
                    Route::patch('/data-tables/{dataTable}/rows/{row}', [AdminDataTableRowController::class, 'update']);
                    Route::delete('/data-tables/{dataTable}/rows/{row}', [AdminDataTableRowController::class, 'destroy']);
                });

                // The same record in several tables: the groups, keep all or delete copies.
                Route::get('/duplicates', [AdminDuplicateRowController::class, 'index']);
                Route::post('/duplicates/keep', [AdminDuplicateRowController::class, 'keep']);
                Route::post('/duplicates/delete', [AdminDuplicateRowController::class, 'destroy']);

                // Who does the orders: the performer field offers them.
                Route::get('/employees', [AdminEmployeeController::class, 'index']);
                Route::get('/employee-groups', [AdminEmployeeGroupController::class, 'index']);

                // Filling a table from a CSV, XLSX or XML file.
                Route::get('/data-tables/{dataTable}/imports', [AdminDataImportController::class, 'index']);
                Route::post('/data-imports', [AdminDataImportController::class, 'store'])->middleware('throttle:data-imports');
                Route::get('/data-imports/{dataImport}', [AdminDataImportController::class, 'show']);
                Route::post('/data-imports/{dataImport}/run', [AdminDataImportController::class, 'run']);
                Route::post('/data-imports/{dataImport}/undo', [AdminDataImportController::class, 'undo']);
            });

            Route::middleware('business.can:insights')->group(function () {
                Route::get('/analytics', AdminAnalyticsController::class);
                Route::get('/analytics/tables', AdminTableAnalyticsController::class);
                Route::put('/analytics/dashboard', [AdminTableAnalyticsController::class, 'update']);
                Route::get('/analytics/statistics', [AdminTableAnalyticsController::class, 'statistics']);
                Route::get('/analytics/audience', AdminAudienceController::class);
                // What each employee did: orders, revenue, costs.
                Route::get('/employees/stats', [AdminEmployeeController::class, 'stats']);
                Route::get('/assistant', [AdminAssistantController::class, 'show']);
                Route::post('/assistant', [AdminAssistantController::class, 'store'])->middleware('throttle:assistant');
                // Each person's kept conversations with the assistant.
                Route::get('/assistant/conversations', [AdminAssistantController::class, 'conversations']);
                // The latest chats for the header and reopening the last one.
                Route::get('/assistant/recent', [AdminAssistantController::class, 'recent']);
                // Files attached to a question: uploaded first, sent with it by id.
                Route::post('/assistant/attachments', [AdminAssistantAttachmentController::class, 'store'])->middleware('throttle:assistant-uploads');
                Route::get('/assistant/attachments/{assistantAttachment}/file', [AdminAssistantAttachmentController::class, 'file']);
                // A video an agent is cutting: polled by the chat until it is ready.
                Route::get('/assistant/videos/{videoJob}', [AdminAssistantController::class, 'video']);
                Route::delete('/assistant/attachments/{assistantAttachment}', [AdminAssistantAttachmentController::class, 'destroy']);

                // The social module: the business's Instagram and Facebook
                // through Meta (read only) and the accounts it watches.
                Route::middleware('feature:social')->group(function () {
                    Route::get('/social', [AdminSocialController::class, 'show']);
                    Route::post('/social/sync', [AdminSocialController::class, 'sync'])->middleware('throttle:social-sync');
                    Route::get('/social/competitors/{socialCompetitor}', [AdminSocialController::class, 'competitor']);
                    // Connecting and choosing what is read: who manages the business's settings.
                    Route::middleware('business.can:settings')->group(function () {
                        Route::post('/social/connect', [AdminSocialController::class, 'connect'])->middleware('throttle:10,1');
                        Route::post('/social/callback', [AdminSocialController::class, 'callback'])->middleware('throttle:10,1');
                        Route::put('/social/accounts', [AdminSocialController::class, 'choose'])->middleware('throttle:social-sync');
                        Route::delete('/social', [AdminSocialController::class, 'disconnect']);
                        Route::post('/social/competitors', [AdminSocialController::class, 'addCompetitor'])->middleware('throttle:20,1');
                        Route::delete('/social/competitors/{socialCompetitor}', [AdminSocialController::class, 'removeCompetitor']);
                    });
                });
                Route::get('/assistant/conversations/{assistantConversation}', [AdminAssistantController::class, 'conversation']);
                Route::patch('/assistant/conversations/{assistantConversation}', [AdminAssistantController::class, 'rename']);
                Route::post('/assistant/conversations/{assistantConversation}/read', [AdminAssistantController::class, 'read']);
                Route::delete('/assistant/conversations/{assistantConversation}', [AdminAssistantController::class, 'destroy']);
            });

            // What the business spends: kinds of expenses and the ones that
            // repeat; the expenses tables themselves go through /data-tables.
            Route::middleware('business.can:expenses')->group(function () {
                Route::get('/expense-categories', [AdminExpenseCategoryController::class, 'index']);
                Route::post('/expense-categories', [AdminExpenseCategoryController::class, 'store']);
                Route::patch('/expense-categories/{expenseCategory}', [AdminExpenseCategoryController::class, 'update']);
                Route::delete('/expense-categories/{expenseCategory}', [AdminExpenseCategoryController::class, 'destroy']);
                Route::get('/recurring-expenses', [AdminRecurringExpenseController::class, 'index']);
                Route::post('/recurring-expenses', [AdminRecurringExpenseController::class, 'store']);
                Route::patch('/recurring-expenses/{recurringExpense}', [AdminRecurringExpenseController::class, 'update']);
                Route::delete('/recurring-expenses/{recurringExpense}', [AdminRecurringExpenseController::class, 'destroy']);
                // What was spent in a day, week, month, quarter or year, apart from sales.
                Route::get('/expenses/statistics', AdminExpenseStatisticsController::class);
                // Revenue − cost of goods − expenses, per day, week, month, quarter or year.
                Route::get('/analytics/finance', AdminFinanceController::class)->middleware('business.can:insights');
                // The same reports as XLSX or CSV files.
                Route::get('/expenses/statistics/export', [AdminExportController::class, 'expenses'])->middleware('throttle:30,1');
                Route::get('/analytics/finance/export', [AdminExportController::class, 'finance'])->middleware(['business.can:insights', 'throttle:30,1']);
            });

            Route::middleware('business.can:settings')->group(function () {
                Route::get('/settings', [AdminSettingsController::class, 'show']);
                Route::put('/settings', [AdminSettingsController::class, 'update']);
                Route::post('/settings/logo', [AdminSettingsController::class, 'storeLogo']);
                Route::delete('/settings/logo', [AdminSettingsController::class, 'destroyLogo']);
                // The chat with the platform team.
                Route::get('/support/messages', [AdminSupportController::class, 'index']);
                Route::post('/support/messages', [AdminSupportController::class, 'store'])->middleware('throttle:30,1');
                Route::post('/contact-channels', [AdminContactChannelController::class, 'store']);
                Route::patch('/contact-channels/{contactChannel}', [AdminContactChannelController::class, 'update']);
                Route::delete('/contact-channels/{contactChannel}', [AdminContactChannelController::class, 'destroy']);

                // The business's employees: owner and admin.
                Route::post('/employees', [AdminEmployeeController::class, 'store']);
                // One employee's orders; the money only for the owner.
                Route::get('/employees/{employee}', [AdminEmployeeController::class, 'show']);
                Route::patch('/employees/{employee}', [AdminEmployeeController::class, 'update']);
                Route::delete('/employees/{employee}', [AdminEmployeeController::class, 'destroy']);
                // Time off and hiring (the HR agent prepares the same as cards).
                Route::get('/team/absences', [AdminTeamController::class, 'absences']);
                Route::post('/team/absences', [AdminTeamController::class, 'storeAbsence']);
                Route::delete('/team/absences/{absence}', [AdminTeamController::class, 'destroyAbsence']);
                Route::get('/team/hiring', [AdminTeamController::class, 'hiring']);
                Route::post('/team/openings', [AdminTeamController::class, 'storeOpening']);
                Route::patch('/team/openings/{opening}', [AdminTeamController::class, 'updateOpening']);
                Route::delete('/team/openings/{opening}', [AdminTeamController::class, 'destroyOpening']);
                Route::post('/team/candidates', [AdminTeamController::class, 'storeCandidate']);
                Route::patch('/team/candidates/{candidate}', [AdminTeamController::class, 'updateCandidate']);
                Route::delete('/team/candidates/{candidate}', [AdminTeamController::class, 'destroyCandidate']);
                // Changes of the team the HR agent prepared: the owner applies, discards or undoes them.
                Route::get('/hr-changes/{hrChange}', [AdminHrChangeController::class, 'show']);
                Route::post('/hr-changes/{hrChange}/apply', [AdminHrChangeController::class, 'apply'])->middleware('throttle:30,1');
                Route::post('/hr-changes/{hrChange}/undo', [AdminHrChangeController::class, 'undo'])->middleware('throttle:30,1');
                Route::post('/hr-changes/{hrChange}/discard', [AdminHrChangeController::class, 'discard'])->middleware('throttle:30,1');
                // Groups of employees and the managers who lead them.
                Route::post('/employee-groups', [AdminEmployeeGroupController::class, 'store']);
                Route::patch('/employee-groups/{employeeGroup}', [AdminEmployeeGroupController::class, 'update']);
                Route::delete('/employee-groups/{employeeGroup}', [AdminEmployeeGroupController::class, 'destroy']);
            });

            Route::middleware('business.can:catalog')->group(function () {
                // Designing a table (its name and columns): owner and admin.
                Route::post('/data-tables', [AdminDataTableController::class, 'store']);
                // The synced Google events as an order table (a new version when they changed).
                Route::get('/google/table', [AdminGoogleCalendarController::class, 'preview']);
                Route::post('/google/table', [AdminGoogleCalendarController::class, 'makeTable'])->middleware('throttle:google-sync');
                Route::post('/data-tables/bulk-delete', [AdminDataTableController::class, 'bulkDestroy']);
                Route::patch('/data-tables/{dataTable}', [AdminDataTableController::class, 'update']);
                // What the columns without a role probably mean, by their names and values.
                Route::get('/data-tables/{dataTable}/role-suggestions', [AdminDataTableController::class, 'roleSuggestions']);
                Route::delete('/data-tables/{dataTable}', [AdminDataTableController::class, 'destroy']);
                // Splitting tables into periods and putting them together: a preview, then the
                // reshape (or one an AI agent planned), undone while the new tables are untouched.
                Route::post('/table-reshapes/preview', [AdminTableReshapeController::class, 'preview'])->middleware('throttle:60,1');
                Route::post('/table-reshapes', [AdminTableReshapeController::class, 'store'])->middleware('throttle:20,1');
                Route::get('/table-reshapes/{tableReshape}', [AdminTableReshapeController::class, 'show']);
                Route::post('/table-reshapes/{tableReshape}/apply', [AdminTableReshapeController::class, 'apply'])->middleware('throttle:20,1');
                Route::post('/table-reshapes/{tableReshape}/undo', [AdminTableReshapeController::class, 'undo'])->middleware('throttle:20,1');
                // Changes of the tables an AI agent prepared: the owner applies, discards or undoes them.
                Route::get('/table-changes/{tableChange}', [AdminTableChangeController::class, 'show']);
                Route::post('/table-changes/{tableChange}/apply', [AdminTableChangeController::class, 'apply'])->middleware('throttle:30,1');
                Route::post('/table-changes/{tableChange}/undo', [AdminTableChangeController::class, 'undo'])->middleware('throttle:30,1');
                Route::post('/table-changes/{tableChange}/discard', [AdminTableChangeController::class, 'discard'])->middleware('throttle:30,1');

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
                // The services module, as on the site (see above).
                Route::middleware('feature:services')->group(function () {
                    // The price calculator: added by hand, a business without one has none.
                    Route::get('/calculator-settings', [AdminCalculatorSettingController::class, 'show']);
                    Route::post('/calculator-settings', [AdminCalculatorSettingController::class, 'store']);
                    Route::put('/calculator-settings', [AdminCalculatorSettingController::class, 'update']);
                    Route::get('/additional-services', [AdminAdditionalServiceController::class, 'index']);
                    Route::post('/additional-services', [AdminAdditionalServiceController::class, 'store']);
                    Route::patch('/additional-services/{additionalService}', [AdminAdditionalServiceController::class, 'update']);
                    Route::delete('/additional-services/{additionalService}', [AdminAdditionalServiceController::class, 'destroy']);
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
                });

                // The site module (see above).
                Route::middleware('feature:site')->group(function () {
                    Route::get('/posts', [AdminPostController::class, 'index']);
                    Route::post('/posts', [AdminPostController::class, 'store']);
                    Route::get('/posts/{post}', [AdminPostController::class, 'show']);
                    Route::patch('/posts/{post}', [AdminPostController::class, 'update']);
                    Route::delete('/posts/{post}', [AdminPostController::class, 'destroy']);
                    Route::get('/service-gallery-items', [AdminServiceGalleryItemController::class, 'index']);
                    Route::post('/service-gallery-items', [AdminServiceGalleryItemController::class, 'store']);
                    Route::patch('/service-gallery-items/{serviceGalleryItem}', [AdminServiceGalleryItemController::class, 'update']);
                    Route::delete('/service-gallery-items/{serviceGalleryItem}', [AdminServiceGalleryItemController::class, 'destroy']);
                    Route::get('/service-videos', [AdminServiceVideoController::class, 'index']);
                    Route::post('/service-videos', [AdminServiceVideoController::class, 'store']);
                    Route::patch('/service-videos/{serviceVideo}', [AdminServiceVideoController::class, 'update']);
                    Route::delete('/service-videos/{serviceVideo}', [AdminServiceVideoController::class, 'destroy']);
                });
                Route::post('/uploads', [AdminUploadController::class, 'store']);
            });
        });
    });
});
