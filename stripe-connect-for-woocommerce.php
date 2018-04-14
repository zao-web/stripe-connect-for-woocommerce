<?php
/**
 * Plugin Name: Stripe Connect for WooCommerce
 * Plugin URI:  https://zao.is
 * Description: A modern Stripe Connect gateway for WooCommerce
 * Version:     0.1.0
 * Author:      Zao
 * Author URI:  https://zao.is
 * Donate link: https://zao.is
 * License:     MIT
 * Text Domain: stripe-connect-for-woocommerce
 * Domain Path: /languages
 *
 * @link    https://zao.is
 *
 * @package Stripe_Connect_For_WooCommerce
 * @version 0.1.0
 *
 * Built using generator-plugin-wp (https://github.com/WebDevStudios/generator-plugin-wp)
 */

/**
 * Copyright (c) 2018 Zao (email : justin@zao.is)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2 or, at
 * your discretion, any later version, as published by the Free
 * Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

 define( 'STRIPE_CONNECT_WC_VERSION',  '0.1.0' . ( defined( 'SCRIPT_DEBUG' ) ? time() : '' ) );
 define( 'STRIPE_CONNECT_WC_BASENAME', plugin_basename( __FILE__ ) );
 define( 'STRIPE_CONNECT_WC_URL',      plugin_dir_url( __FILE__ ) );
 define( 'STRIPE_CONNECT_WC_PATH',     dirname( __FILE__ ) . '/' );
 define( 'STRIPE_CONNECT_WC_INC',      STRIPE_CONNECT_WC_PATH . 'includes/' );

// Use composer autoload.
require 'vendor/autoload.php';

/**
 * Main initiation class.
 *
 * @since  0.1.0
 */
final class Stripe_Connect_For_WooCommerce {

	/**
	 * Current version.
	 *
	 * @var    string
	 * @since  0.1.0
	 */
	const VERSION = '0.1.0';

	/**
	 * URL of plugin directory.
	 *
	 * @var    string
	 * @since  0.1.0
	 */
	protected $url = '';

	/**
	 * Path of plugin directory.
	 *
	 * @var    string
	 * @since  0.1.0
	 */
	protected $path = '';

	/**
	 * Plugin basename.
	 *
	 * @var    string
	 * @since  0.1.0
	 */
	protected $basename = '';

	/**
	 * Detailed activation error messages.
	 *
	 * @var    array
	 * @since  0.1.0
	 */
	protected $activation_errors = array();

	/**
	 * Singleton instance of plugin.
	 *
	 * @var    Stripe_Connect_For_WooCommerce
	 * @since  0.1.0
	 */
	protected static $single_instance = null;

	/**
	 * Creates or returns an instance of this class.
	 *
	 * @since   0.1.0
	 * @return  Stripe_Connect_For_WooCommerce A single instance of this class.
	 */
	public static function get_instance() {
		if ( null === self::$single_instance ) {
			self::$single_instance = new self();
		}

		return self::$single_instance;
	}

	/**
	 * Sets up our plugin.
	 *
	 * @since  0.1.0
	 */
	protected function __construct() {
		$this->basename = plugin_basename( __FILE__ );
		$this->url      = plugin_dir_url( __FILE__ );
		$this->path     = plugin_dir_path( __FILE__ );
	}

	/**
	 * Attach other plugin classes to the base plugin class.
	 *
	 * @since  0.1.0
	 */
	public function plugin_classes() {
		include_once $this->path . 'includes/settings.php';
		include_once $this->path . 'includes/helpers.php';
		// $this->plugin_class = new SCFWC_Plugin_Class( $this );

	} // END OF PLUGIN CLASSES FUNCTION

	/**
	 * Add hooks and filters.
	 * Priority needs to be
	 * < 10 for CPT_Core,
	 * < 5 for Taxonomy_Core,
	 * and 0 for Widgets because widgets_init runs at init priority 1.
	 *
	 * @since  0.1.0
	 */
	public function hooks() {
		add_action( 'init'                              , [ $this, 'init' ], 0 );
		add_action( 'template_redirect'                 , [ $this, 'maybe_check_oauth' ] );
		add_action( 'woocommerce_before_template_part'  , [ $this, 'maybe_show_stripe_button' ] );
		add_filter( 'wc_stripe_settings'                , [ $this, 'add_connect_settings' ] );
		add_action( 'edit_user_profile'                 , [ $this, 'add_stripe_connect_fields_to_profile' ] );
		add_action( 'show_user_profile'                 , [ $this, 'add_stripe_connect_fields_to_profile' ] );
		add_action( 'personal_options_update'           , [ $this, 'add_stripe_connect_fields_to_usermeta' ] );
		add_action( 'edit_user_profile_update'          , [ $this, 'add_stripe_connect_fields_to_usermeta' ] );
		add_filter( 'wc_stripe_generate_payment_request', [ $this, 'add_transfer_group_to_stripe_charge' ], 10, 3 );
		add_action( 'wc_gateway_stripe_process_response', [ $this, 'create_payouts_to_each_seller' ]      , 10, 2 );
		add_filter( 'wcv_commission_rate_percent'       , [ $this, 'filter_wcv_commission' ]              , 10, 2 );
	}

