jQuery(document).ready(function($) {

	//Settings page
	var vp_shipping_rate_frontend = {
		init: function() {

			//If theres a payment method condition set for the pricing, update the checkout if payment method changed
			if(vp_shipping_rate_frontend_params.refresh_payment_methods) {

				//For shortcode checkout
				$( 'form.checkout' ).on( 'change', 'input[name^="payment_method"]', function() {
					$('body').trigger('update_checkout');
				});
				
				//Update checkout when payment method is changed for checkout block
				if(window.wc && window.wc.blocksCheckout) {

					//Store payment method ID when payment method is changed
					wp.hooks.addAction('experimental__woocommerce_blocks-checkout-set-active-payment-method', 'vp-shipping-rate', function(payment_method) {
						window.wc.blocksCheckout.extensionCartUpdate({
							namespace: 'vp-shipping-rate',
							cartPropsToReceive: [ 'extensions', 'shipping-rates' ],
							data: {
								payment_method: payment_method.value
							}
						});
					});
				}

			}

		}
	}

	vp_shipping_rate_frontend.init();
});
