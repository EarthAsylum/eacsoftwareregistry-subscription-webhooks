=== {eac}SoftwareRegistry Subscriptions for WooCommerce ===
Plugin URI:             https://swregistry.earthasylum.com/subscriptions-for-woocommerce/
Author:                 [EarthAsylum Consulting](https://www.earthasylum.com)
Stable tag:             2.1.0
Last Updated:           25-Jul-2024
Requires at least:      5.8.0
Tested up to:           6.6
WC requires at least:   7.0
WC tested up to:        9.2
Requires PHP:           7.4
Contributors:           kevinburkholder
License:                GPLv3 or later
License URI:            https://www.gnu.org/licenses/gpl.html
Tags:                   WooCommerce Subscriptions, WooCommerce Webhooks, SUMO subscriptions, subscriptions, {eac}SoftwareRegistry
WordPress URI:          https://wordpress.org/plugins/eacsoftwareregistry-subscription-webhooks/
Github URI:             https://github.com/EarthAsylum/eacsoftwareregistry-subscription-webhooks/

Adds a custom Webhook topic to WooCommerrce Webhooks for subscription updates; adds subscription and product data to WooCommerce order Webhooks.

== Description ==

**{eac}SoftwareRegistry Subscriptions for WooCommerce** is a plugin, installed on your WooCommerce site, that adds a custom Webhook topic for subscription updates to the WooCommerrce Webhooks, and adds subscription and product data to WooCommerce order Webhooks.

+   Adds a custom Webhook topic for subscription updates to WooCommerce Webhooks.

+   Extends the WooCommerce Order Webhooks by adding subscription data to orders with subscriptions.

+   Adds product meta data to order and subscription records passed through WooCommerce webhooks.

+   Works with WooCommerce Subscriptions - and - SUMO Subscriptions.

When WooCommerce creates an order, the order is stored as a post with type set to "*shop_order*". When the order is for a subscription, a related order is stored as a post with type set to "*shop_subscription*". When a subscription is renewed, a new "*shop_order*" is created related back to the original "*shop_subscription*" order.

When subscriptions (*shop_subscription*) are passed through the *"{eac}SoftwareRegistry Subscription updated"* webhook, additional subscription data and related order numbers are added to the subscription order record being passed.

When orders (*shop_order*) are passed through the WooCommerce *Order created*, *Order updated*, and *Order restored* webhooks, this plugin will append any related *shop_subscription* orders with the additional subscription data and related order numbers.

Meta data (custom fields and attributes) from the products in the order may be appended to the order and subscription records.

For order webhooks, options are presented on the "Webhook" edit screen to choose what data may be added to the orders so that extended data is only retrieved and sent through the webhook where needed.

WooCommerce Webhooks are created by going to: *WooCommerce → Settings → Advanced → Webhooks* in the administration of your store site.

With version 2+, *SUMO Subscriptions* is also supported in nearly the same way as WooCommerce Subscriptions by creating a pseudo *shop_subscription* order from the SUMO Subscription post record and the original or renewal WooCommerce *shop_order*.


= Subscriptions =

To create a webhook for subscription updates, choose *"{eac}SoftwareRegistry Subscription updated"*, when using Woo Subscriptions, or *"{eac}SoftwareRegistry Sumo Subscription"*, when using SUMO Subscriptions, for the topic on the *Webhook data* screen.

Whenever a subscription is updated, the subscription data will be sent to the *Delivery URL* specified in the Webhook.


= Orders =

This plugin also adds subscription data to orders passed through the *Order created*, *Order updated*, and *Order restored* webhooks when the order has related subscription(s).

Orders without subscriptions may be appended with meta data from the products in the order.


= Subscription Data =

The subscription data added (overlayed on the *shop_subscription* record) in the webhooks is:

      'date_created'                => datetime     // 'YYYY-MM-DDThh:mm:ss',
      'date_modified'               => datetime     // 'YYYY-MM-DDThh:mm:ss',
      'date_paid'                   => datetime     // 'YYYY-MM-DDThh:mm:ss',
      'date_completed'              => datetime     // 'YYYY-MM-DDThh:mm:ss',
      'last_order_id'               => int          // last completed order id,
      'last_order_date_created'     => datetime     // 'YYYY-MM-DDThh:mm:ss',
      'last_order_date_paid'        => datetime     // 'YYYY-MM-DDThh:mm:ss',
      'last_order_date_completed'   => datetime     // 'YYYY-MM-DDThh:mm:ss',
      'schedule_trial_end'          => datetime     // 'YYYY-MM-DDThh:mm:ss',
      'schedule_start'              => datetime     // 'YYYY-MM-DDThh:mm:ss',
      'schedule_end'                => datetime     // 'YYYY-MM-DDThh:mm:ss',
      'schedule_cancelled'          => datetime     // 'YYYY-MM-DDThh:mm:ss',
      'schedule_next_payment'       => datetime     // 'YYYY-MM-DDThh:mm:ss',
      'schedule_payment_retry'      => datetime     // 'YYYY-MM-DDThh:mm:ss',
      'billing_period'              => string       // 'day','month','year',
      'billing_interval'            => int          // number of days,months,years,
      'sign_up_fee'                 => float        // signup fee amount,
      'product_meta'                => array        // [ product_id => [product_meta_data] ]
      'related_orders'              => array        // [ order_id => type ('parent','renewal','resubscribe','switch') ]

product_meta includes:

      'id'                          => int          // product id,
      'name'                        => string       // product name,
      'slug'                        => string       // product slug,
      'sku'                         => string       // product sku,
      'attributes'                  => array        // product attributes (name => value)
      'meta_data'                   => array        // product custom fields (name => value)
      'categories'                  => array        // product categories (slug => name)


For the subscription webhook, this data is overlayed on the *subscription* order created by WooCommerce.

For the order webhooks, this data is overlayed on the related *subscription* order and appended to the *shop_order* in a "subscriptions" array, indexed by id (allowing for multiple subscriptions per order).

For orders without subscriptions, the product_meta array is appended to the *shop_order*.


= SUMO Subscriptions =

The pseudo subscription order is built by taking the SUMO subscription post record and overlaying the most recent *shop_order*. The 'id' number of the pseudo order is the subscription post id. The parent id is the original *shop_order* that created the subscription.


= Using With {eac}SoftwareRegistry Registration Server =

You must have the [{eac}SoftwareRegistry WebHooks for WooCommerce](https://swregistry.earthasylum.com/webhooks-for-woocommerce/) extension enabled on your Software Registration server.

When creating a subscription webhook, the *Delivery URL* for *"{eac}SoftwareRegistry Subscription updated"* and *"{eac}SoftwareRegistry Sumo Subscription"* is:
`https://{your_registration_server}.com/wp-json/softwareregistry/v1/wc-subscription`

When creating order webhooks, the *Delivery URL* for *Order created*, *Order updated*, *Order deleted* and *Order restored* is:
`https://{your_registration_server}.com/wp-json/softwareregistry/v1/wc-order`

With this configuration, you can pass registry values (registry_*) in the product_meta array by creating custom fields on the product record and overriding the registration server defaults. For example:

    registry_product = package_name
    registry_license = Basic

See [{eac}SoftwareRegistry WebHooks for WooCommerce](https://swregistry.earthasylum.com/webhooks-for-woocommerce/) for more information.


== Installation ==

This plugin is intended to be installed on your WooCommerce store site (not necessarily your software registration server).

= Automatic Plugin Installation =

This plugin is available from the [WordPress Plugin Repository](https://wordpress.org/plugins/search/earthasylum/) and can be installed from the WordPress Dashboard » *Plugins* » *Add New* page. Search for 'EarthAsylum', click the plugin's [Install] button and, once installed, click [Activate].

See [Managing Plugins -> Automatic Plugin Installation](https://wordpress.org/support/article/managing-plugins/#automatic-plugin-installation-1)

= Upload via WordPress Dashboard =

Installation of this plugin can be managed from the WordPress Dashboard » *Plugins* » *Add New* page. Click the [Upload Plugin] button, then select the eacsoftwareregistry-subscription-webhooks.zip file from your computer.

See [Managing Plugins -> Upload via WordPress Admin](https://wordpress.org/support/article/managing-plugins/#upload-via-wordpress-admin)

= Manual Plugin Installation =

You can install the plugin manually by extracting the eacsoftwareregistry-subscription-webhooks.zip file and uploading the 'eacsoftwareregistry-subscription-webhooks' folder to the 'wp-content/plugins' folder on your WordPress server.

See [Managing Plugins -> Manual Plugin Installation](https://wordpress.org/support/article/managing-plugins/#manual-plugin-installation-1)

= Settings =

Options for this plugin will be found on the *WooCommerce → Settings → Advanced → Webhooks* page.


== Screenshots ==

1. WooCommerce > Settings > Advanced > Webhooks
![{eac}SoftwareRegistry Subscriptions for WooCommerce](https://ps.w.org/eacsoftwareregistry-subscription-webhooks/assets/screenshot-1.png)

1. Options for Order Webhooks
![{eac}SoftwareRegistry Subscriptions for WooCommerce](https://ps.w.org/eacsoftwareregistry-subscription-webhooks/assets/screenshot-2.png)


== Other Notes ==

= Additional Information =

Requires [WooCommerce](https://woocommerce.com/) and either [WooCommerce Payments](https://woocommerce.com/payments/) (with subscriptions), [WooCommerce Subscriptions](https://woocommerce.com/document/subscriptions/) or [SUMO Subscriptions](https://codecanyon.net/item/sumo-subscriptions-woocommerce-subscription-system/16486054).

+   Developed for use with [{eac}SoftwareRegistry Registration Server](https://swregistry.earthasylum.com/).
+   Nonetheless can be used wherever subscriptions or additional product details are needed in WooCommerce webhooks.

= See Also =

+   [{eac}SoftwareRegistry – Software Registration Server](https://swregistry.earthasylum.com/software-registration-server/)
+   [{eac}SoftwareRegistry WebHooks for WooCommerce](https://swregistry.earthasylum.com/webhooks-for-woocommerce/)


== Copyright ==

= Copyright © 2024, EarthAsylum Consulting, distributed under the terms of the GNU GPL. =

This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should receive a copy of the GNU General Public License along with this program. If not, see [https://www.gnu.org/licenses/](https://www.gnu.org/licenses/).


== Changelog ==

= Version 2.1.0 – July 25, 2024 =

+   Compatible with WooCommerce v9+ and HPOS (High Performance Order Storage).
+   Supports new WooCommerce RestAPI as well as Legacy API (if enabled).
+   Compatible with WordPress 6.6.
+   Get available post ids from parent order.
+   Updated minimum requirements: WP 5.8, WC 7.0, PHP 7.4.
+   Updated translator name (but still no translations).

= Version 2.0.1 – April 13, 2024 =

+   Fix/use proper actions for SUMO Subscriptions.

= Version 2.0.0 – April 4, 2024 =

+   Supportes SUMO Subscriptions.
+   Added 'current_action' to the webhook data.
+   Added 'categories' to product_meta.
+   Compatible with WordPress 6.5+ and WooCommerce 8.7+

= Version 1.0.9 – November 11, 2022 =

+   Cosmetic changes, tested WordPress 6.1 WooCommerce 7.0.

= Version 1.0.8 – September 30, 2022 =

+   Fixed potential PHP notice on load (plugin_action_links_).

= Version 1.0.7 – August 28, 2022 =

+   Added 'Settings', 'Docs' and 'Support' links on plugins page.

= Version 1.0.6 – July 2, 2022 =

+   Explicitly validate user input from webhooks form.

= Version 1.0.5 – July 1, 2022 =

+   Fixed product meta data overwrite on variable product.
+   Cosmetic changes (readme) for WordPress submission.

= Version 1.0.4 – May 19, 2022 =

+   Support (strip) variation attributes with "pa_" prefix.

= Version 1.0.3 – May 4, 2022 =

+   Fixes for dates and schedules, and order meta data.

= Version 1.0.2 – May 2, 2022 =

+   Added webhook options.

= Version 1.0.1 – April 29, 2022 =

+   Added product_meta (including non-subscription orders)

= Version 1.0.0 – April 22, 2022 =

+   Initial release.

