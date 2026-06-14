=== Estimate - Request a Quote for WooCommerce ===
Contributors: wppoland
Tags: woocommerce, request a quote, quote, b2b, hide price
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 8.1
Requires Plugins: woocommerce
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Let customers request a quote instead of buying directly — ideal for B2B and made-to-order.

== Description ==

Estimate turns WooCommerce products into quote requests. Instead of an
add-to-cart button, quote-enabled products show an **Add to quote** button (and,
optionally, hide the price). Customers build a quote list, then send you their
details from a simple request form. Each request is emailed to you and saved as a
private record you can review in wp-admin.

It is ideal for B2B stores, wholesale, bulk orders and made-to-order products
where prices are negotiated rather than fixed.

= Features =

* Two quote modes: enable quotes for **selected products** or for **all products**.
* Per-product toggle in the product editor (selected mode).
* Replaces the add-to-cart button with an **Add to quote** button on product pages and listings.
* Optionally hides the price on quote-enabled products.
* Per-visitor quote list (cookie-based) — works for logged-out shoppers, no account needed.
* A `[estimate_quote]` shortcode that shows the quote list and a request form (name, email, company, message).
* Quantity editing and item removal on the quote page.
* On submit: emails the merchant and stores the request as a private custom post type.
* Configurable recipient email and storefront button text.
* Accessible, mobile-friendly storefront markup.
* Translation ready (POT included) and clean uninstall.
* HPOS and cart/checkout blocks compatible.

= The [estimate_quote] shortcode =

Create a page (e.g. "Request a Quote") and add the shortcode:

`[estimate_quote]`

The page shows the current quote list and the request form. If a visitor's list
is empty, a friendly empty state links back to the shop.

== Installation ==

1. Upload the plugin to `/wp-content/plugins/estimate`, or install via Plugins → Add New.
2. Activate it. WooCommerce must be active.
3. Go to **WooCommerce → Estimate** and choose your quote mode and options.
4. Create a page with the `[estimate_quote]` shortcode to host the quote list and request form.
5. In "selected" mode, edit a product and tick **Enable quote requests** in the Product data box.

== Frequently Asked Questions ==

= Does it require WooCommerce? =

Yes. WooCommerce must be installed and active.

= Where do quote requests go? =

Each submission is emailed to the recipient you set (or the site admin email by
default) and saved as a private "Quote Request" record under the WooCommerce
menu in wp-admin.

= Can I enable quotes for only some products? =

Yes. Set the quote mode to "Selected products only" and tick **Enable quote
requests** on each product you want. Choose "All products" to apply it store-wide.

= Does the quote list work for logged-out visitors? =

Yes. The list is stored in a cookie per visitor, so no account is required.

== Screenshots ==

1. The Add to quote button replacing add-to-cart on a product.
2. The quote page: list, quantities and the request form.
3. The Estimate settings screen under WooCommerce.
4. A saved quote request in wp-admin.

== Changelog ==

= 0.1.0 =
* Initial release: quote modes (selected/all), Add to quote button, price hiding, per-visitor quote list, `[estimate_quote]` page with request form, merchant email and a private quote-request record.
