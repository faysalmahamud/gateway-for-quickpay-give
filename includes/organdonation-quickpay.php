<?php
	if (!defined('ABSPATH')) die();

require "vendor/autoload.php";
use QuickPay\QuickPay;

/**================== QuickPay payment gateway with GiveWP =================================================**/
/**
 * Register payment method.
 *
 * @since 1.0.0
 *
 * @param array $gateways List of registered gateways.
 *
 * @return array
 */

// change the prefix quickpay_for_give here to avoid collisions with other functions
function quickpay_for_give_register_payment_method( $gateways ) {
  
  // Duplicate this section to add support for multiple payment method from a custom payment gateway.
  $gateways['quickpay'] = array(
    'admin_label'    => __( 'QuickPay - Payment', 'quickpay-for-give' ), // This label will be displayed under Give settings in admin.
    'checkout_label' => __( 'QuickPay Payment', 'quickpay-for-give' ), // This label will be displayed on donation form in frontend.
  );
  
  return $gateways;
}

add_filter( 'give_payment_gateways', 'quickpay_for_give_register_payment_method' );



/**
 * Register Section for Payment Gateway Settings.
 *
 * @param array $sections List of payment gateway sections.
 *
 * @since 1.0.0
 *
 * @return array
 */

// change the quickpay_for_give prefix to avoid collisions with other functions.
function quickpay_for_give_register_payment_gateway_sections( $sections ) {
	
	// `quickpay-settings` is the name/slug of the payment gateway section.
	$sections['quickpay-settings'] = __( 'Quickpay', 'quickpay-for-give' );

	return $sections;
}

add_filter( 'give_get_sections_gateways', 'quickpay_for_give_register_payment_gateway_sections' );



/**
 * Register Admin Settings.
 *
 * @param array $settings List of admin settings.
 *
 * @since 1.0.0
 *
 * @return array
 */
// change the quickpay_for_give prefix to avoid collisions with other functions.
function quickpay_for_give_register_payment_gateway_setting_fields( $settings ) {

	switch ( give_get_current_setting_section() ) {

		case 'quickpay-settings':
			$settings = array(
				array(
					'id'   => 'give_title_quickpay',
					'type' => 'title',
				),
			);

      $settings[] = array(
				'name' => __( 'API Key', 'give-quickpay' ),
				'desc' => __( 'Enter your API Key, found in your Quickpay Dashboard.', 'quickpay-for-give' ),
				'id'   => 'quickpay_for_give_api_key',
				'type' => 'text',
		    );

			$settings[] = array(
				'id'   => 'give_title_quickpay',
				'type' => 'sectionend',
			);

			break;

	} // End switch().

	return $settings;
}

// change the quickpay_for_give prefix to avoid collisions with other functions.
add_filter( 'give_get_settings_gateways', 'quickpay_for_give_register_payment_gateway_setting_fields' );





/**
 * Process Quickpay checkout submission.
 *
 * @param array $posted_data List of posted data.
 *
 * @since  1.0.0
 * @access public
 *
 * @return void
 */

// change the quickpay_for_give prefix to avoid collisions with other functions.
function quickpay_for_give_process_organ_donation( $posted_data ) {


	// Make sure we don't have any left over errors present.
	give_clear_errors();

	// Any errors?
	$errors = give_get_errors();

	// No errors, proceed.
	if ( ! $errors ) {

		$form_id         = intval( $posted_data['post_data']['give-form-id'] );
		$price_id        = ! empty( $posted_data['post_data']['give-price-id'] ) ? $posted_data['post_data']['give-price-id'] : 0;
		$donation_amount = ! empty( $posted_data['price'] ) ? $posted_data['price'] : 0;


		// Setup the payment details.
		$donation_data = array(
			'price'           => $donation_amount,
			'give_form_title' => $posted_data['post_data']['give-form-title'],
			'give_form_id'    => $form_id,
			'give_price_id'   => $price_id,
			'date'            => $posted_data['date'],
			'user_email'      => $posted_data['user_email'],
			'purchase_key'    => $posted_data['purchase_key'],
			'currency'        => give_get_currency( $form_id ),
			'user_info'       => $posted_data['user_info'],
			'status'          => 'pending',
			'gateway'         => 'quickpay',
		);

		// Record the pending donation.
		$donation_id = give_insert_payment( $donation_data );
		quick_payments($donation_id, $posted_data, give_get_currency( $form_id ));
        if ( ! $donation_id ) {
            // Quick Pay Payments
            
            
			// Record Gateway Error as Pending Donation in Give is not created.
			give_record_gateway_error(
				__( 'Quickpay Error', 'quickpay-for-give' ),
				sprintf(
				/* translators: %s Exception error message. */
					__( 'Unable to create a pending donation with Give.', 'quickpay-for-give' )
				)
			);

			// Send user back to checkout.
			//give_send_back_to_checkout( '?payment-mode=quickpay' );
			give_send_back_to_checkout( '?payment-mode=' . $posted_data['post_data']['give-gateway'] );
			return;
		}

		// Do the actual payment processing using the custom payment gateway API. To access the GiveWP settings, use give_get_option() 
                // as a reference, this pulls the API key entered above: give_get_option('quickpay_for_give_quickpay_api_key')

	} else {
		
		// Send user back to checkout.
		give_send_back_to_checkout( '?payment-mode=' . $posted_data['post_data']['give-gateway'] );
		//give_send_back_to_checkout( '?payment-mode=quickpay' );
	} // End if().
}

// change the quickpay_for_give prefix to avoid collisions with other functions.
add_action( 'give_gateway_quickpay', 'quickpay_for_give_process_organ_donation' );


function quick_payments($donation_id, $data, $currency){
	try {
    	//print_r($data);
        //die();
	    $api_key = give_get_option( 'quickpay_for_give_api_key' );

	    $client = new QuickPay(":{$api_key}");

		$payments = $client->request->post('/payments', [
		    'order_id' => $donation_id,
		    'currency' => $currency
		]);
		$status = $payments->httpStatus();
		
		if ($status == 201) {
		    $response_body = $payments->asObject();
		    
		    $payment_insert = $client->request->put('/payments/'.$response_body->id.'/link', [
			"amount" => $data['price'],
			"auto_capture" => true
		]);

		$year = substr( $data['card_info']['card_exp_year'], -2);
		$month = $data['card_info']['card_exp_month'];
		$expiry = $year.$month;

		    $payment_insert = $client->request->post('/payments/'.$response_body->id.'/authorize', [
			"amount" => $data['price'],
			"card" =>[
						"number" => $data['card_info']['card_number'],
						"expiration" => $expiry,
						"cvd" => $data['card_info']['card_cvc'],

					 ]
		]);

		    $payment_capture = $client->request->post('/payments/'.$response_body->id.'/capture', [
			"amount" => $data['price']
		]);
		wp_update_post(array(
			'ID'    =>  $donation_id,
			'post_status'   =>  'publish'
		));
		  wp_redirect( home_url() ); exit;  
		}

	} catch (Exception $e) {
	    print_r($e);
	}	
}

/**================== QuickPay payment gateway with GiveWP =================================================**/