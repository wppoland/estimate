<?php
/**
 * Service wiring. Returns a closure that registers every service in the
 * container. Services are thin and self-contained — this plugin has no external
 * runtime dependencies.
 *
 * @package Estimate
 */

declare(strict_types=1);

use Estimate\Admin\ProductData;
use Estimate\Admin\Settings;
use Estimate\Container;
use Estimate\Migrator;
use Estimate\PostType\QuoteRequest;
use Estimate\Service\QuoteList;
use Estimate\Service\QuotePage;
use Estimate\Service\QuoteProducts;

defined('ABSPATH') || exit;

return static function (Container $c): void {
    $c->singleton(Migrator::class, static fn (): Migrator => new Migrator());

    // Per-visitor quote list (cookie-backed store of product IDs + quantities).
    $c->singleton(QuoteList::class, static fn (): QuoteList => new QuoteList());

    // The private custom post type that stores submitted quote requests.
    $c->singleton(QuoteRequest::class, static fn (): QuoteRequest => new QuoteRequest());

    // Storefront: swap price / add-to-cart for an "Add to quote" button.
    $c->singleton(QuoteProducts::class, static fn (): QuoteProducts => new QuoteProducts(
        $c->get(QuoteList::class),
    ));

    // Storefront: the [estimate_quote] page (list + request form + submission).
    $c->singleton(QuotePage::class, static fn (): QuotePage => new QuotePage(
        $c->get(QuoteList::class),
        $c->get(QuoteRequest::class),
    ));

    // Admin (only needed in wp-admin context).
    if (is_admin()) {
        $c->singleton(Settings::class, static fn (): Settings => new Settings());
        $c->singleton(ProductData::class, static fn (): ProductData => new ProductData());
    }
};
