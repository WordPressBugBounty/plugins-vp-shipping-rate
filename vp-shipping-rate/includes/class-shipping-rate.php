<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VP_Shipping_Rate_Method extends WC_Shipping_Method {

	//Create instance
	public function __construct( $instance_id = 0 ) {
		$this->id                 = 'vp_shipping_rate';
		$this->instance_id        = absint( $instance_id );
		$this->method_title       = esc_html(_x( 'Extra Shipping Rate', 'admin', 'vp-shipping-rate' ));
		$this->method_description = esc_html__( 'Shipping rate based on various conditions. ', 'vp-shipping-rate' );
		$this->supports           = array(
			'shipping-zones',
			'instance-settings',
		);
		$this->init();
	}

	//Initialize settings.
	private function init() {
		$this->instance_form_fields     = include __DIR__ . '/settings-instance.php';
		$this->init_settings();
		$this->title                    = $this->get_option( 'title', $this->method_title );
		$this->tax_status               = $this->get_option( 'tax_status' );
	}

	//Calculate cost
	public function calculate_shipping( $package = array() ) {
		$rate = array(
			'id'      => $this->get_rate_id(),
			'label'   => $this->title,
			'cost'    => 0,
			'package' => $package,
			'meta_data' => array(
				'free_shipping_overwrite' => ($this->get_option( 'free_shipping_overwrite', 'no' ) == 'yes'),
			)
		);

		//Find out shipping cost
		$matched_prices = $this->get_matching_prices();

		//Get info if we need lowest or highest price if theres multiple matches
		$cost_logic = self::get_option('cost_logic', 'low');
		$cost = min($matched_prices);
		if($cost_logic == 'high') $cost = max($matched_prices);
		if($cost_logic == 'sum') $cost = array_sum($matched_prices);
		if($cost_logic == 'high_but_free') {
			if(in_array(0, $matched_prices)) {
				$cost = 0;
			} else {
				$cost = max($matched_prices);
			}
		}

		//Check if a free coupon is used and can overwrite the cost
		if($this->get_option('free_shipping_with_coupon', 'no') == 'yes' && VP_Shipping_Rate_Helpers::cart_has_free_shipping_coupon()) {
			$cost = 0;
		}

		//Allow plugins to customize
		$cost = apply_filters( 'vp_shipping_rate_cost', $cost, $package, $this);
		$rate['cost'] = $cost;

		//Set rate
		$this->add_rate( $rate );
	}

	public function get_matching_prices() {

		//Find out shipping cost
		$pricings = $this->get_option('pricing', array());
		$matched_prices = array();

		//Get cart details
		$cart_details = VP_Shipping_Rate_Conditions::get_cart_details('pricings');

		//Loop through each cost setup and see if theres a match
		foreach ($pricings as $pricing_id => $pricing) {

			//Get the price
			$price = $pricing['cost'];

			//Check for weight based pricing conditions
			if(isset($pricing['weight_based']) && $pricing['weight_based']) {
				$price = self::calculate_weight_based_price($pricing['weight_ranges'], $cart_details['weight']);
				if($price === false) {
					$price = -1;
				}
			}

			//Product qty
			$matched_cart_details = $cart_details;
			$qty = $cart_details['cart_count'];

			//If the condition is based on a shipping class or category, get the QTY for that
			if($pricing['conditions']) {
				foreach ($pricing['conditions'] as $condition) {
					if($condition['category'] == 'product_category' || $condition['category'] == 'shipping_class') {
						if(isset($cart_details['qty'][$condition['value']])) {
							$qty = $cart_details['qty'][$condition['value']];
						}
					}
				}
			}
			
			//Duplicate cart details, so we can modify them for each pricing
			$matched_cart_details['item_count'] = $qty;

			//Loop through each condition and see if its a match
			$condition_is_a_match = VP_Shipping_Rate_Conditions::match_conditions($pricings, $pricing_id, $matched_cart_details);

			//If no match, skip to the next one
			if(!$condition_is_a_match) continue;

			//Check if cost has a custom math formula
			$price = str_replace('[qty]', $qty, $price);

			// Remove whitespace from string.
			$price = preg_replace( '/\s+/', '', $price );

			// Evaluate the math formula
			include_once WC()->plugin_path() . '/includes/libraries/class-wc-eval-math.php';
			$price = WC_Eval_Math::evaluate( $price );

			//Make it an array, so if multiple prices are matched, we can later decide which one to use
			$matched_prices[] = $price;

		}

		//If we don't have any custom costs, just add the base cost
		if(!$matched_prices) {
			$matched_prices[] = $this->get_option('cost', 0);
		}

		return $matched_prices;
	}

	public function is_available( $package ) {

		//Find out shipping costs
		$matched_prices = $this->get_matching_prices();
		
		//If matched prices contains -1, return false, so the method is not available
		if(in_array(-1, $matched_prices)) {
			return false;
		}

		return true;
	}

	//Generate html for custom settings fields
	public function generate_vp_shipping_rate_settings_pricing_table_html( $key, $data) {
		return $this->render_custom_setting_html($key, $data);
	}

	//Generate html for custom settings fields
	public function render_custom_setting_html($key, $data) {
		$field_key = $this->get_field_key( $key );
		$defaults = array(
			'title' => '',
			'disabled' => false,
			'class' => '',
			'css' => '',
			'placeholder' => '',
			'type' => 'text',
			'desc_tip' => false,
			'description' => '',
			'custom_attributes' => array(),
			'options' => array()
		);
		$data = wp_parse_args( $data, $defaults );
		$template_name = str_replace('vp_shipping_rate_settings_', '', $data['type']);
		ob_start();
		include( dirname( __FILE__ ) . '/views/html-admin-'.str_replace('_', '-', $template_name).'.php' );
		return ob_get_clean();
	}

	//Validate custom field data
	public function validate_vp_shipping_rate_settings_pricing_table_field($key, $value) {

		//Sanitize input just in case
		$value = wc_clean($value);

		//If its not an array, it means we removed all pricings, so we can just return an empty array
		if(!is_array($value)) {
			return array();
		}

		//Get conditions value to compare against available fields
		$available_conditions = VP_Shipping_Rate_Conditions::get_conditions('pricings');
		$available_logics = array('and', 'or');
		$available_comparison = array('equal', 'not_equal', 'greater', 'less', 'greater_or_equal', 'less_or_equal');

		//Setup new validated return
		$pricing_data = array();

		//Loop through submitted data
		foreach ($value as $pricing_id => $pricing) {

			//Get cost and make sure we use . as decimal separator
			$cost = wc_clean($pricing['cost']);
			$cost = str_replace(',','.',$cost);

			//Cost is a required field, we can assume user wanted 0 if its empty or has some text value
			if(!$cost) {
				$cost = 0;
			}

			//Check if the submitted parameters are correct
			$logic = wc_clean($pricing['logic']);

			//Use the default and value if the submitted value is invalid
			if(!in_array($logic, $available_logics)) {
				$logic = 'and';			
			}

			//Add the pricing to the validated data
			$pricing_data[$pricing_id] = array(
				'cost' => $cost,
				'logic' => $logic,
				'weight_based' => isset($pricing['weight_based']) ? true : false,
				'comment' => isset($pricing['comment']) ? sanitize_text_field($pricing['comment']) : '',
				'conditions' => array()
			);

			//If theres conditions to setup
			$conditions = (isset($pricing['conditions']) && count($pricing['conditions']) > 0);
			if($conditions) {
				foreach ($pricing['conditions'] as $condition) {
					$category = wc_clean($condition['category']);
					$comparison = wc_clean($condition['comparison']);
					
					//If the category is not valid, skip to the next one
					if(!isset($available_conditions[$category])) continue;

					//If the comparison is not valid, skip to the next one
					if(!in_array($comparison, $available_comparison)) continue;

					//Check for valid value
					if(!$condition[$condition['category']]) continue;
					$condition_value = sanitize_text_field($condition[$condition['category']]);

					//If we can check for value from a select boy, see if we have that options available
					if($available_conditions[$category]['options'] && !isset($available_conditions[$category]['options'][$condition_value])) continue;

					//If we got this far, we can add the condition
					$pricing_data[$pricing_id]['conditions'][] = array(
						'category' => $category,
						'comparison' => $comparison,
						'value' => $condition_value
					);
					
				}
			}

			//Save weight ranges
			$weight_based = isset($pricing['weight_based']) ? true : false;
			if($weight_based && isset($pricing['weight_ranges']) && count($pricing['weight_ranges']) > 0) {
				$pricing_data[$pricing_id]['weight_ranges'] = array();
				foreach ($pricing['weight_ranges'] as $weight_range) {
					$min_weight = wc_clean($weight_range['min']);
					$max_weight = wc_clean($weight_range['max']);
					$range_cost = wc_clean($weight_range['cost']);
					$range_cost = str_replace(',','.',$range_cost);
					$pricing_data[$pricing_id]['weight_ranges'][] = array(
						'min' => (float)$min_weight,
						'max' => (float)$max_weight,
						'cost' => (float)$range_cost
					);
				}
			}

		}

		return $pricing_data;
	}

	public function has_payment_method_condition() {
		$has_payment_method_condition = false;
		$costs = $this->get_option('pricing', array());
		foreach ($costs as $cost_id => $cost) {

			//Check for conditions if needed
			foreach ($cost['conditions'] as $condition_id => $condition) {
				if($condition['category'] == 'payment_method') {
					$has_payment_method_condition = true;
					break;
				}
			}

		}
		return $has_payment_method_condition;
	}

	public static function calculate_weight_based_price($ranges, $weight) {

		// Check if ranges is valid
		if (empty($ranges) || !is_array($ranges)) {
			return false;
		}

		// Loop through each range and find matching cost
		// Using: min < weight <= max (exclusive min, inclusive max)
		// This is standard shipping logic where 1kg falls in "0-1" range
		foreach ($ranges as $range) {
			if (!isset($range['min']) || !isset($range['max']) || !isset($range['cost'])) {
				continue;
			}

			$min = floatval($range['min']);
			$max = floatval($range['max']);
				
			// Check if weight falls within this range
			if ($weight > $min && $weight <= $max) {
				return floatval($range['cost']);
			}
		}

		// No matching range found
		return false;
	}

}
