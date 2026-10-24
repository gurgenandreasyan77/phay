<?php

namespace App\Sites;

use App\Models\AdditionalService;
use App\Models\CalculatorSetting;
use App\Models\ContactChannel;
use App\Models\MarketTaskPage;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductImage;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServiceGalleryItem;
use App\Models\ServicePackage;
use App\Models\ServiceVideo;
use App\Models\SiteSetting;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Model observer: a saved or deleted row of public content marks its cache
 * tags for the owning business's sites.
 */
class RefreshSiteCache
{
    /** @var array<class-string<Model>, list<SiteCacheTag>> */
    public const TAGS = [
        ServiceCategory::class => [SiteCacheTag::ServiceCategories, SiteCacheTag::Services],
        Service::class => [SiteCacheTag::Services],
        ServicePackage::class => [SiteCacheTag::ServicePackages],
        ServiceGalleryItem::class => [SiteCacheTag::ServiceGallery],
        ServiceVideo::class => [SiteCacheTag::ServiceVideos],
        AdditionalService::class => [SiteCacheTag::AdditionalServices],
        Product::class => [SiteCacheTag::Products],
        ProductImage::class => [SiteCacheTag::Products],
        ProductCategory::class => [SiteCacheTag::ProductCategories, SiteCacheTag::Products],
        MarketTaskPage::class => [SiteCacheTag::TaskPages],
        CalculatorSetting::class => [SiteCacheTag::CalculatorSettings],
        SiteSetting::class => [SiteCacheTag::SiteSettings],
        ContactChannel::class => [SiteCacheTag::SiteSettings],
        Tenant::class => [SiteCacheTag::SiteSettings],
    ];

    public function __construct(private readonly SiteRevalidator $revalidator) {}

    public function saved(Model $model): void
    {
        $this->mark($model);
    }

    public function deleted(Model $model): void
    {
        $this->mark($model);
    }

    private function mark(Model $model): void
    {
        $tenantId = $model instanceof Tenant ? $model->getKey() : $model->getAttribute('tenant_id');

        if ($tenantId) {
            $this->revalidator->touch((int) $tenantId, ...self::TAGS[$model::class]);
        }
    }
}
