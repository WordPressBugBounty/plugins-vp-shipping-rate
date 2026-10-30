<?php
defined( 'ABSPATH' ) || exit;

$settings = array(
	'title'      => array(
		'title'       => __( 'Method title', 'vp-shipping-rate' ),
		'type'        => 'text',
		'description' => __( 'This controls the title which the user sees during checkout.', 'vp-shipping-rate' ),
		'default'     => _x( 'Shipping Rate', 'shipping method default name', 'vp-shipping-rate' ),
		'desc_tip'    => true,
	),
	'tax_status' => array(
		'title'   => __( 'Tax status', 'vp-shipping-rate' ),
		'type'    => 'select',
		'class'   => 'wc-enhanced-select',
		'default' => 'taxable',
		'options' => array(
			'taxable' => __( 'Taxable', 'vp-shipping-rate' ),
			'none'    => _x( 'None', 'Tax status', 'vp-shipping-rate' ),
		),
	),
	'cost'       => array(
		'title'             => __( 'Default cost', 'vp-shipping-rate' ),
		'type'              => 'text',
		'placeholder'       => '',
		'description'       => __('Enter a default price for this shipping option. You can overwrite this later based on conditional logic.', 'vp-shipping-rate'),
		'default'           => '0',
		'desc_tip'          => true,
		'sanitize_callback' => array( $this, 'sanitize_cost' ),
	),
	'pricing' => array(
		'title' => __('Detailed cost', 'vp-shipping-rate'),
		'type' => 'vp_shipping_rate_settings_pricing_table',
	),
	'cost_logic' => array(
		'title'   => __( 'Multiple cost logic', 'vp-shipping-rate' ),
		'type'    => 'select',
		'class'   => 'wc-enhanced-select',
		'default' => 'low',
		'description'       => __('If theres multiple matches for the shipping cost, use the lowest or the highest cost.', 'vp-shipping-rate'),
		'options' => array(
			'low' => __( 'Lowest', 'vp-shipping-rate' ),
			'high' => __( 'Highest', 'vp-shipping-rate' ),
			'high_but_free' => __( 'Highest, but with free shipping', 'vp-shipping-rate' ),
			'sum' => __( 'Sum', 'vp-shipping-rate' ),
		),
	),
	'free_shipping_with_coupon' => array(
		'title' => __( 'Free shipping coupon', 'vp-shipping-rate' ),
		'label' => __( 'Free shipping coupon applies to this rate', 'vp-shipping-rate' ),
		'type' => 'checkbox',
	),
	'free_shipping_overwrite' => array(
		'title' => __( 'Free shipping', 'vp-shipping-rate' ),
		'label' => __( 'If free shipping is available, make this rate free too', 'vp-shipping-rate' ),
		'type' => 'checkbox',
	),
	'disable_cod' => array(
		'title' => __( 'Disable COD', 'vp-shipping-rate' ),
		'label' => __( 'Disable cash on delivery if this shipping method is selected', 'vp-shipping-rate' ),
		'type' => 'checkbox',
	)
);

return apply_filters('vp_shipping_rate_instance_settings', $settings);

