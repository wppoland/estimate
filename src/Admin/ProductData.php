<?php

declare(strict_types=1);

namespace Estimate\Admin;

use Estimate\Contract\HasHooks;
use Estimate\Service\QuoteProducts;

defined('ABSPATH') || exit;

/**
 * Adds an "Enable quote requests" checkbox to the product editor (General tab in
 * the Product data box). Only relevant in "selected" mode; in "all" mode every
 * product is quote-enabled regardless of this flag.
 */
final class ProductData implements HasHooks
{
    private const OPTION = 'estimate_settings';
    private const NONCE  = 'estimate_product_data';

    public function registerHooks(): void
    {
        add_action('woocommerce_product_options_general_product_data', [$this, 'renderField']);
        add_action('woocommerce_admin_process_product_object', [$this, 'save']);
    }

    public function renderField(): void
    {
        $mode = (string) ($this->settings()['mode'] ?? 'selected');

        echo '<div class="options_group">';

        wp_nonce_field(self::NONCE, 'estimate_product_data_nonce');

        woocommerce_wp_checkbox([
            'id'          => QuoteProducts::META_ENABLED,
            'value'       => $this->fieldValue(),
            'label'       => __('Enable quote requests', 'estimate'),
            'description' => 'all' === $mode
                ? __('Quote mode is set to "all products", so every product already shows an Add to quote button.', 'estimate')
                : __('Hide the price and add-to-cart button and show an "Add to quote" button instead.', 'estimate'),
        ]);

        echo '</div>';
    }

    public function save(\WC_Product $product): void
    {
        $nonce = isset($_POST['estimate_product_data_nonce'])
            ? sanitize_text_field(wp_unslash($_POST['estimate_product_data_nonce']))
            : '';

        if (! wp_verify_nonce($nonce, self::NONCE)) {
            return;
        }

        $enabled = isset($_POST[QuoteProducts::META_ENABLED]) ? 'yes' : 'no';
        $product->update_meta_data(QuoteProducts::META_ENABLED, $enabled);
    }

    /**
     * Current checkbox value for the product being edited.
     */
    private function fieldValue(): string
    {
        global $post;

        if (! $post instanceof \WP_Post) {
            return 'no';
        }

        $product = wc_get_product($post->ID);

        if (! $product instanceof \WC_Product) {
            return 'no';
        }

        return 'yes' === $product->get_meta(QuoteProducts::META_ENABLED) ? 'yes' : 'no';
    }

    /**
     * @return array<string, mixed>
     */
    private function settings(): array
    {
        $stored = get_option(self::OPTION, []);

        if (! is_array($stored)) {
            $stored = [];
        }

        /** @var array<string, mixed> $defaults */
        $defaults = require ESTIMATE_DIR . 'config/defaults.php';

        return array_merge($defaults, $stored);
    }
}
