<?php

declare(strict_types=1);

namespace Estimate\Service;

use Estimate\Contract\HasHooks;

defined('ABSPATH') || exit;

/**
 * Per-visitor quote list.
 *
 * Items are stored in a single cookie as a compact map of product ID => quantity.
 * No personal data is kept and nothing is written server-side until the visitor
 * submits the request form, so this works for logged-out shoppers and is cache
 * friendly. The cookie is read on demand and written via the standard headers,
 * so the list survives navigation for the configured lifetime.
 */
final class QuoteList implements HasHooks
{
    private const COOKIE = 'estimate_quote_items';

    /** Cookie lifetime in seconds (30 days). */
    private const LIFETIME = 30 * DAY_IN_SECONDS;

    /** Hard cap on distinct line items to keep the cookie small and sane. */
    private const MAX_ITEMS = 100;

    /**
     * In-request cache so repeated reads (and writes within the same request)
     * stay consistent even though the cookie superglobal is only sent next load.
     *
     * @var array<int, int>|null
     */
    private ?array $items = null;

    public function registerHooks(): void
    {
        // Nothing to hook: the list is read/written on demand. Implementing
        // HasHooks keeps it uniform with the rest of the service registry.
    }

    /**
     * Current items as product ID => quantity, skipping anything no longer
     * purchasable so the list never shows stale or deleted products.
     *
     * @return array<int, int>
     */
    public function items(): array
    {
        $items = $this->raw();
        $clean = [];

        foreach ($items as $productId => $qty) {
            $product = wc_get_product($productId);

            if (! $product instanceof \WC_Product) {
                continue;
            }

            $clean[$productId] = $qty;
        }

        return $clean;
    }

    public function count(): int
    {
        return count($this->items());
    }

    public function has(int $productId): bool
    {
        return isset($this->raw()[$productId]);
    }

    /**
     * Add a product (or increase its quantity). Returns the new line quantity.
     */
    public function add(int $productId, int $qty = 1): int
    {
        $productId = absint($productId);
        $qty       = max(1, $qty);

        if ($productId <= 0) {
            return 0;
        }

        $items = $this->raw();

        if (! isset($items[$productId]) && count($items) >= self::MAX_ITEMS) {
            return 0;
        }

        $items[$productId] = ($items[$productId] ?? 0) + $qty;

        $this->persist($items);

        return $items[$productId];
    }

    public function setQuantity(int $productId, int $qty): void
    {
        $productId = absint($productId);
        $items     = $this->raw();

        if ($qty <= 0) {
            unset($items[$productId]);
        } elseif (isset($items[$productId])) {
            $items[$productId] = $qty;
        }

        $this->persist($items);
    }

    public function remove(int $productId): void
    {
        $items = $this->raw();
        unset($items[absint($productId)]);
        $this->persist($items);
    }

    public function clear(): void
    {
        $this->persist([]);
    }

    /**
     * Raw stored map (product ID => quantity), unfiltered by product validity.
     *
     * @return array<int, int>
     */
    private function raw(): array
    {
        if (null !== $this->items) {
            return $this->items;
        }

        $this->items = [];

        $raw = isset($_COOKIE[self::COOKIE])
            ? sanitize_text_field(wp_unslash($_COOKIE[self::COOKIE]))
            : '';

        if ('' === $raw) {
            return $this->items;
        }

        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            return $this->items;
        }

        foreach ($decoded as $productId => $qty) {
            $productId = absint($productId);
            $qty       = absint($qty);

            if ($productId > 0 && $qty > 0) {
                $this->items[$productId] = $qty;
            }
        }

        return $this->items;
    }

    /**
     * @param array<int, int> $items
     */
    private function persist(array $items): void
    {
        $items        = array_slice($items, 0, self::MAX_ITEMS, true);
        $this->items  = $items;
        $value        = '' === ($json = (string) wp_json_encode($items)) ? '' : $json;

        // Don't try to send headers if output already started (e.g. shortcode in
        // body). The in-request cache still keeps this request consistent.
        if (headers_sent()) {
            return;
        }

        $path   = defined('COOKIEPATH') && '' !== COOKIEPATH ? COOKIEPATH : '/';
        $domain = defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : '';

        if ([] === $items) {
            setcookie(self::COOKIE, '', time() - DAY_IN_SECONDS, $path, $domain, is_ssl(), true);
            return;
        }

        setcookie(
            self::COOKIE,
            $value,
            time() + self::LIFETIME,
            $path,
            $domain,
            is_ssl(),
            true,
        );
    }
}
