<?php
/**
 * Give - Quickpay | Recurring Donations Support.
 *
 * @since 1.9.5
 */

// Bailout, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Kameli\Quickpay\Quickpay;
/**
 * This class exists check ensure that Recurring donations add-on is installed and the Give_Recurring_Quickpay class not exists.
 *
 * @since 1.9.5
 */
if ( ! class_exists( 'Give_Recurring_QuickPay' ) ) {

	/**
	 * Class Give_Recurring_Quickpay
	 *
	 * @since 1.9.5
	 */
	class Give_Recurring_QuickPay extends Give_Recurring_Gateway {

		/**
		 * Quickpay API.
		 *
		 * @since  1.9.5
		 * @access public
		 *
		 * @var $Quickpay_api
		 */
		public $quickpay_api;

		/**
		 * Give_Recurring_Quickpay constructor.
		 *
		 * @since  1.9.5
		 * @access public
		 *
		 * @return void
		 */
		public function init() {

			$this->id = 'quickpay';
			$api_key = give_get_option( 'quickpay_for_give_api_key' );
			$this->quickpay_api = new Quickpay($api_key);
			add_action( "give_recurring_cancel_{$this->id}_subscription", array( $this, 'cancel' ), 10, 2 );
		}
		
		public function create_payment_profiles() {
			$form_id           = absint( $this->purchase_data['post_data']['give-form-id'] );
			$currency = give_get_currency( $form_id );

			
			$amount             = $this->purchase_data['post_data']['give-amount'];
			try {
			$subscription = $this->quickpay_api->subscriptions()->create([
			    'currency' => $currency,
			    'order_id' => $this->purchase_data['post_data']['give-recurring-period-donors-choice']."_".rand(0,100000),
			    'description' => $this->purchase_data['post_data']['give-recurring-period-donors-choice'],
			]);

			if($subscription->getId()){
				give_get_subscription_note_html();
				give_insert_payment_note( $this->payment_id, __( 'Subscription ID:'.$subscription->getId() ) );
			}

			$link = $this->quickpay_api->subscriptions()->link($subscription->getId(), [
			    'amount' => $amount, // the amount does not matter here, but is still required for some reason
			]);

			// // Make the user follow the payment link which will take them to a form where they put in their card details
			$url = $link->getUrl();
			if($link){
				give_insert_payment_note( $this->payment_id, __( 'Link Url:'.$url ) );	
			}

			$year = $this->purchase_data['post_data']['card_exp_year'];
			//$year = substr( $this->purchase_data['post_data']['card_exp_year'], -2);
			$month = $this->purchase_data['post_data']['card_exp_month'];
			$expiry = $year.$month;

			$authorize = $this->quickpay_api->subscriptions()->authorize($subscription->getId(), [
					"amount" => $amount,
					"card" =>[
								"number" => $this->purchase_data['post_data']['card_number'],
								"expiration" => 2112,
								"cvd" => $this->purchase_data['post_data']['card_cvc'],

					]
			]);
			if($authorize){
				give_insert_payment_note( $this->payment_id, __( 'Authorize Payment:'.$subscription->getId() ) );	
			}
				$payment = $this->quickpay_api->subscriptions()->recurring($subscription->getId(), [
				    'amount' => $amount,
				    'order_id' => $this->purchase_data['post_data']['give-recurring-period-donors-choice']."_rec_".rand(10,1000),
				]);
				if($payment->getId()){
					
					give_set_payment_transaction_id( $this->payment_id, $payment->getId() );

					give_insert_payment_note( $this->payment_id, __( 'Payment ID:'.$payment->getId() ) );
					
					$this->quickpay_api->payments()->captureAmount($payment->getId(), $payment->amount());
					
					give_insert_payment_note( $this->payment_id, __( 'Captured Payments:'.$payment->getId() ) );
				}
			} 
			catch (Exception $e) {
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
		
		public function can_cancel( $ret, $subscription ) {

			if (
				$subscription->gateway === $this->id &&
				! empty( $subscription->profile_id ) &&
				'active' === $subscription->status
			) {
				$ret = true;
			}

			return $ret;
		}

		public function cancel( $subscription, $now = true ) {



		}		
		public function can_update_subscription( $ret, $subscription ) {

			if (
				$subscription->gateway === $this->id &&
				! empty( $subscription->profile_id ) &&
				'active' === $subscription->status
			) {
				return true;
			}

			return $ret;
		}
		public function can_sync( $ret, $subscription ) {

			if (
				$subscription->gateway === $this->id &&
				! empty( $subscription->profile_id ) &&
				'active' === $subscription->status
			) {
				$ret = true;
			}

			return $ret;
		}
		public function link_profile_id( $profile_id, $subscription ) {

			if ( ! empty( $profile_id ) ) {
				$payment    = new Give_Payment( $subscription->parent_payment_id );
				$html       = '<a href="%s" target="_blank">' . $profile_id . '</a>';
				$base_url   = 'live' === $payment->mode ? 'https://dashboard.quickpay.com/' : 'https://dashboard.quickpay.com/test/';
				$link       = esc_url( $base_url . 'subscriptions/' . $profile_id );
				$profile_id = sprintf( $html, $link );
			}

			return $profile_id;

		}				
			
	}

	new Give_Recurring_QuickPay();
}