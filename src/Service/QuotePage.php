<?php

declare(strict_types=1);

namespace Estimate\Service;

use Estimate\Contract\HasHooks;
use Estimate\PostType\QuoteRequest;

defined('ABSPATH') || exit;

/**
 * The [estimate_quote] page: shows the current quote list and a request form,
 * processes submissions, stores them as a private CPT and emails the merchant.
 *
 * The shortcode renders graceful states for an empty list and for a successful
 * submission, and never emits a fatal error if WooCommerce data is missing. All
 * output is escaped; all input is sanitised and nonce-verified.
 */
final class QuotePage implements HasHooks
{
    private const OPTION = 'estimate_settings';
    private const NONCE  = 'estimate_submit_quote';

    /** Quantity-update / removal nonce for the list controls. */
    private const LIST_NONCE = 'estimate_update_quote';

    /** @var array<string, string> Validation errors keyed by field. */
    private array $errors = [];

    /** @var array{name: string, email: string, company: string, message: string} */
    private array $values = ['name' => '', 'email' => '', 'company' => '', 'message' => ''];

    public function __construct(
        private readonly QuoteList $list,
        private readonly QuoteRequest $requests,
    ) {
    }

    public function registerHooks(): void
    {
        add_shortcode('estimate_quote', [$this, 'render']);
        add_action('template_redirect', [$this, 'maybeHandlePost']);
    }

    /**
     * Handle list updates (quantity / remove) and form submission early, before
     * any output, so cookies can be written and redirects can fire.
     */
    public function maybeHandlePost(): void
    {
        // Nonce is verified inside each handler before any data is processed.
        // List item updates / removals.
        if (isset($_POST['estimate_list_action'])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $this->handleListUpdate();
            return;
        }

        // Quote request submission.
        if (isset($_POST['estimate_submit'])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $this->handleSubmit();
        }
    }

    private function handleListUpdate(): void
    {
        $nonce = isset($_POST['estimate_list_nonce'])
            ? sanitize_text_field(wp_unslash($_POST['estimate_list_nonce']))
            : '';

        if (! wp_verify_nonce($nonce, self::LIST_NONCE)) {
            return;
        }

        $action = isset($_POST['estimate_list_action'])
            ? sanitize_key(wp_unslash($_POST['estimate_list_action']))
            : '';

        if (isset($_POST['estimate_remove'])) {
            $productId = absint(wp_unslash($_POST['estimate_remove']));
            if ($productId > 0) {
                $this->list->remove($productId);
            }
        } elseif ('update' === $action && isset($_POST['qty']) && is_array($_POST['qty'])) {
            // Sanitise each submitted quantity.
            $qtys = wp_unslash($_POST['qty']); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each value cast to int below.
            foreach ((array) $qtys as $productId => $qty) {
                $this->list->setQuantity(absint($productId), absint($qty));
            }
        }

        wp_safe_redirect(remove_query_arg(['estimate_sent']));
        exit;
    }

    private function handleSubmit(): void
    {
        $nonce = isset($_POST['estimate_nonce'])
            ? sanitize_text_field(wp_unslash($_POST['estimate_nonce']))
            : '';

        if (! wp_verify_nonce($nonce, self::NONCE)) {
            $this->errors['_form'] = __('Your session expired. Please try again.', 'estimate');
            return;
        }

        $this->values = [
            'name'    => isset($_POST['estimate_name']) ? sanitize_text_field(wp_unslash($_POST['estimate_name'])) : '',
            'email'   => isset($_POST['estimate_email']) ? sanitize_email(wp_unslash($_POST['estimate_email'])) : '',
            'company' => isset($_POST['estimate_company']) ? sanitize_text_field(wp_unslash($_POST['estimate_company'])) : '',
            'message' => isset($_POST['estimate_message']) ? sanitize_textarea_field(wp_unslash($_POST['estimate_message'])) : '',
        ];

        if ('' === $this->values['name']) {
            $this->errors['name'] = __('Please tell us your name.', 'estimate');
        }

        if ('' === $this->values['email'] || ! is_email($this->values['email'])) {
            $this->errors['email'] = __('Please enter a valid email address.', 'estimate');
        }

        $items = $this->lineItems();

        if ([] === $items) {
            $this->errors['_form'] = __('Your quote list is empty.', 'estimate');
        }

        if ([] !== $this->errors) {
            return;
        }

        $postId = $this->requests->create($this->values, $items);

        if ($postId > 0) {
            $this->notifyMerchant($postId, $this->values, $items);
            $this->list->clear();
        }

        wp_safe_redirect(add_query_arg('estimate_sent', '1', $this->currentUrl()));
        exit;
    }

