<?php
/**
 * Default settings, merged under the option key `estimate_settings`.
 *
 * The plugin ships enabled in "selected" mode so nothing changes on the
 * storefront until the merchant marks individual products as quote-enabled.
 * Switching to "all" mode turns every product into a quote request.
 *
 * @package Estimate
 *
 * @return array<string, mixed>
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

return [
    // Master switch.
    'enabled' => true,

    // Quote mode: 'selected' (only flagged products) or 'all' (every product).
    'mode' => 'selected',

    // Hide the price alongside the add-to-cart button on quote-enabled products.
    'hide_price' => true,

    // Storefront button label. Empty = translated default ("Add to quote").
    'button_text' => '',

    // Merchant recipient for new quote requests. Empty = site admin email.
    'recipient' => '',

    // Optional intro shown above the quote list on the quote page.
    'quote_intro' => '',
];