	public function filter_wcv_commission( $commission, $product_id ) {
		return scfwc_get_seller_commission( WCV_Vendors::get_vendor_from_product( $product_id ) );
	}

	public function add_transfer_group_to_stripe_charge( $post_data, $order, $prepared_source ) {
		$post_data['transfer_group'] = self::get_order_transfer_number( $order );

		return $post_data;
	}

	public function create_payouts_to_each_seller( $response, $order ) {

		$data = [
			'currency'       => strtoupper( get_woocommerce_currency() ),
			'transfer_group' => self::get_order_transfer_number( $order )
		];

		$commissions = WCV_Vendors::get_vendor_dues_from_order( $order );

		// By default, WCV assumes the admin payout to the the 1 key in this method. We employ a different model.
		if ( isset( $commissions[1] ) ) {
			unset( $commissions[1] );
		}

		// Loop through each vendor, add 'destination' of Stripre account;, amount of tax + shipping + (subtotal * commission)
		foreach ( $commissions as $vendor_id => $commission ) {
			$acct = get_user_meta( $vendor_id, 'stripe_account_id', $account_id );

			if ( empty( $acct ) ) {
				$order->add_order_note( sprintf( __( 'Attempted to pay out %s to %s, but they do not have their Stripe account connected.' ), $commission['total'], get_user_by( 'id', $vendor_id )->display_name ) );
				continue;
			}

			$args     = array_merge( $data, [ 'destination' => $acct, 'amount' => $commission['total'] ] );
			$request  = apply_filters( 'stripe_connect_transfer_args', $data, $response, $order );
			$response = WC_Stripe_API::request( $request, 'transfers' );

			WC_Stripe_Logger::log( var_export( $response, 1 ) );
		}

	}

	public static function get_order_transfer_number( $order ) {
		return apply_filters( 'stripe_connect_transfer_group', sprintf( __( 'Order #%s' ), $order->get_id() ) );
	}

	public function add_connect_settings( $settings = array() ) {

		$settings['connect_header'] = array(
			'title'       => __( 'Stripe Connect Settings', 'woocommerce-gateway-stripe' ),
			'type'        => 'title',
			'description' => __( 'The following defaults will be applied to all seller accounts, and can be overriden at a seller level by admins in the User section.' ),
		);

		$settings['connect_payout_schedule_interval'] = array(
			'title'       => __( 'Payout Schedule', 'woocommerce-gateway-stripe' ),
			'label'       => __( 'Interval', 'woocommerce-gateway-stripe' ),
			'type'        => 'select',
			'description' => __( 'Select the payout schedule you would like for sellers by default. This is how often the seller will receive eligble payouts into their account.', 'woocommerce-gateway-stripe' ),
			'default'     => 'daily',
			'desc_tip'    => true,
			'options'     => array(
				'daily'   => __( 'Daily', 'woocommerce-gateway-stripe' ),
				'weekly'  => __( 'Weekly', 'woocommerce-gateway-stripe' ),
				'monthly' => __( 'Monthly', 'woocommerce-gateway-stripe' ),
			),
		);

		$settings['connect_payout_schedule_delay_days'] = array(
			'title'       => __( 'Payout Schedule (Delay Days)', 'woocommerce-gateway-stripe' ),
			'label'       => __( 'Delay Days', 'woocommerce-gateway-stripe' ),
			'type'        => 'text',
			'description' => __( 'Daily payouts will be delayed by this many days.', 'woocommerce-gateway-stripe' ),
			'default'     => '30',
			'desc_tip'    => true,
		);

		$settings['connect_payout_schedule_weekly_anchor'] = array(
			'title'       => __( 'Payout Schedule (Weekly Anchor)', 'woocommerce-gateway-stripe' ),
			'label'       => __( 'Weekly Anchor', 'woocommerce-gateway-stripe' ),
			'type'        => 'select',
			'description' => __( 'Weekly Payouts will be made on this day.', 'woocommerce-gateway-stripe' ),
			'default'     => 'monday',
			'desc_tip'    => true,
			'options'     => array(
				'monday'    => __( 'Monday', 'woocommerce-gateway-stripe' ),
				'tuesday'   => __( 'Tuesday', 'woocommerce-gateway-stripe' ),
				'wednesday' => __( 'Wednesday', 'woocommerce-gateway-stripe' ),
				'thursday'  => __( 'Thursday', 'woocommerce-gateway-stripe' ),
				'friday'    => __( 'Friday', 'woocommerce-gateway-stripe' ),
			),
		);

		$settings['connect_payout_schedule_monthly_anchor'] = array(
			'title'       => __( 'Payout Schedule (Daily Anchor)', 'woocommerce-gateway-stripe' ),
			'label'       => __( 'Daily Anchor', 'woocommerce-gateway-stripe' ),
			'type'        => 'text',
			'description' => __( 'Weekly payouts will be made on this day of the month.', 'woocommerce-gateway-stripe' ),
			'default'     => '15',
			'desc_tip'    => true,
		);

		$settings['connect_default_commission'] = array(
			'title'       => __( 'Payout Schedule (Default Commission)', 'woocommerce-gateway-stripe' ),
			'label'       => __( 'Default Commission', 'woocommerce-gateway-stripe' ),
			'type'        => 'text',
			'description' => __( 'This is the default amount sellers will receive, plus taxes and shipping.', 'woocommerce-gateway-stripe' ),
			'default'     => '85',
			'desc_tip'    => true,
		);

		return $settings;
	}

