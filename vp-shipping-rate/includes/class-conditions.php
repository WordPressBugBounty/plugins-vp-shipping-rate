<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'VP_Shipping_Rate_Conditions', false ) ) :

	class VP_Shipping_Rate_Conditions {

		//Get possible conditional values
		public static function get_conditions($group = 'pricings') {

			//Get country list
			$countries_obj = new WC_Countries();
			$countries = $countries_obj->__get('countries');

			//Setup conditions
			$conditions = array(
				'cart_total' => array(
					'label' => __('Cart Total', 'vp-shipping-rate'),
					'options' => array()
				),
				'cart_total_discount' => array(
					'label' => __('Cart Total(with discount)', 'vp-shipping-rate'),
					'options' => array()
				),
				'volume' => array(
					'label' => __('Package volume', 'vp-shipping-rate'),
					'options' => array()
				),
				'longest_side' => array(
					'label' => __('Package longest side', 'vp-shipping-rate'),
					'options' => array()
				),
				'cart_count' => array(
					'label' => __('Items in cart', 'vp-shipping-rate'),
					'options' => array()
				),
				'item_count' => array(
					'label' => __('Items in condition', 'vp-shipping-rate'),
					'options' => array()
				),
				'product_category' => array(
					'label' => __('Product category', 'vp-shipping-rate'),
					'options' => array()
				),
				'shipping_class' => array(
					'label' => __('Shipping class', 'vp-shipping-rate'),
					'options' => VP_Shipping_Rate_Helpers::get_shipping_classes()
				),
				'type' => array(
					"label" => __('Order type', 'vp-shipping-rate'),
					'options' => array(
						'individual' => __('Individual', 'vp-shipping-rate'),
						'company' => __('Company', 'vp-shipping-rate'),
					)
				),
				'billing_country' => array(
					"label" => __('Billing country', 'vp-shipping-rate'),
					'options' => $countries
				),
				'current_date' => array(
					'label' => __('Current date', 'vp-shipping-rate'),
					'options' => array()
				),
				'current_time' => array(
					'label' => __('Current time', 'vp-shipping-rate'),
					'options' => array()
				),
				'current_day' => array(
					'label' => __('Current day', 'vp-shipping-rate'),
					'options' => array(
						1 => __('Monday', 'vp-shipping-rate'),
						2 => __('Tuesday', 'vp-shipping-rate'),
						3 => __('Wednesday', 'vp-shipping-rate'),
						4 => __('Thursday', 'vp-shipping-rate'),
						5 => __('Friday', 'vp-shipping-rate'),
						6 => __('Saturday', 'vp-shipping-rate'),
						7 => __('Sunday', 'vp-shipping-rate'),
					)
				),
				'payment_method' => array(
					"label" => __('Payment method', 'vp-shipping-rate'),
					'options' => VP_Shipping_Rate_Helpers::get_payment_methods()
				),
				'user_logged_in' => array(
					'label' => __('User logged in', 'vp-shipping-rate'),
					'options' => array(
						'yes' => __('Yes', 'vp-shipping-rate'),
						'no' => __('No', 'vp-shipping-rate'),
					)
				),
				'user_role' => array(
					'label' => __('User role', 'vp-shipping-rate'),
					'options' => VP_Shipping_Rate_Helpers::get_user_roles()
				),
				'weight' => array(
					"label" => __('Package weight', 'vp-shipping-rate'),
					'options' => array()
				),
			);

			//Add hierarchical category options
			$all_cats = get_terms(array('taxonomy' => 'product_cat', 'hide_empty' => false));
			if(!is_wp_error($all_cats) && $all_cats) {
				$cats_by_parent = array();
				foreach ($all_cats as $cat) {
					$cats_by_parent[(int)$cat->parent][] = $cat;
				}
				$walker = function($parent_id, $depth) use (&$walker, $cats_by_parent, &$conditions) {
					if(!isset($cats_by_parent[$parent_id])) return;
					usort($cats_by_parent[$parent_id], function($a, $b){ return strcasecmp($a->name, $b->name); });
					foreach ($cats_by_parent[$parent_id] as $cat) {
						$prefix = ($depth > 0) ? str_repeat('- ', $depth) . ' ' : '';
						$conditions['product_category']['options'][$cat->term_id] = $prefix . $cat->name;
						$walker($cat->term_id, $depth + 1);
					}
				};
				$walker(0, 0);
			}

			//Apply filters
			$conditions = apply_filters('vp_shipping_rate_pricings_conditions', $conditions);

			return $conditions;
		}

		public static function get_cart_details($group) {

			//Get weight
			$cart_weight = WC()->cart->get_cart_contents_weight();

			//Get volume
			$cart_volume = VP_Shipping_Rate_Helpers::get_cart_volume();
			$longest_side = VP_Shipping_Rate_Helpers::get_cart_volume_longest_side();

			//Get cart total
			$cart_total = WC()->cart->get_displayed_subtotal();
			$cart_total_discount = $cart_total-WC()->cart->get_discount_total();
			if ( WC()->cart->display_prices_including_tax() ) {
				$cart_total_discount = $cart_total_discount - WC()->cart->get_discount_tax();
			}

			//Get cart categories
			$cart_categories = array();

			//Get shipping classes
			$shipping_classes = array();
			$qty_values = array();

			//Loop through all products in the Cart
			foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
				$terms = get_the_terms ( $cart_item['product_id'], 'product_cat' );
				if($terms) {
					foreach ( $terms as $term ) {
						$cart_categories[] = $term->term_id;
						$qty_values[$term->term_id] = (isset($qty_values[$term->term_id])) ? $qty_values[$term->term_id]+$cart_item['quantity'] : $cart_item['quantity'];
					}
				}
				if($cart_item['data']->get_shipping_class()) {
					$shipping_classes[] = $cart_item['data']->get_shipping_class();
					$qty_values[$cart_item['data']->get_shipping_class()] = (isset($qty_values[$cart_item['data']->get_shipping_class()])) ? $qty_values[$cart_item['data']->get_shipping_class()]+$cart_item['quantity'] : $cart_item['quantity'];
				}
			}

			//Get payment method
			$payment_method = WC()->session->get('chosen_payment_method');

			//For checkout block
			if(!$payment_method) {
				$payment_method = WC()->session->get('vp_shipping_rate_chosen_payment_method');
			}

			//Get billing details
			$customer = WC()->cart->get_customer();
			$order_type = ($customer->get_billing_company()) ? 'company' : 'individual';

			//Get billing address location
			$eu_countries = WC()->countries->get_european_union_countries('eu_vat');
			$billing_address = 'world';
			if(in_array($customer->get_billing_country(), $eu_countries)) {
				$billing_address = 'eu';
			}

			//Get filtered item count
			$cart_item_count = WC()->cart->get_cart_contents_count();

			//Setup an array to match conditions
			$cart_details = array(
				'cart_total' => $cart_total,
				'cart_total_discount' => $cart_total_discount,
				'product_categories' => $cart_categories,
				'weight' => $cart_weight,
				'volume' => $cart_volume,
				'payment_method' => $payment_method,
				'billing_country' => $customer->get_billing_country(),
				'billing_address' => $billing_address,
				'type' => $order_type,
				'shipping_classes' => $shipping_classes,
				'cart_count' => WC()->cart->get_cart_contents_count(),
				'longest_side' => $longest_side,
				'current_date' => strtotime( wp_date( 'Y-m-d' ) ),
				'current_time' => strtotime( wp_date( 'H:i' ) ),
				'current_day' => wp_date('N'),
				'qty' => $qty_values,
				'item_count' => $cart_item_count,
				'user_logged_in' => (is_user_logged_in()) ? 'yes' : 'no',
				'user_role' => (is_user_logged_in()) ? wp_get_current_user()->roles[0] : '',
			);
			
			//Custom conditions
			return apply_filters('vp_shipping_rate_pricings_conditions_values', $cart_details);

		}

		public static function match_conditions($items, $item_id, $order_details) {
			$item = $items[$item_id];

			//Check if the conditions match
			foreach ($item['conditions'] as $condition_id => $condition) {
				$comparison = ($condition['comparison'] == 'equal');
				$items[$item_id]['conditions'][$condition_id]['match'] = false;

				//Convert date to time
				if($condition['category'] == 'current_date') {
					$condition['value'] = strtotime( wp_date( 'Y-m-d', strtotime($condition['value']) ) );
				}

				//Add condition for current time
				if($condition['category'] == 'current_time') {
					$condition['value'] = strtotime($condition['value']);
				}

				switch ($condition['category']) {
					case 'product_category':
						if(in_array($condition['value'], $order_details['product_categories'])) {
							$items[$item_id]['conditions'][$condition_id]['match'] = $comparison;
						} else {
							$items[$item_id]['conditions'][$condition_id]['match'] = !$comparison;
						}
						break;
					case 'shipping_class':
						if(in_array($condition['value'], $order_details['shipping_classes'])) {
							$items[$item_id]['conditions'][$condition_id]['match'] = $comparison;
						} else {
							$items[$item_id]['conditions'][$condition_id]['match'] = !$comparison;
						}
						break;
					default:
						switch ($condition['comparison']) {
							case 'equal':
								if($condition['value'] == $order_details[$condition['category']]) {
									$items[$item_id]['conditions'][$condition_id]['match'] = true;
								}
								break;
							case 'not_equal':
								if($condition['value'] != $order_details[$condition['category']]) {
									$items[$item_id]['conditions'][$condition_id]['match'] = true;
								}
								break;
							case 'greater':
								if((float)$condition['value'] < $order_details[$condition['category']]) {
									$items[$item_id]['conditions'][$condition_id]['match'] = true;
								}
								break;
							case 'greater_or_equal':
								if((float)$condition['value'] <= $order_details[$condition['category']]) {
									$items[$item_id]['conditions'][$condition_id]['match'] = true;
								}
								break;
							case 'less':
								if((float)$condition['value'] > $order_details[$condition['category']]) {
									$items[$item_id]['conditions'][$condition_id]['match'] = true;
								}
								break;
							case 'less_or_equal':
								if((float)$condition['value'] >= $order_details[$condition['category']]) {
									$items[$item_id]['conditions'][$condition_id]['match'] = true;
								}
								break;		
							default:
								if((float)$condition['value'] > $order_details[$condition['category']]) {
									$items[$item_id]['conditions'][$condition_id]['match'] = true;
								}
								break;
						}
						break;
				}
			}

			//Count how many matches we have
			$matched = 0;
			foreach ($items[$item_id]['conditions'] as $condition) {
				if($condition['match']) $matched++;
			}

			//Check if we need to match all or just one
			$condition_is_a_match = false;
			if($item['logic'] == 'and' && $matched == count($item['conditions'])) $condition_is_a_match = true;
			if($item['logic'] == 'or' && $matched > 0) $condition_is_a_match = true;

			return $condition_is_a_match;
		}

	}

endif;
