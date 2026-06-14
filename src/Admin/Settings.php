<?php

declare(strict_types=1);

namespace Estimate\Admin;

defined('ABSPATH') || exit;

use Estimate\Contract\HasHooks;

/**
 * Admin settings page registered as a WooCommerce submenu.
 *
 * Stores everything in the `estimate_settings` option (array): the master
 * toggle, quote mode (all / selected), whether to hide the price, the storefront
 * button text, the merchant recipient and an optional quote-page intro. All
 * output is escaped; all input is sanitised on save.
 */
final class Settings implements HasHooks
{
    private const OPTION = 'estimate_settings';
    private const PAGE   = 'estimate-settings';
    private const GROUP  = 'estimate_settings_group';

    private const MODES = ['selected', 'all'];

    /** Incremented to give each inline-help control a unique id/anchor. */
    private int $helpSeq = 0;

    public function registerHooks(): void
    {
        add_action('admin_menu', [$this, 'addMenuPage']);
        add_action('admin_init', [$this, 'registerSettings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function enqueueAssets(string $hook): void
    {
        if ('woocommerce_page_' . self::PAGE !== $hook) {
            return;
        }

        wp_enqueue_style(
            'estimate-admin',
            ESTIMATE_URL . 'assets/css/admin.css',
            [],
            \Estimate\VERSION,
        );
    }

    public function addMenuPage(): void
    {
        add_submenu_page(
            'woocommerce',
            __('Estimate — Request a Quote', 'estimate'),
            __('Estimate', 'estimate'),
            'manage_woocommerce',
            self::PAGE,
            [$this, 'renderPage'],
        );
    }

    public function registerSettings(): void
    {
        register_setting(
            self::GROUP,
            self::OPTION,
            [
                'type'              => 'array',
                'sanitize_callback' => [$this, 'sanitize'],
            ],
        );

        add_filter(
            'option_page_capability_' . self::GROUP,
            static fn (): string => 'manage_woocommerce',
        );
    }

    public function renderPage(): void
    {
        if (! current_user_can('manage_woocommerce')) {
            return;
        }

        $settings = $this->settings();
        $mode     = (string) ($settings['mode'] ?? 'selected');
        ?>
        <div class="wrap estimate-admin">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <div class="estimate-intro">
                <h2><?php esc_html_e('Let customers request a quote', 'estimate'); ?></h2>
                <p>
                    <?php esc_html_e('Turn products into quote requests instead of direct purchases — ideal for B2B, bulk or made-to-order items. Customers build a quote list and send you their details; each request is emailed to you and saved for review.', 'estimate'); ?>
                </p>
                <p>
                    <?php
                    printf(
                        /* translators: %s: shortcode wrapped in <code>. */
                        esc_html__('Add the %s shortcode to a page to show the quote list and request form.', 'estimate'),
                        '<code>[estimate_quote]</code>',
                    );
                    ?>
                </p>
            </div>

            <form method="post" action="options.php">
                <?php settings_fields(self::GROUP); ?>

                <div class="estimate-card">
                    <h2><?php esc_html_e('General', 'estimate'); ?></h2>
                    <table class="form-table" role="presentation">
                        <tbody>
                            <tr>
                                <th scope="row">
                                    <?php esc_html_e('Enable quote requests', 'estimate'); ?>
                                    <?php $this->help(__('The master switch. When off, nothing changes on your storefront and the quote button and page are not rendered.', 'estimate')); ?>
                                </th>
                                <td>
                                    <label for="estimate_enabled">
                                        <input type="checkbox" id="estimate_enabled"
                                            name="<?php echo esc_attr(self::OPTION); ?>[enabled]" value="1"
                                            <?php checked((bool) ($settings['enabled'] ?? false), true); ?> />
                                        <?php esc_html_e('Show quote requests on the storefront.', 'estimate'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="estimate_mode"><?php esc_html_e('Quote mode', 'estimate'); ?></label>
                                    <?php $this->help(__('"Selected products" turns the quote button on only for products you flag in the product editor. "All products" turns it on for your entire catalogue.', 'estimate')); ?>
                                </th>
                                <td>
                                    <select id="estimate_mode" name="<?php echo esc_attr(self::OPTION); ?>[mode]">
                                        <option value="selected" <?php selected($mode, 'selected'); ?>>
                                            <?php esc_html_e('Selected products only', 'estimate'); ?>
                                        </option>
                                        <option value="all" <?php selected($mode, 'all'); ?>>
                                            <?php esc_html_e('All products', 'estimate'); ?>
                                        </option>
                                    </select>
                                    <p class="description">
                                        <?php esc_html_e('In "selected" mode, enable individual products via the "Enable quote requests" checkbox in the product data box.', 'estimate'); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <?php esc_html_e('Hide price', 'estimate'); ?>
                                    <?php $this->help(__('Hide the product price on quote-enabled products so customers focus on requesting a quote. The add-to-cart button is always replaced regardless of this setting.', 'estimate')); ?>
                                </th>
                                <td>
                                    <label for="estimate_hide_price">
                                        <input type="checkbox" id="estimate_hide_price"
                                            name="<?php echo esc_attr(self::OPTION); ?>[hide_price]" value="1"
                                            <?php checked((bool) ($settings['hide_price'] ?? false), true); ?> />
                                        <?php esc_html_e('Hide the price on quote-enabled products.', 'estimate'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="estimate_button_text"><?php esc_html_e('Button text', 'estimate'); ?></label>
                                    <?php $this->help(__('The label shown on the storefront button. Leave blank to use the default, "Add to quote".', 'estimate')); ?>
                                </th>
                                <td>
                                    <input type="text" id="estimate_button_text" class="regular-text"
                                        name="<?php echo esc_attr(self::OPTION); ?>[button_text]"
                                        value="<?php echo esc_attr((string) ($settings['button_text'] ?? '')); ?>"
                                        placeholder="<?php esc_attr_e('Add to quote', 'estimate'); ?>" />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="estimate-card">
                    <h2><?php esc_html_e('Notifications', 'estimate'); ?></h2>
                    <table class="form-table" role="presentation">
                        <tbody>
                            <tr>
                                <th scope="row">
                                    <label for="estimate_recipient"><?php esc_html_e('Recipient email', 'estimate'); ?></label>
                                    <?php $this->help(__('Where new quote requests are emailed. Leave blank to use the site admin email.', 'estimate')); ?>
                                </th>
                                <td>
                                    <input type="email" id="estimate_recipient" class="regular-text"
                                        name="<?php echo esc_attr(self::OPTION); ?>[recipient]"
                                        value="<?php echo esc_attr((string) ($settings['recipient'] ?? '')); ?>"
                                        placeholder="<?php echo esc_attr((string) get_option('admin_email')); ?>" />
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="estimate_quote_intro"><?php esc_html_e('Quote page intro', 'estimate'); ?></label>
                                    <?php $this->help(__('Optional text shown above the quote list on the quote page. Basic HTML is allowed.', 'estimate')); ?>
                                </th>
                                <td>
                                    <textarea id="estimate_quote_intro" class="large-text" rows="3"
                                        name="<?php echo esc_attr(self::OPTION); ?>[quote_intro]"><?php
                                        echo esc_textarea((string) ($settings['quote_intro'] ?? ''));
                                    ?></textarea>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render an accessible inline-help affordance using the native Popover API.
     */
    private function help(string $text): void
    {
        $id = 'estimate-help-' . (++$this->helpSeq);
        ?>
        <button type="button" class="estimate-help"
            aria-label="<?php esc_attr_e('More information', 'estimate'); ?>"
            aria-describedby="<?php echo esc_attr($id); ?>"
            popovertarget="<?php echo esc_attr($id); ?>">?</button>
        <div id="<?php echo esc_attr($id); ?>" class="estimate-tip" role="tooltip" popover hidden>
            <?php echo esc_html($text); ?>
        </div>
        <?php
    }

    /**
     * Sanitise the submitted settings before save.
     *
     * @param mixed $raw
     * @return array<string, mixed>
     */
    public function sanitize(mixed $raw): array
    {
        if (! is_array($raw)) {
            $raw = [];
        }

        $mode = isset($raw['mode']) ? sanitize_key((string) $raw['mode']) : 'selected';

        if (! in_array($mode, self::MODES, true)) {
            $mode = 'selected';
        }

        $recipient = isset($raw['recipient']) ? sanitize_email((string) $raw['recipient']) : '';

        return [
            'enabled'     => ! empty($raw['enabled']),
            'mode'        => $mode,
            'hide_price'  => ! empty($raw['hide_price']),
            'button_text' => isset($raw['button_text']) ? sanitize_text_field((string) $raw['button_text']) : '',
            'recipient'   => is_email($recipient) ? $recipient : '',
            'quote_intro' => isset($raw['quote_intro']) ? wp_kses_post((string) $raw['quote_intro']) : '',
        ];
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
