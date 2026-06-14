# Estimate - Request a Quote for WooCommerce

Estimate turns WooCommerce products into quote requests. Instead of an add-to-cart button,
quote-enabled products show an "Add to quote" button (and can optionally hide the price). Customers
build a quote list and send you their details from a simple form — ideal for B2B, wholesale, bulk
orders and made-to-order products.

## Features

- Two quote modes: enable quotes for selected products or for all products.
- Replaces the add-to-cart button with an "Add to quote" button on product pages and listings.
- Optionally hides the price on quote-enabled products.
- Per-visitor quote list (cookie-based), so logged-out shoppers can use it without an account.
- An `[estimate_quote]` shortcode that shows the quote list and a request form.
- On submit: emails the merchant and stores the request as a private record in wp-admin.
- Accessible, mobile-friendly markup with a no-JavaScript fallback.

## Installation

1. Upload the plugin to `/wp-content/plugins/estimate`, or install it via Plugins → Add New.
2. Activate it. WooCommerce must be active.
3. Go to WooCommerce → Estimate and choose your quote mode and options.
4. Create a page with the `[estimate_quote]` shortcode to host the quote list and request form.

## Frequently Asked Questions

**Does it require WooCommerce?**
Yes. WooCommerce must be installed and active.

**Where do quote requests go?**
Each submission is emailed to the recipient you set (or the site admin email by default) and saved
as a private "Quote Request" record under the WooCommerce menu in wp-admin.

Built by WPPoland — https://plogins.com

License: GPL-2.0-or-later
