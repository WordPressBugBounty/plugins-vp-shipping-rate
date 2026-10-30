<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'VP_Shipping_Rate_Helpers', false ) ) :

	class VP_Shipping_Rate_Helpers {

		public static function get_cart_volume() {
			// Initializing variables
			$volume = $rate = 0;

			// Get the dimetion unit set in Woocommerce
			$dimension_unit = get_option( 'woocommerce_dimension_unit' );

			// Calculate the rate to be applied for volume in m3
			if ( $dimension_unit == 'mm' ) {
				$rate = pow(10, 9);
			} elseif ( $dimension_unit == 'cm' ) {
				$rate = pow(10, 6);
			} elseif ( $dimension_unit == 'm' ) {
				$rate = 1;
			}

			if( $rate == 0 ) return false; // Exit

			// Loop through cart items
			foreach(WC()->cart->get_cart() as $cart_item) {
				// Get an instance of the WC_Product object and cart quantity
				$product = $cart_item['data'];
				$qty     = $cart_item['quantity'];

				// Get product dimensions
				$length = $product->get_length();
				$width  = $product->get_width();
				$height = $product->get_height();

				// Calculations a item level
				if($length && $width && $height) {
					$volume += $length * $width * $height * $qty;
				}
			}

			return $volume / $rate;
		}

		public static function get_cart_volume_longest_side() {
			$sides = array();
			$max = 0;
			foreach(WC()->cart->get_cart() as $cart_item) {
				$product = $cart_item['data'];
				$length = $product->get_length();
				$width  = $product->get_width();
				$height = $product->get_height();

				// Calculations a item level
				if($length && $width && $height) {
					$sides[] = $length;
					$sides[] = $width;
					$sides[] = $height;
				}
			}

			if($sides) {
				$max = max($sides);
			}

			return $max;
		}

		public static function get_payment_methods() {
			$available_gateways = WC()->payment_gateways->payment_gateways();
			$payment_methods = array();
			foreach ($available_gateways as $available_gateway) {
				if($available_gateway->enabled == 'yes') {
					$payment_methods[$available_gateway->id] = $available_gateway->title;
				}
			}
			return $payment_methods;
		}

		public static function pricing_has_payment_method_condition() {
			$custom_zones = WC_Shipping_Zones::get_zones();
			$worldwide_zone = new WC_Shipping_Zone( 0 );
			$worldwide_methods = $worldwide_zone->get_shipping_methods();
			$has_payment_method_condition = false;

			foreach ( $custom_zones as $zone ) {
				$shipping_methods = $zone['shipping_methods'];
				foreach ($shipping_methods as $shipping_method) {
					if ( $shipping_method->is_enabled() && $shipping_method->id == 'vp_shipping_rate' && $shipping_method->has_payment_method_condition()) {
						$has_payment_method_condition = true;
					}
				}
			}

			foreach ($worldwide_methods as $shipping_method) {
				if ( $shipping_method->is_enabled() && $shipping_method->id == 'vp_shipping_rate' && $shipping_method->has_payment_method_condition()) {
					$has_payment_method_condition = true;
				}
			}

			return $has_payment_method_condition;
		}

		public static function get_product_categories() {
			$categories = array();
			foreach (get_terms(array('taxonomy' => 'product_cat')) as $category) {
				$categories[$category->term_id] = $category->name;
			}
			return $categories;
		}

		public static function cart_has_free_shipping_coupon() {
			$has_free_shipping = false;
			$applied_coupons = WC()->cart->get_applied_coupons();
			foreach( $applied_coupons as $coupon_code ){
				$coupon = new WC_Coupon($coupon_code);
				if($coupon->get_free_shipping()){
					$has_free_shipping = true;
					break;
				}
			}
			return $has_free_shipping;
		}

		public static function is_free_shipping_available() {
			$is_free_shipping_available = false;
			$packages = WC()->shipping()->get_packages();
			foreach ( $packages as $package_id => $package ) {
				if ( WC()->session->__isset( 'shipping_for_package_'.$package_id ) && isset(WC()->session->get( 'shipping_for_package_'.$package_id )['rates']) ) {
					foreach ( WC()->session->get( 'shipping_for_package_'.$package_id )['rates'] as $shipping_rate_id => $shipping_rate ) {
						$cost = $shipping_rate->get_cost();
						if($cost == 0 && $shipping_rate->get_method_id() != 'vp_shipping_rate' && $shipping_rate->get_method_id() != 'local_pickup') {
							$is_free_shipping_available = true;
						}
					}
				}
			}
			return $is_free_shipping_available;
		}

		public static function is_vp_shipping_rate_selected() {

			//Get selected shipping methd
			$chosen_methods = WC()->session->get( 'chosen_shipping_methods' );

			//If vp_shipping_rate is chosen
			$is_vp_shipping_rate_selected = false;
			if($chosen_methods) {
				foreach ($chosen_methods as $chosen_method) {
					if(strpos($chosen_method, 'vp_shipping_rate') !== false) {
						$is_vp_shipping_rate_selected = true;
					}
				}
			}

			return $is_vp_shipping_rate_selected;
		}

		public static function get_shipping_classes() {
			$shipping_classes = WC()->shipping()->get_shipping_classes();
			$available_classes = array();
			foreach ($shipping_classes as $shipping_class) {
				$available_classes[$shipping_class->slug] = $shipping_class->name;
			}
			return $available_classes;
		}

		public static function get_user_roles() {
			$roles = get_editable_roles();
			$available_roles = array();
			foreach ($roles as $role_id => $role) {
				$available_roles[$role_id] = $role['name'];
			}
			return $available_roles;
		}

	}

endif;
