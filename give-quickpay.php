<?php
/**
 * Plugin Name: Payment Gateway for Quickpay on Give
 * Plugin URI: https://github.com/faysalmahamud/give-wp-quickpay
 * Description: Process online donations via the Quickpay payment gateway.
 * Author: Faysal Mahamud
 * Author URI: https://www.linkedin.com/in/turjo
 * Version: 1.0.3
 * Text Domain: gateway-for-quickpay-give
 * Domain Path: /languages
 * GitHub Plugin URI: https://github.com/faysalmahamud/give-wp-quickpay
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
  exit;
}

class Give_Qucikpay_Gateway {

	/**
	 * @since  1.0
	 * @access static
	 * @var Give_Qucikpay_Gateway $instance
	 */
	static private $instance;

	/**
	 * Notices (array)
	 *
	 * @since 1.2.1
	 *
	 * @var array
	 */
	public $notices = array();

	/**
	 * Singleton pattern.
	 *
	 * Give_Qucikpay_Gateway constructor.
	 */
	private function __construct() {
	}


	/**
	 * Get instance
	 *
	 * @since  1.0
	 * @access static
	 * @return Give_Qucikpay_Gateway|static
	 */
	static function get_instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
			self::$instance->setup();
		}

		return self::$instance;
	}

	/**
	 * Setup Give Mollie.
	 *
	 * @since  1.2.1
	 * @access private
	 */
	private function setup() {

		// Setup constants.
		$this->setup_constants();

		// Give init hook.
		add_action( 'give_init', array( $this, 'init' ), 10 );
		add_action( 'admin_init', array( $this, 'check_environment' ), 999 );
		add_action( 'admin_notices', array( $this, 'admin_notices' ), 15 );
	}

	/**
	 * Init the plugin in give_init so environment variables are set.
	 *
	 * @since 1.2.1
	 */
	function init() {

		if ( ! $this->get_environment_warning() ) {
			return;
		}

		$this->load_files();
		//$this->setup_hooks();
		$this->load_textdomain();
		//$this->activation_banner();

		add_filter( 'give_recurring_available_gateways', array( $this, 'register_quickpay_gateway') );

		// Add license.
		if ( class_exists( 'Give_License' ) ) {
			new Give_License( GIVE_QUCIKPAY_FILE, 'QUCIKPAY Gateway', GIVE_QUCIKPAY_VERSION, 'WordImpress' );
		}

	}

	public function register_quickpay_gateway($gateways){
		$gateways['quickpay'] = "Give_Recurring_QuickPay";

		return $gateways;
	}
	/**
	 * Setup constants.
	 *
	 * @since  1.0
	 * @access public
	 */
	public function setup_constants() {

		// Global Params.
		if ( ! defined( 'GIVE_QUCIKPAY_VERSION' ) ) {
			define( 'GIVE_QUCIKPAY_VERSION', '1.2.1' );
		}

		if ( ! defined( 'GIVE_QUCIKPAY_MIN_GIVE_VER' ) ) {
			define( 'GIVE_QUCIKPAY_MIN_GIVE_VER', '2.3.1' );
		}

		if ( ! defined( 'GIVE_QUCIKPAY_FILE' ) ) {
			define( 'GIVE_QUCIKPAY_FILE', __FILE__ );
		}

		if ( ! defined( 'GIVE_QUCIKPAY_BASENAME' ) ) {
			define( 'GIVE_QUCIKPAY_BASENAME', plugin_basename( GIVE_QUCIKPAY_FILE ) );
		}

		if ( ! defined( 'GIVE_QUCIKPAY_URL' ) ) {
			define( 'GIVE_QUCIKPAY_URL', plugins_url( '/', GIVE_QUCIKPAY_FILE ) );
		}

		if ( ! defined( 'GIVE_QUCIKPAY_DIR' ) ) {
			define( 'GIVE_QUCIKPAY_DIR', plugin_dir_path( GIVE_QUCIKPAY_FILE ) );
		}
	}

	/**
	 * Load files.
	 *
	 * @since  1.0
	 * @access public
	 * @return Give_Qucikpay_Gateway
	 */
	public function load_files() {

		if ( file_exists( GIVE_QUCIKPAY_DIR . 'includes/vendor/autoload.php' ) ) {
			require_once GIVE_QUCIKPAY_DIR . 'includes/vendor/autoload.php';
		}

		// Load helper functions.
		require_once GIVE_QUCIKPAY_DIR . 'includes/functions.php';

		// Load plugin settings.
		require_once GIVE_QUCIKPAY_DIR . 'includes/admin/admin-settings.php';

		require_once GIVE_RECURRING_PLUGIN_DIR . 'includes/gateways/give-recurring-gateway.php';

		require_once GIVE_QUCIKPAY_DIR . 'includes/class-give-recurring-qucikpay.php';
		// Load frontend actions.
		//require_once GIVE_QUCIKPAY_DIR . 'includes/actions.php';

		// Process payment
		require_once GIVE_QUCIKPAY_DIR . 'includes/process-payment.php';


		// if ( is_admin() ) {
		// 	// Load admin actions..
		// 	require_once GIVE_QUCIKPAY_DIR . 'includes/admin/actions.php';
		// }

		return self::$instance;
	}


	/**
	 * Setup hooks.
	 *
	 * @since  1.0
	 * @access public
	 * @return Give_Qucikpay_Gateway
	 */
	public function setup_hooks() {
		add_action( 'wp_enqueue_scripts', array( $this, 'frontend_enqueue' ) );

		return self::$instance;
	}

	/**
	 * Load frontend scripts
	 *
	 * @since  1.0
	 * @access public
	 */
	public function frontend_enqueue() {
		if ( give_Qucikpay_is_active() ) {
			wp_register_script( 'Qucikpay-js', 'https://checkout.Qucikpay.com/v1/checkout.js' );
			wp_enqueue_script( 'Qucikpay-js' );

			wp_register_script( 'give-Qucikpay-popup-js', GIVE_QUCIKPAY_URL . 'assets/js/give-Qucikpay-popup.js', array( 'jquery' ), false, GIVE_QUCIKPAY_VERSION );
			wp_enqueue_script( 'give-Qucikpay-popup-js' );

			$merchant = give_Qucikpay_get_merchant_credentials();
			$data     = array(
				'merchant_key_id' => $merchant['merchant_key_id'],
				'popup'           => array(
					'color' => give_get_option( 'Qucikpay_popup_theme_color' ),
					'image' => give_get_option( 'Qucikpay_popup_image' ),
				),
				'setup_order_url' => add_query_arg( array( 'give_action' => 'give_process_Qucikpay' ), home_url() ),
				'clear_order_url' => add_query_arg( array( 'give_action' => 'give_clear_order' ), home_url() ),
			);

			wp_localize_script( 'give-Qucikpay-popup-js', 'give_Qucikpay_vars', $data );
		}
	}


	/**
	 * Check plugin environment.
	 *
	 * @since  1.2.1
	 * @access public
	 *
	 * @return bool
	 */
	public function check_environment() {
		// Flag to check whether plugin file is loaded or not.
		$is_working = true;

		// Load plugin helper functions.
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . '/wp-admin/includes/plugin.php';
		}

		/* Check to see if Give is activated, if it isn't deactivate and show a banner. */
		// Check for if give plugin activate or not.
		$is_give_active = defined( 'GIVE_PLUGIN_BASENAME' ) ? is_plugin_active( GIVE_PLUGIN_BASENAME ) : false;

		if ( empty( $is_give_active ) ) {
			// Show admin notice.
			$this->add_admin_notice( 'prompt_give_activate', 'error', sprintf( __( '<strong>Activation Error:</strong> You must have the <a href="%s" target="_blank">Give</a> plugin installed and activated for the Qucikpay add-on to activate.', 'give-Qucikpay' ), 'https://givewp.com' ) );
			$is_working = false;
		}

		return $is_working;
	}

	/**
	 * Check plugin for Give environment.
	 *
	 * @since  1.2.1
	 * @access public
	 *
	 * @return bool
	 */
	public function get_environment_warning() {
		// Flag to check whether plugin file is loaded or not.
		$is_working = true;

		// Verify dependency cases.
		if (
			defined( 'GIVE_VERSION' )
			&& version_compare( GIVE_VERSION, GIVE_QUCIKPAY_MIN_GIVE_VER, '<' )
		) {

			/* Min. Give. plugin version. */
			// Show admin notice.
			$this->add_admin_notice( 'prompt_give_incompatible', 'error', sprintf( __( '<strong>Activation Error:</strong> You must have the <a href="%s" target="_blank">Give</a> core version %s for the Qucikpay add-on to activate.', 'give-Qucikpay' ), 'https://givewp.com', GIVE_QUCIKPAY_MIN_GIVE_VER ) );

			$is_working = false;
		}

		return $is_working;
	}


	/**
	 * Load the text domain.
	 *
	 * @access private
	 * @since  1.0
	 *
	 * @return void
	 */
	public function load_textdomain() {

		// Set filter for plugin's languages directory.
		$give_Qucikpay_lang_dir = dirname( plugin_basename( GIVE_QUCIKPAY_FILE ) ) . '/languages/';
		$give_Qucikpay_lang_dir = apply_filters( 'give_quickpay_languages_directory', $give_Qucikpay_lang_dir );

		// Traditional WordPress plugin locale filter
		$locale = apply_filters( 'plugin_locale', get_locale(), 'give-quickpay' );
		$mofile = sprintf( '%1$s-%2$s.mo', 'give-quickpay', $locale );

		// Setup paths to current locale file
		$mofile_local  = $give_Qucikpay_lang_dir . $mofile;
		$mofile_global = WP_LANG_DIR . '/give-quickpay/' . $mofile;

		if ( file_exists( $mofile_global ) ) {
			// Look in global /wp-content/languages/give-Qucikpay folder
			load_textdomain( 'give-quickpay', $mofile_global );
		} elseif ( file_exists( $mofile_local ) ) {
			// Look in local /wp-content/plugins/give-Qucikpay/languages/ folder
			load_textdomain( 'give-quickpay', $mofile_local );
		} else {
			// Load the default language files
			load_plugin_textdomain( 'give-quickpay', false, $give_Qucikpay_lang_dir );
		}

	}


	/**
	 * Allow this class and other classes to add notices.
	 *
	 * @since 1.2.1
	 *
	 * @param $slug
	 * @param $class
	 * @param $message
	 */
	public function add_admin_notice( $slug, $class, $message ) {
		$this->notices[ $slug ] = array(
			'class'   => $class,
			'message' => $message,
		);
	}

	/**
	 * Display admin notices.
	 *
	 * @since 1.2.1
	 */
	public function admin_notices() {

		$allowed_tags = array(
			'a'      => array(
				'href'  => array(),
				'title' => array(),
				'class' => array(),
				'id'    => array(),
			),
			'br'     => array(),
			'em'     => array(),
			'span'   => array(
				'class' => array(),
			),
			'strong' => array(),
		);

		foreach ( (array) $this->notices as $notice_key => $notice ) {
			echo "<div class='" . esc_attr( $notice['class'] ) . "'><p>";
			echo wp_kses( $notice['message'], $allowed_tags );
			echo '</p></div>';
		}

	}

}

if ( ! function_exists( 'Give_Qucikpay_Gateway' ) ) {
	function Give_Qucikpay_Gateway() {
		return Give_Qucikpay_Gateway::get_instance();;
	}

	Give_Qucikpay_Gateway();
}
