=== Extra Shipping Rates for WooCommerce ===
Contributors: passatgt
Tags: woocommerce, shipping, conditional, table rate, weight
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 7.0
Stable tag: 1.4
License: GPLv2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Donate link: https://vp-plugins.com/

Easily set shipping rates based on a variety of conditions to match your business requirements in your WooCommerce store.

== Description ==

Are you tired of complex shipping setups and rigid pricing structures? Look no further! Meet *Extra Shipping Rates*, the user-friendly WooCommerce plugin designed to give you complete control over your shipping rates. With a multitude of conditions to tailor your shipping costs, you can now offer a personalized and seamless shopping experience for your customers. No ads, no upsells, no banners. It just works!

*Conditions Include*

* **Payment Method:** Choose shipping rates based on the selected payment method.
* **Order Type:** Distinguish rates for individual and company orders.
* **Product Category:** Set rates based on specific product categories.
* **Billing Country:** Tailor shipping costs to different billing countries.
* **Cart Total:** Adjust rates depending on the total value of items in the cart.
* **Cart Total (with Discount):** Factor in cart total after discounts are applied.
* **Package Weight:** Set rates based on the weight of the package.
* **Package Volume:** Customize rates based on the volume of the package.
* **Package Longest Side:** Set rates based on the longest side of the package.
* **Shipping Class:** Define rates for different shipping classes.
* **Items in Cart:** Adjust rates based on the number of items in the cart.
* **Items in Condition:** Adjust rates based on the number of items in the cart that match the product category or shipping class condition.
* **Current Date:** Set rates for specific dates.
* **Current Time:** Set rates for specific times during the day.
* **Current Day:** Set rates for specific days of the week.
* **User logged in:** Set different rates for logged in and guest customers.
* **User role:** Set different rates based on the user's role.

== Installation ==

1. Install and activate "VP Shipping Rate" from your WordPress dashboard.
2. Navigate to the settings and configure your shipping rates based on the provided conditions.
3. Enjoy the flexibility of personalized shipping rates that adapt to your business needs.

== Screenshots ==

1. Settings screen(WooCommerce / Settings / Shipping)

== Changelog ==

= 1.4 =
* Duplicate pricing will copy select field values too
* Category condition values are nested to indicate sub-categories
* You can optionally add notes to each rule
* Only relevant comparison operators are shown based on the condition type
* WP 7.0 compatiblity fixes

= 1.3.2 =
* Weight ranges are set and calculated in the same unit as the store settings

= 1.3.1 =
* Bug fixes for initial save and the add condition button

= 1.3 =
* Simpler weight based pricing: you can set the weight ranges and costs in a single section to reduce complexity
* Option to diplicate and sort the pricing sections for easier management
* Compatibility with latest WC and WP versions

= 1.2.4 =
* WooCommerce 9.9.3 compatibility fix

= 1.2.3 =
* Bugfix for saving settings

= 1.2.2 =
* Greater or equal and less or equal comparison settings in conditions
* Marked compatibility with latest WP and Woo

= 1.2.1 =
* Display "free" in WooCommerce e-mails next to the shipping method name, if it was free shipping

= 1.2 =
* User logged in condition
* User role condition
* Marked compatibility with latest WP and Woo

= 1.1.1 =
* If the shipping is free, it will display "free" after the shipping method name
* "Highest, but with free shipping" logic option, so you can use the most expensive option, but it will use the free one anyway if exists

= 1.1 =
* Time condition: setup shipping rates based on the current time, for example rates are different before 12:00 (use 24 hour format)
* Day conditon: setup shipping rates based on the current day of the week
* Items in condition count: if the condition contains a product category or shipping class condition, this value will only count the matching products, not the total number of cart items

= 1.0.3 =
* Fix for the "If free shipping is available, make this rate free too" option

= 1.0.2 =
* You can use math in the cost field, like * [qty](which is the cart count, or if the condition is based on shipping class or category, the count of the matching items)
* Sum cost logic, which adds together all the matching costs to define the final shipping cost

= 1.0.1 =
* Updated readme and assets

= 1.0 =
* First version released