<?php

declare(strict_types=1);

namespace Estimate\PostType;

use Estimate\Contract\HasHooks;

defined('ABSPATH') || exit;

/**
 * The private custom post type that stores submitted quote requests.
 *
 * Requests are not public — the CPT is registered with public => false and is
 * surfaced only in wp-admin under the WooCommerce menu. Each post stores the
 * customer's contact details and the requested line items as post meta, and the
 * message body as post content.
 */
final class QuoteRequest implements HasHooks
{
    public const POST_TYPE = 'estimate_quote';

    public const META_NAME    = '_estimate_name';
    public const META_EMAIL   = '_estimate_email';
    public const META_COMPANY = '_estimate_company';
    public const META_ITEMS   = '_estimate_items';

    public function registerHooks(): void
    {
        $this->register();

        if (is_admin()) {
            add_filter('manage_' . self::POST_TYPE . '_posts_columns', [$this, 'columns']);
            add_action('manage_' . self::POST_TYPE . '_posts_custom_column', [$this, 'renderColumn'], 10, 2);
            add_action('add_meta_boxes', [$this, 'addMetaBox']);
        }
    }

    /**
     * Register the post type. Called directly (not only via hook) so it is
     * available immediately during boot on the init action.
     */
    public function register(): void
    {
        if (post_type_exists(self::POST_TYPE)) {
            return;
        }

        register_post_type(
            self::POST_TYPE,
            [
                'labels'              => [
                    'name'               => __('Quote Requests', 'estimate'),
                    'singular_name'      => __('Quote Request', 'estimate'),
                    'menu_name'          => __('Quote Requests', 'estimate'),
                    'all_items'          => __('Quote Requests', 'estimate'),
                    'edit_item'          => __('View Quote Request', 'estimate'),
                    'view_item'          => __('View Quote Request', 'estimate'),
                    'search_items'       => __('Search quote requests', 'estimate'),
                    'not_found'          => __('No quote requests found.', 'estimate'),
                    'not_found_in_trash' => __('No quote requests in Trash.', 'estimate'),
                ],
                'public'              => false,
                'show_ui'             => true,
                'show_in_menu'        => 'woocommerce',
                'show_in_rest'        => false,
                'exclude_from_search' => true,
                'publicly_queryable'  => false,
                'has_archive'         => false,
                'rewrite'             => false,
                'query_var'           => false,
                'hierarchical'        => false,
                'menu_icon'           => 'dashicons-media-spreadsheet',
                'supports'            => ['title', 'editor'],
                'capability_type'     => 'post',
                'map_meta_cap'        => true,
                'capabilities'        => [
                    'create_posts' => 'do_not_allow',
                ],
            ],
        );
    }

    /**
     * Persist a quote request. Returns the new post ID, or 0 on failure.
     *
     * @param array{name: string, email: string, company: string, message: string} $contact
     * @param array<int, array{product_id: int, name: string, qty: int}>            $items
     */
    public function create(array $contact, array $items): int
    {
        $title = sprintf(
            /* translators: 1: customer name, 2: human-readable date */
            __('Quote from %1$s — %2$s', 'estimate'),
            '' !== $contact['name'] ? $contact['name'] : $contact['email'],
            wp_date(get_option('date_format') . ' ' . get_option('time_format')),
        );

        $postId = wp_insert_post(
            [
                'post_type'    => self::POST_TYPE,
                'post_status'  => 'private',
                'post_title'   => $title,
                'post_content' => $contact['message'],
            ],
            true,
        );

        if (is_wp_error($postId) || 0 === $postId) {
            return 0;
        }

        update_post_meta($postId, self::META_NAME, $contact['name']);
        update_post_meta($postId, self::META_EMAIL, $contact['email']);
        update_post_meta($postId, self::META_COMPANY, $contact['company']);
        update_post_meta($postId, self::META_ITEMS, $items);

        return (int) $postId;
    }

    /**
     * @param array<string, string> $columns
     * @return array<string, string>
     */
    public function columns(array $columns): array
    {
        $reordered = [];

        foreach ($columns as $key => $label) {
            if ('date' === $key) {
                $reordered['estimate_email']   = __('Email', 'estimate');
                $reordered['estimate_company'] = __('Company', 'estimate');
                $reordered['estimate_items']   = __('Items', 'estimate');
            }

            $reordered[$key] = $label;
        }

        return $reordered;
    }

    public function renderColumn(string $column, int $postId): void
    {
        switch ($column) {
            case 'estimate_email':
                $email = (string) get_post_meta($postId, self::META_EMAIL, true);
                if ('' !== $email) {
                    printf('<a href="%1$s">%2$s</a>', esc_url('mailto:' . $email), esc_html($email));
                } else {
                    echo '—';
                }
                break;

            case 'estimate_company':
                echo esc_html((string) get_post_meta($postId, self::META_COMPANY, true) ?: '—');
                break;

            case 'estimate_items':
                $items = get_post_meta($postId, self::META_ITEMS, true);
                echo esc_html((string) (is_array($items) ? count($items) : 0));
                break;
        }
    }

    public function addMetaBox(): void
    {
        add_meta_box(
            'estimate_quote_details',
            __('Quote details', 'estimate'),
            [$this, 'renderMetaBox'],
            self::POST_TYPE,
            'normal',
            'high',
        );
    }

    public function renderMetaBox(\WP_Post $post): void
    {
        $name    = (string) get_post_meta($post->ID, self::META_NAME, true);
        $email   = (string) get_post_meta($post->ID, self::META_EMAIL, true);
        $company = (string) get_post_meta($post->ID, self::META_COMPANY, true);
        $items   = get_post_meta($post->ID, self::META_ITEMS, true);
        $items   = is_array($items) ? $items : [];
        ?>
        <table class="widefat striped" style="margin-bottom:1em">
            <tbody>
                <tr>
                    <th style="width:160px"><?php esc_html_e('Name', 'estimate'); ?></th>
                    <td><?php echo esc_html('' !== $name ? $name : '—'); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Email', 'estimate'); ?></th>
                    <td>
                        <?php if ('' !== $email) : ?>
                            <a href="<?php echo esc_url('mailto:' . $email); ?>"><?php echo esc_html($email); ?></a>
                        <?php else : ?>
                            —
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Company', 'estimate'); ?></th>
                    <td><?php echo esc_html('' !== $company ? $company : '—'); ?></td>
                </tr>
            </tbody>
        </table>

        <h3><?php esc_html_e('Requested items', 'estimate'); ?></h3>
        <?php if ([] === $items) : ?>
            <p><?php esc_html_e('No items recorded.', 'estimate'); ?></p>
        <?php else : ?>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Product', 'estimate'); ?></th>
                        <th style="width:120px"><?php esc_html_e('Quantity', 'estimate'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item) :
                        $productId = isset($item['product_id']) ? absint($item['product_id']) : 0;
                        $itemName  = isset($item['name']) ? (string) $item['name'] : '';
                        $qty       = isset($item['qty']) ? absint($item['qty']) : 1;
                        $editLink  = $productId > 0 ? get_edit_post_link($productId) : '';
                        ?>
                        <tr>
                            <td>
                                <?php if ($editLink) : ?>
                                    <a href="<?php echo esc_url($editLink); ?>"><?php echo esc_html($itemName); ?></a>
                                <?php else : ?>
                                    <?php echo esc_html($itemName); ?>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html((string) $qty); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <?php
    }
}
