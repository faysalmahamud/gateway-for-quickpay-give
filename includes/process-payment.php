<?php
/**
 * Process QuickPay Payments.
 */
#use QuickPay\QuickPay;
use Kameli\Quickpay\Quickpay;

function quick_payments($donation_id, $data, $currency){
	try {
    	//print_r($data);
        //die();
	    $api_key = give_get_option( 'quickpay_for_give_api_key' );

	    $qp =  new Quickpay($api_key);

		$payment = $qp->payments()->create([
		    'currency' => $currency,
		    'order_id' => $donation_id,
		]);		

		if (!empty($payment) && !empty($payment->getId())) {
			give_insert_payment_note( $donation_id, __( 'Payment Create Successful. Quickpay Transaction ID:'. $payment->getId() ) );

		    $qp->payments()->link($payment->getId(), [
				"amount" => $data['price'],
				"auto_capture" => true
			]);

			$year = substr( $data['card_info']['card_exp_year'], -2);
			$month = $data['card_info']['card_exp_month'];
			$expiry = $year.$month;

		    $payment_insert = $qp->payments()->authorize($payment->getId(), [
			"amount" => $data['price'],
			"card" =>[
						"number" => $data['card_info']['card_number'],
						"expiration" => $expiry,
						"cvd" => $data['card_info']['card_cvc'],

					 ]
		]);

		$payment_capture = $qp->payments()->capture($payment->getId(), [
			"amount" => $data['price']
		]);

		give_insert_payment_note( $donation_id, __( 'Transaction Successful. Quickpay Transaction ID:'.$payment->getId() ) );

		give_set_payment_transaction_id( $donation_id, $payment->getId() );

		wp_update_post(array(
			'ID'    =>  $donation_id,
			'post_status'   =>  'publish'
		));
		  give_send_to_success_page();  
		}

	} catch (Exception $e) {
		give_record_gateway_error(
			__( 'Quickpay Error', 'give-quickpay' ),
			__( 'Transaction Failed.', 'give-quickpay' )
			. '<br><br>' . sprintf( esc_attr__( 'Error Detail: %s', 'give-quickpay' ), '<br>' . print_r( $e->getMessage(), true ) )
		);

		give_set_error( 'give-quickpay', __( 'An error occurred while processing your payment. Please try again.', 'give-quickpay' ) );

		// Problems? Send back.
		give_send_back_to_checkout();
	}	
}

add_action( 'give_gateway_quickpay', 'give_quickpay_process_payment' );

function give_quickpay_process_payment( $posted_data ) {


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
	}
}