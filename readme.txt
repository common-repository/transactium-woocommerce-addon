=== Transactium WooCommerce AddOn ===
Contributors: transactiumdev
Donate link: http://transactium.com
Tags: transactium, woocommerce, payment, payments, ecommerce
Requires at least: 3.9
Tested up to: 6.5.5
Stable tag: 1.16
License: GPLv2 or later
WC requires at least: 2.4
WC tested up to: 9.1

Spark the most flexible eCommerce solution for WordPress, WooCommerce, and process payments via Transactium EZPay!

== Description ==

Transact securely from your WordPress site with [Transactium](http://transactium.com) - no coding required.

More to add in future versions!


Current Features
 
* accept one-time secure payments
* issue refunds with a click of a button
* support subscriptions via [WooCommerce Subscriptions](https://woocommerce.com/products/woocommerce-subscriptions/#) (not included)
* integrate with 3DSecure
* transact with VISA or MasterCard payment methods
* all payments are *PCI compliant*

Straight forward set-up. No coding required.

> **Transactium WooCommerce AddOn integrates with _[WooCommerce](https://www.woocommerce.com)_ — the most popular WordPress platform for eCommerce - to allow customers to checkout with Transactium EZPay.**
>
> **Optionally, download the _[WooCommerce Subscriptions](https://woocommerce.com/products/woocommerce-subscriptions/#)_ plugin to support subscription type payments.**
>
> **[Download](https://downloads.wordpress.org/plugin/transactium-woocommerce-addon.zip)**

== Support ==

> **Problems? Require special customisations? [Contact Us](http://support.transactium.com/support/tickets/new)**

== Current Limitations ==



== Installation ==

This section describes how to install and setup the Transactium WooCommerce Addon. Be sure to follow *all* of the instructions in order for the add-on to work properly. If you're unsure of any step, there are [screenshots](https://wordpress.org/plugins/transactium-woocommerce-addon/screenshots/).

### Requirements

Requires at least WordPress 3.9, PHP 5.5 and _[WooCommerce](https://www.woocommerce.com)_ 2.4.

### Steps
 
1. Make sure you have your own copy of _[WooCommerce](https://www.woocommerce.com)_ set up and running.

2. You'll also need a [Transactium EZPay](http://support.transactium.com/support/tickets/new) account

3. Upload the plugin to your WordPress site. There are three ways to do this:

    * **WordPress dashboard search**

        - In your WordPress dashboard, go to the **Plugins** menu and click the _Add New_ button
        - Search for `Transactium WooCommerce AddOn`
        - Click to install the plugin

    * **WordPress dashboard upload**

        - Download the plugin zip file by clicking the orange download button on this page
        - In your WordPress dashboard, go to the **Plugins** menu and click the _Add New_ button
        - Click the _Upload_ link
        - Click the _Choose File_ button to upload the zip file you just downloaded

    * **FTP upload**

        - Download the plugin zip file by clicking the orange download button on this page
        - Unzip the file you just downloaded
        - FTP in to your site
        - Upload the `transactium-woocommerce-addon` folder to the `/wp-content/plugins/` directory

4. Visit the **Plugins** menu in your WordPress dashboard, find `Transactium WooCommerce AddOn` in your plugin list, and click the _Activate_ link.

5. Visit the **WooCommerce->Settings** from the admin menu, select the Checkout tab and the inner _Transactium EZPay_ menu link respectively. Here input your Transactium account information. Save your settings.

6. Select the _General_ tab and set your desired currency. This will be the currency used for your product transactions.

7. On checkout, there should now be the Transactium EZPay payment method (defaults to: "Credit Card") as an option.

If you need help, try checking the [screenshots](https://wordpress.org/plugins/transactium-woocommerce-addon/screenshots/)

== Frequently Asked Questions ==

= Do I need to have my own copy of WooCommerce for this plugin to work? =
Yes, you need to install the [WooCommerce plugin](https://www.woocommerce.com/ "visit the WooCommerce website") for this plugin to work.

= Does this version work with the latest version of WooCommerce? =
This plugin was developed to target WooCommerce version 2.4 and later. It has not been tested on previous versions of WooCommerce.

= Your plugin just does not work =
Please contact [support](http://support.transactium.com/support/tickets/new).

== Screenshots ==

1. Activate Transactium WooCommerce AddOn

2. Transactium EZPay settings page under **WooCommerce->Settings->Checkout->Transactium EZPay**.

3. Currency setting in **WooCommerce->Settings->General**

4. End Result on Checkout

== Changelog ==

= 1.16 =
* Update : Fix BUG introduced in 1.15

= 1.15 =
* Update : Added IPN support for notifications

= 1.14 =
* Update : Removed obsolete methods (3ds v1)
* Update : Added proper client reference to all transactions

= 1.13 =
* Update : Added more escaping and removed unused code

= 1.12 =
* Update : Added escaping and extra input validation

= 1.11 =
* Update : Updated Saved card feature to work with recent versions

= 1.10 =
* Update : ASYNC transaction fix for BOV

= 1.9 =
* Update : Added ASYNC support

= 1.8 =
* Update : update stylesheet to mask cvv

= 1.7 =
* Update : fix for async transaction failures

= 1.6 =
* Update : Added support for async
* Update : Removed amount from HOST method to facilitate order amount manipulation from 3rd party plugins

= 1.5 =
* Bugfix : Fixed compatability with woocommerce 3.5.1

= 1.4 (2017-09-11 =
* Bugfix : Fixed problem "result code invalid"
* Update : Setup CVV as mandatory on repeat payments

= 1.3 (2017-05-29) =
* Added an optional Card Details section to be shown on order completion

= 1.2 (2017-04-11) =
* Added backwards compatibility for WooCommerce 2.4 and up

= 1.1 (2017-04-11) =
* Added support for WooCommerce 3

= 1.0 (2017-04-04) =
* Initial release.

== Upgrade Notice ==
= 1.7 =
* Update : fix for Async

= 1.6 =
* Update : Added support for async
* Update : Removed amount from HOST method to facilitate order amount manipulation from 3rd party plugins

= 1.5 =
* Bugfix : Fixed compatability with woocommerce 3.5.1

= 1.4 =
* Bugfix : Fixed problem "result code invalid"
* Update : Setup CVV as mandatory on repeat payments

= 1.3 =
New* : Optional Card Details section on checkout success

= 1.2 =
Plug-in was upgraded to support WooCommerce 2.4 and later.

= 1.1 =
Plug-in was upgraded to support WooCommerce 3 or later. Upgrade may break on earlier versions of WooCommerce.
