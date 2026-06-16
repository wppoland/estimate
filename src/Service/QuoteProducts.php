<?php

declare(strict_types=1);

namespace Estimate\Service;

use Estimate\Contract\HasHooks;

defined('ABSPATH') || exit;

/**
 * Storefront behaviour for quote-enabled products.
 *
 * On products that are quote-enabled (either every product in "all" mode, or the
 * individually flagged ones in "selected" mode) this hides the price and the
 * add-to-cart button and renders an "Add to quote" button in their place, on
 * both single product pages and shop/category loops. Each "Add to quote" button
 * is a real link that adds the product and lands on the quote page, so the flow
 * works without any JavaScript.
 */
final class QuoteProducts implements HasHooks
{
    /** Product meta flag set by the merchant in "selected" mode. */
    public const META_ENABLED = '_estimate_quote_enabled';

    private const OPTION = 'estimate_settings';
    private const NONCE  = 'estimate_quote';

    public function __construct(private readonly QuoteList $list)
    {
    }

    public function registerHooks(): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        // Replace price + add-to-cart on quote-enabled products.
        add_filter('woocommerce_get_price_html', [$this, 'maybeHidePrice'], 100, 2);
        add_action('woocommerce_single_product_summary', [$this, 'maybeReplaceSingle'], 1);
        add_filter('woocommerce_loop_add_to_cart_link', [$this, 'maybeReplaceLoopButton'], 100, 2);

        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);

        // Handle the add-to-quote link.
        add_action('template_redirect', [$this, 'handleAddLink']);
    }

    /**
     * Whether a given product should be treated as quote-only.
     */
    public function isQuoteProduct(\WC_Product $product): bool
    {
        $mode = (string) ($this->settings()['mode'] ?? 'selected');

        return 'all' === $mode
            ? true
            : 'yes' === $product->get_meta(self::META_ENABLED);
    }

    public function maybeHidePrice(mixed $price, mixed $product): mixed
    {
        if (! $product instanceof \WC_Product) {
            return $price;
        }

        if (! $this->isQuoteProduct($product)) {
            return $price;
        }

        if (empty($this->settings()['hide_price'])) {
            return $price;
        }

        return '';
    }

    /**
     * On single product pages, remove the add-to-cart form for quote products
     * and render our button instead.
     */
    public function maybeReplaceSingle(): void
    {
        global $product;

        if (! $product instanceof \WC_Product || ! $this->isQuoteProduct($product)) {
            return;
        }

        remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30);
        add_action('woocommerce_single_product_summary', [$this, 'renderSingleButton'], 30);
    }

    public function renderSingleButton(): void
    {
        global $product;

        if (! $product instanceof \WC_Product) {
            return;
        }

        echo $this->buttonHtml($product, true); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- buttonHtml escapes internally.
    }

    /**
     * In product loops, swap the add-to-cart link for an "Add to quote" link.
     */
    public function maybeReplaceLoopButton(mixed $html, mixed $product): mixed
    {
        if (! $product instanceof \WC_Product || ! $this->isQuoteProduct($product)) {
            return $html;
        }

        return $this->buttonHtml($product, false);
    }

    /**
     * Build the "Add to quote" button markup. All dynamic values are escaped.
     */
    private function buttonHtml(\WC_Product $product, bool $single): string
    {
        $label = $this->buttonLabel();
        $added = $this->list->has($product->get_id());

        $classes = 'button estimate-add-to-quote';
        if (! $single) {
            $classes .= ' add_to_cart_button';
        }
        if ($added) {
            $classes .= ' estimate-added';
        }

        $href = add_query_arg(
            [
                'estimate_add'   => $product->get_id(),
                'estimate_nonce' => wp_create_nonce(self::NONCE),
            ],
            $this->quotePageUrl(),
        );

        return sprintf(
            '<a href="%1$s" class="%2$s">%3$s</a>',
            esc_url($href),
            esc_attr($classes),
            esc_html($added ? __('In your quote', 'estimate') : $label),
        );
    }

    /**
     * Add the product from the "Add to quote" link, then redirect to the quote
     * page.
     */
    public function handleAddLink(): void
    {
        if (! isset($_GET['estimate_add'], $_GET['estimate_nonce'])) {
            return;
        }

        $nonce = sanitize_text_field(wp_unslash($_GET['estimate_nonce']));

        if (! wp_verify_nonce($nonce, self::NONCE)) {
            return;
        }

        $productId = absint(wp_unslash($_GET['estimate_add']));
        $product   = $productId > 0 ? wc_get_product($productId) : null;

        if ($product instanceof \WC_Product && $this->isQuoteProduct($product)) {
            $this->list->add($productId, 1);
        }

        wp_safe_redirect($this->quotePageUrl());
        exit;
    }

    public function enqueueAssets(): void
    {
        wp_enqueue_style(
            'estimate',
            ESTIMATE_URL . 'assets/css/estimate.css',
            [],
            \Estimate\VERSION,
        );

        wp_enqueue_script(
            'estimate',
            ESTIMATE_URL . 'assets/js/estimate.js',
            [],
            \Estimate\VERSION,
            true,
        );
    }

    private function buttonLabel(): string
    {
        $custom = trim((string) ($this->settings()['button_text'] ?? ''));

        return '' !== $custom ? $custom : __('Add to quote', 'estimate');
    }

    /**
     * URL of the page holding the [estimate_quote] shortcode. Falls back to the
     * shop page (then home) so the no-JS flow always has somewhere to land.
     */
    private function quotePageUrl(): string
    {
        $pageId = (int) get_option('estimate_quote_page_id', 0);

        if ($pageId > 0 && 'publish' === get_post_status($pageId)) {
            return (string) get_permalink($pageId);
        }

        $shop = wc_get_page_id('shop');

        if ($shop > 0) {
            return (string) get_permalink($shop);
        }

        return home_url('/');
    }

    private function isEnabled(): bool
    {
        return (bool) ($this->settings()['enabled'] ?? false);
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
