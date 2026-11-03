<?php
/*
Plugin Name: Extra Shipping Rates for WooCommerce
Plugin URI: https://visztpeter.me
Description: Setup shipping rates based on various conditions such as weight, number of items, shipping class, price, cart total and much more.
Author: Viszt Péter
Author URI: https://visztpeter.me
Text Domain: vp-shipping-rate
Domain Path: /languages/
Version: 1.4.1
WC requires at least: 8.0
WC tested up to: 10.7.0
License: GPLv2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Requires Plugins: woocommerce
*/
use Automattic\Jetpack\Constants;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! defined( 'VP_SHIPPING_RATE_PLUGIN_FILE' ) ) {
	define( 'VP_SHIPPING_RATE_PLUGIN_FILE', __FILE__ );
}

class VP_Shipping_Rate {
	public static $plugin_prefix;
	public static $plugin_url;
	public static $plugin_path;
	public static $plugin_basename;
	public static $version;
	public $template_base = null;
	public $settings = null;
	protected static $_instance = null;
	public static $sidebar_loaded = false;
	public $labels = null;
	public static $plugin_slug;

	//Load providers
	public $providers = array();

	//Ensures only one instance of class is loaded or can be loaded
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	//Just for a little extra security
	public function __clone() {
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Cloning this object is forbidden.', 'vp-shipping-rate' ), esc_html( VP_Shipping_Rate::$version ) );
	}

