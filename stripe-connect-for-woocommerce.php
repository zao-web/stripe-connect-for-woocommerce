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
		add_filter( 'woocommerce_shipping_packages'     , [ $this, 'add_shipping_package_meta' ] );
		add_filter( 'wcv_vendor_dues'                   , [ $this, 'add_shipping_tax_to_commissions' ], 10, 3 );

		if ( isset( $_GET['role'] ) && 'vendor' === $_GET['role'] ) {
			add_filter( 'manage_users_columns'              , [ $this, 'add_stripe_id_column' ] );
			add_filter( 'manage_users_custom_column'        , [ $this, 'render_stripe_id_column_data' ], 10, 3 );
		}

		// TODO: get this working so that the commissions match the actual payouts.
		// add_filter( 'wcv_vendor_dues'                   , [ $this, 'maybe_modify_totals' ]            , 20, 3 );
		add_action( 'init'                              , 'scfwc_maybe_charge_monthly_fee' );
	}

	/**
	 * Adds taxes generated from TaxJar for shipping rates to commissions prior to insertion.
	 *
	 * @param Array    $receiver Array of commission receivers
	 * @param WC_Order $order    WooCommerce Order Object.
	 * @param Bool     $group    Whether or not to group vendor products into one array or several.
	 * @return void
	 */
	public function add_shipping_tax_to_commissions( $receiver, $order, $group ) {
		$shipping = $order->get_items( 'shipping' );
		$rates    = [];

		foreach ( $shipping as $items ) {
			$rates[ $items->get_meta( 'vendor_id' ) ] = $items->get_total_tax();
		}

		foreach ( $receiver as $vendor_id => $data ) {

			if ( ! isset( $rates[ $vendor_id ] ) || ! $rates[ $vendor_id ] ) {
				continue;
			}

			// We've now know we have taxes on shipping - now we just need to add to either the grouped tax rate, or the first of the non-grouped tax rates.
			if ( $group ) {
				 $receiver[ $vendor_id ]['tax'] += $rates[ $vendor_id ];
				 continue;
			} else {
				foreach ( $data as $product_id => $contents ) {
					$receiver[ $vendor_id ][$product_id]['tax'] += $rates[ $vendor_id ];
					continue 2;
				}
			}
		}

		return $receiver;
	}

	/**
	 * Adds taxes generated from TaxJar for shipping rates to commissions prior to insertion.
	 *
	 * @param Array    $receiver Array of commission receivers
	 * @param WC_Order $order    WooCommerce Order Object.
	 * @param Bool     $group    Whether or not to group vendor products into one array or several.
	 * @return void
	 */
	public function maybe_modify_totals( $receiver, $order, $group ) {

		$is_running_completed  = doing_action( 'woocommerce_order_status_completed' );
		$is_running_processing = doing_action( 'woocommerce_order_status_processing' );

		if ( isset( $receiver[1] ) ) {
			unset( $receiver[1] );
		}

		foreach ( $receiver as $vendor_id => $data ) {
			$receiver[ $vendor_id ]['commission'] = $this->prepare_commission( $vendor_id, $order, $data, false );
		}

		return $receiver;
	}

	/**
	 * Returns Vendor Commission
	 *
	 * The commission should be their percentage of the NET Stripe payout on their items, plus taxes + shipping, minus monthly fees.
	 *
	 * @param [type] $vendor_id
	 * @param [type] $order
	 * @return void
	 */
	protected function prepare_commission( $vendor_id, $order, $commission, $log = true ) {
		$vendor_name = get_user_by( 'id', $vendor_id )->display_name;


		// For now, we'll override the commission calculation, which for whatever reason, is not taking our custom rates into account
		// TODO: Determine why that is - possibly hooking in too late.
		$commission['commission'] = $this->calculate_base_total( $vendor_id, $order );

		$log = [
			'Base seller payout for ' . $vendor_name . ' for this order was ' . $commission['commission'] . ' based on a commission of ' . scfwc_get_seller_commission( $vendor_id ) . '%'
		];

		$log[] = 'Subtotal = Base plus tax & shipping is ' . round( $commission['commission'] + $commission['tax'] + $commission['shipping'], 2 );

		$total       =  round( $commission['commission'] + $commission['tax'] + $commission['shipping'], 2 );

		$stripe_fee  = $this->get_stripe_fee_portion( $vendor_id, $order, $commission );
		$total      -= $stripe_fee;
		$log[]       = 'Total = subtotal of ' . $total . ' less Stripe fee portion of ' . $stripe_fee .' is ' . ( $total - $stripe_fee );

		$payout_fee  = $this->get_payout_fee( $vendor_id, $order, $commission );
		$total      -= $payout_fee;
		$log[]       = 'Payout fee of 0.25% of the total products, shipping, and taxes for ' . $vendor_name . ' is ' . $payout_fee;

		$monthly_fee = $this->maybe_process_monthly_fee( $vendor_id, $commission['total'] );

		if ( $monthly_fee ) {
			$log[] = 'Monthly fee of  ' . $monthly_fee . ' was due for seller, resulting in a total transfer of ' . round( $total - $monthly_fee, 2 );
			$total -= $monthly_fee;
		} else {
			$log[] = 'No monthly fee was due for seller, resulting in a total transfer of ' . $total;
		}

		if ( $log ) {
			$order->add_order_note( implode( '<br />', $log ) );
		}

		return round( $total, 2 );
	}

	/**
	 * Returns vendor's portion of Stripe fee to pay.
	 *
	 * Not every vendor will have equal amounts of the order - imagine one vendor selling $50 of a $2,000 order.
	 * If that order has one other vendor with $1,950 in the order, but they split the $58.30 fee evenly, that's not fair.
	 *
	 * @param [type] $vendor_id
	 * @param [type] $order
	 * @param [type] $commission
	 * @return void
	 */
	protected function get_stripe_fee_portion( $vendor_id, $order, $commission ) {

		$total_stripe_fee = WC_Stripe_Helper::get_stripe_fee( $order );
		$order_total      = $order->get_total();
		$base             = $commission['tax'] + $commission['shipping']; // The Commission object already did the hard work of getting per-vendor tax/shipping.

		$vendor_totals = 0;

		foreach ( $order->get_items() as $item ) {
			$vendor = WCV_Vendors::get_vendor_from_product( $item->get_product()->get_id() );

			if ( $vendor == $vendor_id ) {
				$vendor_totals += $item->get_total();
			}
		}

		$vendor_totals += $base;

		$portion = round( $total_stripe_fee * ( $vendor_totals / $order_total ), 2 );

		return apply_filters( 'get_stripe_fee_portion', $portion, $vendor_id, $order, $commission );
	}

	/**
	 * Returns proper base vendor commission.
	 *
	 * Not every vendor will have equal amounts of the order - imagine one vendor selling $50 of a $2,000 order.
	 * If that order has one other vendor with $1,950 in the order, but they split the $58.30 fee evenly, that's not fair.
	 *
	 * @param [type] $vendor_id
	 * @param [type] $order
	 * @return void
	 */
	protected function calculate_base_total( $vendor_id, $order ) {

		$commission  = scfwc_get_seller_commission( $vendor_id ) / 100;
		$order_total = $order->get_total();

		$vendor_totals = 0;

		foreach ( $order->get_items() as $item ) {
			$vendor = WCV_Vendors::get_vendor_from_product( $item->get_product()->get_id() );

			if ( $vendor == $vendor_id ) {
				$vendor_totals += $item->get_total();
			}
		}

		$portion = round( ( $commission * $vendor_totals ), 2 );

		return apply_filters( 'calculate_base_seller_commission_total', $portion, $vendor_id, $order );
	}

	/**
	 * Returns vendor's portion of Stripe payout fee to pay.
	 *
	 * This amounts to 0.25% of a vendor's payout, based on the subtotal prior to Chamfr's fee, not after.
	 * This fee is also based on the inclusion of taxes and shipping..
	 *
	 * @param [type] $vendor_id
	 * @param [type] $order
	 * @param [type] $commission
	 * @return void
	 */
	protected function get_payout_fee( $vendor_id, $order, $commission ) {

		$payout_fee  = apply_filters( 'default_payout_fee_percentage', 0.0025, $vendor_id, $order, $commission );
		$order_total = $order->get_total();
		$base        = $commission['tax'] + $commission['shipping']; // The Commission object already did the hard work of getting per-vendor tax/shipping.

		$vendor_totals = 0;

		foreach ( $order->get_items() as $item ) {
			$vendor = WCV_Vendors::get_vendor_from_product( $item->get_product()->get_id() );

			if ( $vendor == $vendor_id ) {
				$vendor_totals += $item->get_total();
			}
		}

		$vendor_totals += $base;

		$total   = $payout_fee * $vendor_totals;
		$portion = ( floor( 100 * $total ) + floor( 100 * $total - floor( 100 * $total ) ) ) / 100;

		return apply_filters( 'get_payout_fee', $portion, $vendor_id, $order, $commission );
	}

	protected function get_items_list( $contents ) {

		$items_list = [];

		foreach ( $contents as $item ) {
			$items_list[] = $item['data']->get_name() . ' × ' . $item['quantity'];
		}

		return implode( ', ', $items_list );
	}

	public function split_shipping( $items, $total ){

		$last_item_id    = '';
		$total_remaining = 0;

		$new_shipping_cost = ( $total == 0 ) ? 0 : $total / count( $items );

		foreach ( $items as $item_id => $details ) {
			$items[ $item_id ][ 'shipping_cost' ] = number_format( $new_shipping_cost, 2 );
			$last_item_id = $item_id;
			$total -= number_format( $new_shipping_cost, 2 );
		}

		// Make sure any uneven splits are still stored correctly for commissions
		$items[ $last_item_id ][ 'shipping_cost' ] += number_format( $total, 2 );
		$items[ $last_item_id ][ 'shipping_cost' ]  = number_format( $items[ $last_item_id ][ 'shipping_cost' ], 2 );

		return apply_filters( 'wcv_split_shipping_items', $items );

	}

	public function prepare_items( $items, $total ) {
		$items = array_map( function( $item ) {
			return array(
				'product_id'    => $item['product_id'],
			 );
		}, $items );

		return $this->split_shipping( $items, $total ) ;
	}

	public function add_shipping_package_meta( $packages ) {

		foreach ( $packages as $index => $package ) {

			foreach ( $package['rates'] as $rate_id => $rate ) {

				if ( false !== stristr( $rate_id, 'ups' ) || false !== stristr( $rate_id, 'fedex' ) ) {

					$total_shipping = $rate->get_cost();
					$items          = $this->prepare_items( $package['contents'], $total_shipping );

					$rate->add_meta_data( 'Items', $this->get_items_list( $package['contents'] ) );

					$rate->add_meta_data(
						'vendor_costs',
						array(
							'total_shipping' => $total_shipping,
							'total_cost'     => $package['contents_cost'],
							'items'          => $items
						)
					);

					$rate->add_meta_data( 'vendor_id', $package['vendor_id'] );

				}
			}
		}

		return $packages;
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
			'currency'           => strtoupper( get_woocommerce_currency() ),
			'transfer_group'     => self::get_order_transfer_number( $order ),
			'source_transaction' => $response->id
		];

		$commissions  = WCV_Vendors::get_vendor_dues_from_order( $order );

		// By default, WCV assumes the admin payout to the the 1 key in this method. We employ a different model.
		if ( isset( $commissions[1] ) ) {
			unset( $commissions[1] );
		}

		// Loop through each vendor, add 'destination' of Stripe account;, amount of tax + shipping + (subtotal * commission)
		foreach ( $commissions as $vendor_id => $commission ) {
			$acct = get_user_meta( $vendor_id, 'stripe_account_id', true );

			$total = $this->prepare_commission( $vendor_id, $order, $commission, 'processing' === $order->get_status() );

			if ( empty( $acct ) ) {
				$order->add_order_note( sprintf( __( 'Attempted to pay out %s to %s, but they do not have their Stripe account connected.' ), $total, get_user_by( 'id', $vendor_id )->display_name ) );
				continue;
			}

			$args = array_merge( $data, [
				'destination' => $acct,
				'amount'      => WC_Stripe_Helper::get_stripe_amount( $total )
				]
			);

			$request   = apply_filters( 'stripe_connect_transfer_args', $args, $response, $order );
			$_response = WC_Stripe_API::request( $request, 'transfers' );

			$monthly_fee = $this->maybe_process_monthly_fee( $vendor_id, $commission['total'] );

			// TODO: Determine if we have a better way of determining success here.
			if ( $_response->id ) {

				if ( $monthly_fee ) {
					$order->add_order_note( sprintf( __( 'Paid monthly fee of %s out of the commission (%s) to %s.' ), $monthly_fee, $total, get_user_by( 'id', $vendor_id )->display_name ) );
					update_user_meta( $vendor_id, date( 'm-Y' ) . '-chamfr-fee', array( 'transfer_id' => $_response->id, 'fee' => $monthly_fee ) );
				}

				$order->add_order_note( sprintf( __( 'Successfully transferred (%s) to %s. Transfer ID#: %s' ), $total, get_user_by( 'id', $vendor_id )->display_name, $_response->id ) );
			}

		}

	}

	/**
	 * If a vendor has not yet had their monthly membership fee
	 *
	 * @param [type] $vendor_id
	 * @return void
	 */
	public function maybe_process_monthly_fee( $vendor_id, $total ) {
		$monthly_fee = scfwc_user_monthly_fee( $vendor_id );

		if ( $monthly_fee >= $total ) {
			return false;
		}

		$month_key = date( 'm-Y' ) . '-chamfr-fee';
		$has_processed_monthly_fee = get_user_meta( $vendor_id, $month_key, true );

		if ( ! empty( $has_processed_monthly_fee ) ) {
			return false;
		}

		return $monthly_fee;
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
			'title'       => __( 'Payout Schedule (Monthly Anchor)', 'woocommerce-gateway-stripe' ),
			'label'       => __( 'Monthly Anchor', 'woocommerce-gateway-stripe' ),
			'type'        => 'text',
			'description' => __( 'Monthly payouts will be made on this day of the month.', 'woocommerce-gateway-stripe' ),
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

		$settings['monthly_fee'] = array(
			'title'       => __( 'Monthly Fee (Active)', 'woocommerce-gateway-stripe' ),
			'label'       => __( 'Default Monthly Fee', 'woocommerce-gateway-stripe' ),
			'type'        => 'text',
			'description' => __( 'This is the default monthly fee that sellers are charged in any month they have a payout.', 'woocommerce-gateway-stripe' ),
			'default'     => '4.25',
			'desc_tip'    => true,
		);

		$settings['passive_monthly_fee'] = array(
			'title'       => __( 'Monthly Fee (Global)', 'woocommerce-gateway-stripe' ),
			'label'       => __( 'Default Monthly Fee', 'woocommerce-gateway-stripe' ),
			'type'        => 'text',
			'description' => __( 'This is the default monthly fee that sellers are charged regardless of any payout.', 'woocommerce-gateway-stripe' ),
			'default'     => '99.00',
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

	public function add_stripe_id_column( $column ) {
		$column['stripe_account_id'] = __( 'Stripe Account ID' );
		return $column;
	}

	public function render_stripe_id_column_data( $val, $column_name, $user_id ) {

		switch ( $column_name ) {
			case 'stripe_account_id' :
				return get_user_meta( $user_id, 'stripe_account_id', true );
				break;
			default:
		}
		return $val;
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
		<tr>
            <th>
                <label for="stripe_account_id"><?php echo esc_html( 'Stripe Account ID' ); ?></label>
            </th>
            <td>
                <input type="text" class="regular-text ltr" id="stripe_account_id" name="stripe_account_id" value="<?php esc_attr_e( $user->stripe_account_id ); ?>"
			   title="<?php esc_attr_e( $data['title'] ); ?>">
            </td>
        </tr>
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
					<option value="<?php esc_attr_e( $name ); ?>" <?php selected( $name, $value, true ); ?>><?php echo esc_html( $val ); ?></option>
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
	 * @return bool Meta ID if the key didn't exist, true on successful update, false on failure.
	 */
	public function add_stripe_connect_fields_to_usermeta( $user_id ) {

	    // check that the current user have the capability to edit the $user_id
	    if ( ! current_user_can( 'edit_user', $user_id ) ) {
	        return false;
	    }

		//
		foreach ( $this->add_connect_settings() as $key => $data ) {

			if ( isset( $_POST[ $key ] ) ) {
				// create/update user meta for the $user_id
				update_user_meta( $user_id, $key, sanitize_text_field( $_POST[ $key ] ) );
			}
		}

		$account_id = get_user_meta( $user_id, 'stripe_account_id', true );

		if ( WCV_Vendors::is_vendor( $user_id ) && ! empty( $account_id ) ) {
			scfwc_update_user_payout_schedule( $user_id );
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