    /**
     * Render the shortcode. Returns escaped HTML.
     */
    public function render(): string
    {
        if (! $this->isEnabled()) {
            return '';
        }

        $sent = isset($_GET['estimate_sent']) && '1' === sanitize_text_field(wp_unslash($_GET['estimate_sent'])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only confirmation flag.

        ob_start();

        echo '<div class="estimate-quote">';

        if ($sent) {
            $this->renderThankYou();
            echo '</div>';
            return (string) ob_get_clean();
        }

        $items = $this->lineItems();

        if ([] === $items) {
            $this->renderEmpty();
            echo '</div>';
            return (string) ob_get_clean();
        }

        $this->renderIntro();
        $this->renderList($items);
        $this->renderForm();

        echo '</div>';

        return (string) ob_get_clean();
    }

    private function renderIntro(): void
    {
        $intro = trim((string) ($this->settings()['quote_intro'] ?? ''));

        if ('' === $intro) {
            return;
        }

        echo '<div class="estimate-quote__intro">' . wp_kses_post(wpautop($intro)) . '</div>';
    }

    private function renderThankYou(): void
    {
        ?>
        <div class="estimate-quote__notice estimate-quote__notice--success" role="status">
            <h2><?php esc_html_e('Thank you — your request is on its way', 'estimate'); ?></h2>
            <p><?php esc_html_e('We have received your quote request and will get back to you shortly.', 'estimate'); ?></p>
        </div>
        <?php
    }

    private function renderEmpty(): void
    {
        $shop = wc_get_page_id('shop');
        $url  = $shop > 0 ? (string) get_permalink($shop) : home_url('/');
        ?>
        <div class="estimate-quote__empty">
            <h2><?php esc_html_e('Your quote list is empty', 'estimate'); ?></h2>
            <p><?php esc_html_e('Browse the shop and add products to build your quote request.', 'estimate'); ?></p>
            <p><a class="button" href="<?php echo esc_url($url); ?>"><?php esc_html_e('Browse products', 'estimate'); ?></a></p>
        </div>
        <?php
    }

    /**
     * @param array<int, array{product_id: int, name: string, qty: int}> $items
     */
    private function renderList(array $items): void
    {
        ?>
        <form method="post" class="estimate-quote__list-form">
            <?php wp_nonce_field(self::LIST_NONCE, 'estimate_list_nonce'); ?>
            <input type="hidden" name="estimate_list_action" value="update" />
            <table class="estimate-quote__table">
                <thead>
                    <tr>
                        <th scope="col"><?php esc_html_e('Product', 'estimate'); ?></th>
                        <th scope="col"><?php esc_html_e('Quantity', 'estimate'); ?></th>
                        <th scope="col"><span class="screen-reader-text"><?php esc_html_e('Remove', 'estimate'); ?></span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item) : ?>
                        <tr>
                            <td data-label="<?php esc_attr_e('Product', 'estimate'); ?>"><?php echo esc_html($item['name']); ?></td>
                            <td data-label="<?php esc_attr_e('Quantity', 'estimate'); ?>">
                                <label class="screen-reader-text" for="estimate-qty-<?php echo esc_attr((string) $item['product_id']); ?>">
                                    <?php esc_html_e('Quantity', 'estimate'); ?>
                                </label>
                                <input
                                    type="number"
                                    min="1"
                                    step="1"
                                    id="estimate-qty-<?php echo esc_attr((string) $item['product_id']); ?>"
                                    name="qty[<?php echo esc_attr((string) $item['product_id']); ?>]"
                                    value="<?php echo esc_attr((string) $item['qty']); ?>"
                                    class="estimate-quote__qty"
                                />
                            </td>
                            <td class="estimate-quote__remove-cell">
                                <button
                                    type="submit"
                                    name="estimate_remove"
                                    value="<?php echo esc_attr((string) $item['product_id']); ?>"
                                    class="estimate-quote__remove"
                                    aria-label="<?php
                                    /* translators: %s: product name */
                                    echo esc_attr(sprintf(__('Remove %s', 'estimate'), $item['name']));
                                    ?>"
                                    formnovalidate
                                ><span aria-hidden="true">&times;</span></button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p class="estimate-quote__list-actions">
                <button type="submit" class="button"><?php esc_html_e('Update quantities', 'estimate'); ?></button>
            </p>
        </form>
        <?php
    }