	//Just for a little extra security
	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Unserializing instances of this class is forbidden.', 'vp-shipping-rate' ), esc_html( VP_Shipping_Rate::$version ) );
	}

	//Construct
	public function __construct() {

		//Default variables
		self::$plugin_prefix = 'vp_shipping_rate';
		self::$plugin_basename = plugin_basename(__FILE__);
		self::$plugin_path = trailingslashit(dirname(__FILE__));
		self::$version = '1.4.1';
		self::$plugin_url = plugin_dir_url(self::$plugin_basename);
		self::$plugin_slug = 'vp-shipping-rate';

		//Helper functions & classes
		require_once( plugin_dir_path( __FILE__ ) . 'includes/class-helpers.php' );
		require_once( plugin_dir_path( __FILE__ ) . 'includes/class-conditions.php' );

		//Plugin loaded
		add_action( 'plugins_loaded', array( $this, 'init' ), 11 );

		//HPOS compatibility
		add_action( 'before_woocommerce_init', array( $this, 'woocommerce_hpos_compatible' ) );

		//Admin assets
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_js' ) );

		//Frontend assets
		add_action( 'wp_enqueue_scripts', array( $this, 'frontend_js' ));

		//Create new shipping method
		add_action( 'woocommerce_shipping_init', array( $this, 'shipping_init' ) );
		add_filter( 'woocommerce_shipping_methods', array( $this, 'add_method' ) );

		//Runs when a shipping method is selected
		add_action( 'woocommerce_checkout_update_order_review', array( $this, 'shipping_method_selected' ));

		//Free shipping if another free shipping method exists
		add_filter( 'woocommerce_package_rates', array($this, 'free_shipping_when_free_shipping_exists'), 101, 2);

		//Checkout block compatibility for payment methods
		add_action('woocommerce_blocks_loaded', array($this, 'update_payment_method_in_session'));

		//Reset payment method in session(this is to fix checkout block compatibility)
		add_action('woocommerce_checkout_init', array($this, 'reset_payment_method_in_session'));

		//Indicate free shipping in the name
		add_filter('woocommerce_cart_shipping_method_full_label', array($this, 'free_shipping_label'), 10, 2);
		add_filter('woocommerce_order_shipping_to_display', array( $this, 'display_cost_in_emails'), 10, 3);
		
		//Debug mode
		add_action('woocommerce_load_shipping_methods', array($this, 'show_debug_notice'));
		
	}
	
	//Loads when plugins initialized
	public function init() {

		//Load translations
		load_plugin_textdomain( 'vp-shipping-rate', false, basename( dirname( __FILE__ ) ) . '/languages/' );

	}

	//Declares WooCommerce HPOS compatibility.
	public function woocommerce_hpos_compatible() {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}

	//Add Admin CSS & JS
	public function admin_js() {

		//Get current screen
		$screen       = get_current_screen();
		$screen_id    = $screen ? $screen->id : '';

		if(in_array($screen_id, wc_get_screen_ids())) {
			wp_enqueue_script( 'vp_shipping_rate_admin_js', plugins_url( '/assets/js/admin.min.js',__FILE__ ), array('jquery', 'wc-backbone-modal', 'jquery-blockui', 'jquery-ui-sortable', 'jquery-tiptip'), VP_Shipping_Rate::$version, TRUE );
			wp_enqueue_style( 'vp_shipping_rate_admin_css', plugins_url( '/assets/css/admin.css',__FILE__ ), array(), VP_Shipping_Rate::$version );
		}
		
	}

	//Frontend CSS & JS
	public function frontend_js() {

		//Only on the checkout page
		if(is_checkout()) {
			wp_enqueue_script( 'vp_shipping_rate_frontend_js', plugins_url( '/assets/js/frontend.min.js',__FILE__ ), array('jquery'), VP_Shipping_Rate::$version );
			$vp_shipping_rate_params = array(
				'refresh_payment_methods' => VP_Shipping_Rate_Helpers::pricing_has_payment_method_condition(),
			);

			wp_localize_script( 'vp_shipping_rate_frontend_js', 'vp_shipping_rate_frontend_params', apply_filters('vp_shipping_rate_frontend_params', $vp_shipping_rate_params) );
		}

	}

	//Load gateway class
	public function shipping_init() {
		include_once __DIR__ . '/includes/class-shipping-rate.php';
	}

	//Add shipping method
	public function add_method( $methods ) {
		$methods['vp_shipping_rate'] = 'VP_Shipping_Rate_Method';
		return $methods;
	}

	//Mark shipping rate free if instance settings say so
	public function free_shipping_when_free_shipping_exists($rates, $package) {
		$has_free_shipping = false;
		foreach ( $rates as $rate_id => $rate ) {
			if((float)$rate->get_cost() == 0 && $rate->get_method_id() != 'vp_shipping_rate' && $rate->get_method_id() != 'local_pickup' && $rate->get_method_id() != 'vp_pont') {
				$has_free_shipping = true;
				break;
			}
		}

		if($has_free_shipping) {
			foreach($rates as $rate_id => $rate) {
				if ($rate->get_method_id() == 'vp_shipping_rate' && array_key_exists('free_shipping_overwrite', $rate->get_meta_data()) && $rate->get_meta_data()['free_shipping_overwrite'] == 1) {
					$rates[$rate_id]->cost = 0;
					$rates[$rate_id]->taxes = array();
				}
			}
		}
		return $rates;
	}

	//Invalidate cache when shipping method is selected(for shortcode checkout)
	function shipping_method_selected( $post_data ) {

		//If vp_shipping_rate is chosen
		$is_vp_shipping_rate_selected = VP_Shipping_Rate_Helpers::is_vp_shipping_rate_selected();

		//Check if we are changeing to VP shipping
		if(!$is_vp_shipping_rate_selected && $post_data && strpos($post_data, 'vp_shipping_rate') !== false) {
			$is_vp_shipping_rate_selected = true;
		}

		//If vp_shipping_rate is selected, invalidate cache
		if($is_vp_shipping_rate_selected) {
			$packages = WC()->cart->get_shipping_packages();
			foreach ($packages as $key => $value) {
				$shipping_session = "shipping_for_package_$key";
				unset(WC()->session->$shipping_session);
			}
		}

	}

	//Update payment method in session and invalidate cache(this if for the checkout block)
	function update_payment_method_in_session() {
		if(function_exists('woocommerce_store_api_register_endpoint_data')) {
			woocommerce_store_api_register_update_callback([
				'namespace' => 'vp-shipping-rate',
				'callback'  => function( $data ) {
					if(isset($data['payment_method']) && is_string($data['payment_method'])) {
						WC()->session->set('vp_shipping_rate_chosen_payment_method', sanitize_text_field($data['payment_method']));
						WC()->cart->calculate_shipping();
						WC()->cart->calculate_totals();

						//Invalidate cache
						$is_vp_shipping_rate_selected = VP_Shipping_Rate_Helpers::is_vp_shipping_rate_selected();
						if($is_vp_shipping_rate_selected) {
							$packages = WC()->cart->get_shipping_packages();
							foreach ($packages as $key => $value) {
								$shipping_session = "shipping_for_package_$key";
								//unset(WC()->session->$shipping_session);
							}
						}
					}
				}
			]);
		}
	}

	//Reset payment method in session(this is to fix checkout block compatibility)
	function reset_payment_method_in_session() {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) )
		return;

		if( ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'DOING_AJAX') && DOING_AJAX ))
		return;

		//Get available payment methods
		$available_gateways = WC()->payment_gateways->get_available_payment_gateways();

		//Get first payment method and store it
		//Since payment method selection is not persistent, on page load always the first one will be selected
		$first_payment_method = key($available_gateways);
		WC()->session->set('vp_shipping_rate_chosen_payment_method', $first_payment_method);
	}

	function free_shipping_label($label, $method) {
		if($method->get_method_id() == 'vp_shipping_rate' && $method->get_cost() == 0) {
			$label .= ': ' . __('free', 'vp-shipping-rate');
		}
		return $label;
	}

	public function display_cost_in_emails( $shipping, $order, $tax_display) {
		if($order->get_shipping_total() == 0 && $order->get_shipping_method() == 'vp_shipping_rate') {
			$shipping = $shipping.' ('.esc_html__( 'free', 'vp-shipping-rate' ).')';
		}
		return $shipping;
	}

	//Show the cart details used for condition matching if WooCommerce shipping debug mode is enabled
	public function show_debug_notice($package) {
		if ( empty( $package ) || ! WC()->cart || ! WC()->session ) {
			return;
		}

		//Only for shop managers, when debug mode is enabled
		$debug_mode = 'yes' === get_option( 'woocommerce_shipping_debug_mode', 'no' );
		if(!$debug_mode || !current_user_can('manage_woocommerce')) {
			return;
		}

		$cart_details = VP_Shipping_Rate_Conditions::get_cart_details('pricings');

		//Convert cart details array to a printable notice
		$lines = array();
		foreach ($cart_details as $key => $value) {
			if(is_array($value)) {
				$values = array();
				foreach ($value as $sub_key => $sub_value) {
					$values[] = is_int($sub_key) ? $sub_value : $sub_key.': '.$sub_value;
				}
				$value = implode(', ', array_unique($values));
			} elseif(is_bool($value)) {
				$value = $value ? 'true' : 'false';
			}
			$lines[] = '<strong>'.esc_html($key).'</strong>: '.esc_html(($value === '' || $value === null) ? '-' : $value);
		}

		$notice = '<strong>'.esc_html_x( 'Extra Shipping Rate', 'admin', 'vp-shipping-rate' ).'</strong><br>'.implode('<br>', $lines);

		//Shipping is calculated multiple times per request(and per package), so avoid duplicates
		if ( ! Constants::is_defined( 'WOOCOMMERCE_CHECKOUT' ) && ! Constants::is_defined( 'WC_DOING_AJAX' ) && ! wc_has_notice( $notice ) ) {
			wc_add_notice($notice);
		}
	}

}

//To start the plugin
function VP_Shipping_Rate() {
	return VP_Shipping_Rate::instance();
}

//Only if Woo is running
add_action('plugins_loaded', function(){
	if (defined('WC_VERSION')) {
		VP_Shipping_Rate();
	}
});