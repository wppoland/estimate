<?php
/**
 * Boot order: services listed here are resolved from the container and have
 * their registerHooks() called during Plugin::boot(). Each must implement
 * Estimate\Contract\HasHooks.
 *
 * @package Estimate
 *
 * @return array<class-string>
 */

declare(strict_types=1);

use Estimate\Admin\ProductData;
use Estimate\Admin\Settings;
use Estimate\PostType\QuoteRequest;
use Estimate\Service\QuoteList;
use Estimate\Service\QuotePage;
use Estimate\Service\QuoteProducts;

defined('ABSPATH') || exit;

return is_admin()
    ? [
        QuoteRequest::class,
        QuoteList::class,
        QuoteProducts::class,
        QuotePage::class,
        Settings::class,
        ProductData::class,
    ]
    : [
        QuoteRequest::class,
        QuoteList::class,
        QuoteProducts::class,
        QuotePage::class,
    ];
