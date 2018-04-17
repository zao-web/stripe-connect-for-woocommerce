<?php

function scfwc_load_stripe_api() {
	require_once STRIPE_CONNECT_WC_PATH . 'oauth/vendor/autoload.php';
	\Stripe\Stripe::setApiKey(WC_Stripe_API::get_secret_key());
}

function scfwc_get_payout_schedule( $user_id = 0 ) {

	$wc_stripe_settings = get_option( 'woocommerce_stripe_settings', array() );
	$user               = $user_id ? get_user_by( 'id', $user_id ) : wp_get_current_user();
	$payout_schedule    = [];
	$prefix             = 'connect_payout_schedule_';

	// Default to global settings, if user settings are not set.
	foreach ( $wc_stripe_settings as $key => $value ) {

		// We only want to assign payout_schedule variables here.
		if ( false === stristr( $key, $prefix ) ) {
			continue;
		}

		$_key = strtr( $key, array( $prefix => '' ) );

		$user_override = $user->{$key};

		if ( ! empty( $user_override ) ) {
			$payout_schedule[ $_key ] = $user_override;
		} else {
			$payout_schedule[ $_key ] = $value;
		}

	}

	if ( isset( $payout_schedule['interval'] ) && 'daily' === $payout_schedule['interval'] ) {
		unset( $payout_schedule['monthly_anchor'], $payout_schedule['weekly_anchor'] );
	}

	if ( isset( $payout_schedule['interval'] ) && 'weekly' === $payout_schedule['interval'] ) {
		unset( $payout_schedule['monthly_anchor'] );
	}

	if ( isset( $payout_schedule['interval'] ) && 'monthly' === $payout_schedule['interval'] ) {
		unset( $payout_schedule['weekly_anchor'] );
	}

	return apply_filters( 'scfwc_get_payout_schedule', $payout_schedule, $user );
}

function scfwc_get_seller_commission( $user_id = 0 ) {

	$wc_stripe_settings = get_option( 'woocommerce_stripe_settings', array() );
	$user               = $user_id ? get_user_by( 'id', $user_id ) : wp_get_current_user();
	$user_commission    = $user->connect_default_commission;

	$seller_commission = ! empty( $user_commission ) ? $user_commission : $wc_stripe_settings['connect_default_commission'];

	return apply_filters( 'scfwc_get_seller_commission', $seller_commission, $user );
}

function scfwc_update_user_payout_schedule( $user_id = 0 ) {

	scfwc_load_stripe_api();

	$user = $user_id ? get_user_by( 'id', $user_id ) : wp_get_current_user();

    $account                  = \Stripe\Account::Retrieve( $user->stripe_account_id );
	$account->payout_schedule = scfwc_get_payout_schedule( $user->ID );

	return $account->save();
}

function scfwc_user_monthly_fee( $user_id = 0 ) {
	$wc_stripe_settings = get_option( 'woocommerce_stripe_settings', array() );

	$user = $user_id ? get_user_by( 'id', $user_id ) : wp_get_current_user();
	$monthly_fee    = $user->monthly_fee;

	$monthly_fee = ! empty( $monthly_fee ) ? $monthly_fee : $wc_stripe_settings['monthly_fee'];

	return floatval( apply_filters( 'scfwc_user_monthly_fee', $monthly_fee, $user ) );
}

function scfwc_user_global_monthly_fee( $user_id = 0 ) {
	$wc_stripe_settings = get_option( 'woocommerce_stripe_settings', array() );

	$user = $user_id ? get_user_by( 'id', $user_id ) : wp_get_current_user();
	$monthly_fee    = $user->passive_monthly_fee;

	$monthly_fee = ! empty( $monthly_fee ) ? $monthly_fee : $wc_stripe_settings['passive_monthly_fee'];

	return floatval( apply_filters( 'scfwc_user_global_monthly_fee', $monthly_fee, $user ) );
}

function scfwc_get_login_link( $user_id = 0 ) {

	scfwc_load_stripe_api();

	$link = '';

	$user = $user_id ? get_user_by( 'id', $user_id ) : wp_get_current_user();

	if ( empty( $user->stripe_account_id ) ) {
		return $link;
	}

	$account = \Stripe\Account::retrieve( $user->stripe_account_id );
	$link = $account->login_links->create();

	return $link->url;
}

function scfwc_get_authorize_url ( $user_id = 0 ) {
	$user = $user_id ? get_user_by( 'id', $user_id ) : wp_get_current_user();

	$endpoint = 'https://connect.stripe.com/express/oauth/authorize';

	$args = array(
		'redirect_uri' => home_url( 'dashboard' ),
		'client_id'    => CLIENT_ID,
		'state'        => wp_create_nonce( 'stripe-connect' ),
		'stripe_user[email]' => $user->user_email
	);

	return add_query_arg( $args, $endpoint );
}

function scfwc_maybe_charge_monthly_fee( $user_id = 0 ) {
	if ( ! is_user_logged_in() ) {
		return false;
	}

	$user = $user_id ? get_user_by( 'id', $user_id ) : wp_get_current_user();

	// Retrieve account, source ID, associate to customer, save, and create subscription
	if ( empty( $user->stripe_account_id ) ) {
		return false;
	}

	if ( ! WCV_Vendors::is_vendor( $user->ID ) ) {
		return false;
	}

	$global_fee = scfwc_user_global_monthly_fee( $user->ID );


	// Chamfr may set these to be free
	if ( $global_fee < 0.01 ) {
		return true;
	}

	$month_key                 = date( 'm-Y' ) . '-chamfr-global-fee';
	$has_processed_monthly_fee = get_user_meta( $user->ID, $month_key, true );

	if ( $has_processed_monthly_fee ) {
		return true;
	}

	scfwc_load_stripe_api();

	$charge = \Stripe\Charge::create( array(
		"amount"   => WC_Stripe_Helper::get_stripe_amount( $global_fee ),
		"currency" => "usd",
		"source"   => $user->stripe_account_id,
		'description' => 'Monthly fee of $' . $global_fee . ' for ' . $user->display_name
		) );

	update_user_meta( $user->ID, $month_key, $charge->id );

	return true;
}