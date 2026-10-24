<?php

namespace App\Sites;

/**
 * Cache tags a Next.js site keeps the public API's answers under. After an
 * edit the platform sends the matching tags to the business's sites, which
 * drop those answers at once instead of waiting out their ISR window.
 * Values must match the *_TAG constants of the site (for Servio: src/lib/api.ts in the servio repository).
 */
enum SiteCacheTag: string
{
    case ServiceCategories = 'service-categories';
    case Services = 'services';
    case ServicePackages = 'service-packages';
    case ServiceGallery = 'service-gallery';
    case ServiceVideos = 'service-videos';
    case AdditionalServices = 'additional-services';
    case Products = 'products';
    case ProductCategories = 'product-categories';
    case TaskPages = 'task-pages';
    case CalculatorSettings = 'calculator-settings';
    case SiteSettings = 'site-settings';
}
