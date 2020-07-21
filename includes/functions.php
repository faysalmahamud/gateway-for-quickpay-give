<?php
/**
 * Get payment method label.
 *
 * @since 1.0
 * @return string
 */
function give_quickpay_get_payment_method_label() {
	$give_settings    = give_get_settings();
	$gateways_label   = array_key_exists( 'gateways_label', $give_settings ) ?
		$give_settings['gateways_label'] :
		array();

	$label = ! empty( $gateways_label['quickpay'] )
		? $gateways_label['quickpay']
		: give_get_option( 'quickpay_payment_method_label', __( 'Quickpay', 'give-quickpay' ) );

	return $label;
}