    private function renderForm(): void
    {
        if (isset($this->errors['_form'])) {
            printf(
                '<div class="estimate-quote__notice estimate-quote__notice--error" role="alert">%s</div>',
                esc_html($this->errors['_form']),
            );
        }
        ?>
        <form method="post" class="estimate-quote__form" novalidate>
            <h2><?php esc_html_e('Request your quote', 'estimate'); ?></h2>
            <?php wp_nonce_field(self::NONCE, 'estimate_nonce'); ?>

            <p class="estimate-quote__field">
                <label for="estimate-name"><?php esc_html_e('Name', 'estimate'); ?> <span class="estimate-quote__req" aria-hidden="true">*</span></label>
                <input type="text" id="estimate-name" name="estimate_name" required
                    value="<?php echo esc_attr($this->values['name']); ?>"
                    <?php echo isset($this->errors['name']) ? 'aria-invalid="true" aria-describedby="estimate-name-error"' : ''; ?> />
                <?php if (isset($this->errors['name'])) : ?>
                    <span class="estimate-quote__error" id="estimate-name-error"><?php echo esc_html($this->errors['name']); ?></span>
                <?php endif; ?>
            </p>

            <p class="estimate-quote__field">
                <label for="estimate-email"><?php esc_html_e('Email', 'estimate'); ?> <span class="estimate-quote__req" aria-hidden="true">*</span></label>
                <input type="email" id="estimate-email" name="estimate_email" required
                    value="<?php echo esc_attr($this->values['email']); ?>"
                    <?php echo isset($this->errors['email']) ? 'aria-invalid="true" aria-describedby="estimate-email-error"' : ''; ?> />
                <?php if (isset($this->errors['email'])) : ?>
                    <span class="estimate-quote__error" id="estimate-email-error"><?php echo esc_html($this->errors['email']); ?></span>
                <?php endif; ?>
            </p>

            <p class="estimate-quote__field">
                <label for="estimate-company"><?php esc_html_e('Company', 'estimate'); ?></label>
                <input type="text" id="estimate-company" name="estimate_company"
                    value="<?php echo esc_attr($this->values['company']); ?>" />
            </p>

            <p class="estimate-quote__field">
                <label for="estimate-message"><?php esc_html_e('Message', 'estimate'); ?></label>
                <textarea id="estimate-message" name="estimate_message" rows="5"><?php echo esc_textarea($this->values['message']); ?></textarea>
            </p>

            <p class="estimate-quote__submit">
                <button type="submit" name="estimate_submit" value="1" class="button alt"><?php esc_html_e('Send quote request', 'estimate'); ?></button>
            </p>
        </form>
        <?php
    }

    /**
     * Build line items from the current quote list, resolving live product data.
     *
     * @return array<int, array{product_id: int, name: string, qty: int}>
     */
    private function lineItems(): array
    {
        $items = [];

        foreach ($this->list->items() as $productId => $qty) {
            $product = wc_get_product($productId);

            if (! $product instanceof \WC_Product) {
                continue;
            }

            $items[] = [
                'product_id' => (int) $productId,
                'name'       => $product->get_name(),
                'qty'        => (int) $qty,
            ];
        }

        return $items;
    }

    /**
     * Email the merchant about a new quote request.
     *
     * @param array{name: string, email: string, company: string, message: string} $contact
     * @param array<int, array{product_id: int, name: string, qty: int}>            $items
     */
    private function notifyMerchant(int $postId, array $contact, array $items): void
    {
        $recipient = trim((string) ($this->settings()['recipient'] ?? ''));

        if ('' === $recipient || ! is_email($recipient)) {
            $recipient = (string) get_option('admin_email');
        }

        $lines   = [];
        $lines[] = sprintf(
            /* translators: %s: site name */
            __('A new quote request was submitted on %s.', 'estimate'),
            wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES),
        );
        $lines[] = '';
        $lines[] = __('Name:', 'estimate') . ' ' . $contact['name'];
        $lines[] = __('Email:', 'estimate') . ' ' . $contact['email'];

        if ('' !== $contact['company']) {
            $lines[] = __('Company:', 'estimate') . ' ' . $contact['company'];
        }

        $lines[] = '';
        $lines[] = __('Requested items:', 'estimate');

        foreach ($items as $item) {
            $lines[] = sprintf('- %1$s x %2$d', $item['name'], $item['qty']);
        }

        if ('' !== $contact['message']) {
            $lines[] = '';
            $lines[] = __('Message:', 'estimate');
            $lines[] = $contact['message'];
        }

        $editLink = get_edit_post_link($postId, 'raw');

        if (is_string($editLink) && '' !== $editLink) {
            $lines[] = '';
            $lines[] = __('View in admin:', 'estimate') . ' ' . $editLink;
        }

        $subject = sprintf(
            /* translators: %s: customer name or email */
            __('New quote request from %s', 'estimate'),
            '' !== $contact['name'] ? $contact['name'] : $contact['email'],
        );

        wp_mail(
            $recipient,
            $subject,
            implode("\n", $lines),
            ['Reply-To: ' . $contact['name'] . ' <' . $contact['email'] . '>'],
        );
    }

    private function currentUrl(): string
    {
        $pageId = get_queried_object_id();

        if ($pageId > 0) {
            $permalink = get_permalink($pageId);
            if (is_string($permalink)) {
                return $permalink;
            }
        }

        return home_url(add_query_arg([], ''));
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