	public function maybe_show_stripe_button( $template ) {
		if ( 'report.php' !== $template ) {
			return;
		}

		include STRIPE_CONNECT_WC_INC . 'dashboard-stripe.php';
	}


	public function add_stripe_connect_fields_to_profile( $user ) {
		// Iterate through add_connect_settings()
    ?>
    <h3><?php _e( 'Stripe Connect Settings' ); ?></h3>
    <table class="form-table">
		<?php
			foreach ( $this->add_connect_settings() as $key => $data ) :
				if ( ! in_array( $data['type'], array( 'text', 'select' ), true ) ) {
					continue;
				}
		?>
        <tr>
            <th>
                <label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $data['title'] ); ?></label>
            </th>
            <td>
                <?php $this->generate_input( $key, $data, $user ); ?>
            </td>
        </tr>
	<?php endforeach; ?>
    </table>
    <?php
	}

	protected function generate_input( $key, $data, $user ) {
		$value = empty(  $user->{$key} ) ? $data['default'] : $user->{$key};

		if ( 'text' === $data['type'] ) { ?>

		<input type="text" class="regular-text ltr" id="<?php esc_attr_e( $key ); ?>" name="<?php esc_attr_e( $key ); ?>" value="<?php esc_attr_e( $value ); ?>"
			   title="<?php esc_attr_e( $data['title'] ); ?>">

	   <?php if ( ! empty( $data['description'] ) ) : ?>
			<p class="description"><?php echo esc_html( $data['description'] ); ?></p>
		<?php endif; ?>

		<?php
		}
		if ( 'select' === $data['type'] ) { ?>
			<select id="<?php esc_attr_e( $key ); ?>" name="<?php esc_attr_e( $key ); ?>" title="<?php esc_attr_e( $data['title'] ); ?>">
				<?php foreach ( $data['options'] as $name => $val ) : ?>
					<option name="<?php esc_attr_e( $name ); ?>" <?php selected( $val, $value, true ); ?>><?php echo esc_html( $val ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php if ( ! empty( $data['description'] ) ) : ?>
				<p class="description"><?php echo esc_html( $data['description'] ); ?></p>
		     <?php endif; ?>
		<?php

		}

	}

	/**
	 * The save action.
	 *
	 * @param $user_id int the ID of the current user.
	 *
	 * @return bool Meta ID if the key didn't exist, true on successful update, false on failure.
	 */
	function add_stripe_connect_fields_to_usermeta( $user_id ) {

	    // check that the current user have the capability to edit the $user_id
	    if ( ! current_user_can( 'edit_user', $user_id ) ) {
	        return false;
	    }

		foreach ( $this->add_connect_settings() as $key => $data ) {
			if ( isset( $_POST[ $key ] ) ) {
				// create/update user meta for the $user_id
				update_user_meta( $user_id, 'birthday', sanitize_text_field( $_POST[ $key ] ) );
			}
		}

	}

	public function maybe_check_oauth() {

		if ( ! isset( $_GET['code'] ) && ! isset( $_GET['error'] ) ) {
			return;
		}

		if ( ! is_page( 'dashboard' ) ) {
			return;
		}

		if ( ! is_user_logged_in() ) {
			return;
		}

		include 'oauth/oauth/connect.php';

	}

	/**
	 * Activate the plugin.
	 *
	 * @since  0.1.0
	 */
	public function _activate() {
		// Bail early if requirements aren't met.
		if ( ! $this->check_requirements() ) {
			return;
		}

		// Make sure any rewrite functionality has been loaded.
		flush_rewrite_rules();
	}

	/**
	 * Deactivate the plugin.
	 * Uninstall routines should be in uninstall.php.
	 *
	 * @since  0.1.0
	 */
	public function _deactivate() {
		// Add deactivation cleanup functionality here.
	}

	/**
	 * Init hooks
	 *
	 * @since  0.1.0
	 */
	public function init() {

		// Bail early if requirements aren't met.
		if ( ! $this->check_requirements() ) {
			return;
		}

		// Load translated strings for plugin.
		load_plugin_textdomain( 'stripe-connect-for-woocommerce', false, dirname( $this->basename ) . '/languages/' );

		// Initialize plugin classes.
		$this->plugin_classes();
	}

	/**
	 * Check if the plugin meets requirements and
	 * disable it if they are not present.
	 *
	 * @since  0.1.0
	 *
	 * @return boolean True if requirements met, false if not.
	 */
	public function check_requirements() {

		// Bail early if plugin meets requirements.
		if ( $this->meets_requirements() ) {
			return true;
		}

		// Add a dashboard notice.
		add_action( 'all_admin_notices', array( $this, 'requirements_not_met_notice' ) );

		// Deactivate our plugin.
		add_action( 'admin_init', array( $this, 'deactivate_me' ) );

		// Didn't meet the requirements.
		return false;
	}

	/**
	 * Deactivates this plugin, hook this function on admin_init.
	 *
	 * @since  0.1.0
	 */
	public function deactivate_me() {

		// We do a check for deactivate_plugins before calling it, to protect
		// any developers from accidentally calling it too early and breaking things.
		if ( function_exists( 'deactivate_plugins' ) ) {
			deactivate_plugins( $this->basename );
		}
	}

	/**
	 * Check that all plugin requirements are met.
	 *
	 * @since  0.1.0
	 *
	 * @todo Add checks for WooCommerce and WooCommerce Stripe Gateway
	 * @return boolean True if requirements are met.
	 */
	public function meets_requirements() {

		// Do checks for required classes / functions or similar.
		// Add detailed messages to $this->activation_errors array.
		return true;
	}

	/**
	 * Adds a notice to the dashboard if the plugin requirements are not met.
	 *
	 * @since  0.1.0
	 */
	public function requirements_not_met_notice() {

		// Compile default message.
		$default_message = sprintf( __( 'Stripe Connect for WooCommerce is missing requirements and has been <a href="%s">deactivated</a>. Please make sure all requirements are available.', 'stripe-connect-for-woocommerce' ), admin_url( 'plugins.php' ) );

		// Default details to null.
		$details = null;

		// Add details if any exist.
		if ( $this->activation_errors && is_array( $this->activation_errors ) ) {
			$details = '<small>' . implode( '</small><br /><small>', $this->activation_errors ) . '</small>';
		}

		// Output errors.
		?>
		<div id="message" class="error">
			<p><?php echo wp_kses_post( $default_message ); ?></p>
			<?php echo wp_kses_post( $details ); ?>
		</div>
		<?php
	}

	/**
	 * Magic getter for our object.
	 *
	 * @since  0.1.0
	 *
	 * @param  string $field Field to get.
	 * @throws Exception     Throws an exception if the field is invalid.
	 * @return mixed         Value of the field.
	 */
	public function __get( $field ) {
		switch ( $field ) {
			case 'version':
				return self::VERSION;
			case 'basename':
			case 'url':
			case 'path':
				return $this->$field;
			default:
				throw new Exception( 'Invalid ' . __CLASS__ . ' property: ' . $field );
		}
	}
}

/**
 * Grab the Stripe_Connect_For_WooCommerce object and return it.
 * Wrapper for Stripe_Connect_For_WooCommerce::get_instance().
 *
 * @since  0.1.0
 * @return Stripe_Connect_For_WooCommerce  Singleton instance of plugin class.
 */
function stripe_connect_for_woocommerce() {
	return Stripe_Connect_For_WooCommerce::get_instance();
}

// Kick it off.
add_action( 'plugins_loaded', array( stripe_connect_for_woocommerce(), 'hooks' ) );

// Activation and deactivation.
register_activation_hook( __FILE__, array( stripe_connect_for_woocommerce(), '_activate' ) );
register_deactivation_hook( __FILE__, array( stripe_connect_for_woocommerce(), '_deactivate' ) );
