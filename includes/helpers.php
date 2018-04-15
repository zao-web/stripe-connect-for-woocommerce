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

		$user_settings = $user->payout_schedule;

		if ( ! empty( $user_settings ) && ! empty( $user_settings[ $key ] ) ) {
			$payout_schedule[ $_key ] = $user_settings[ $key ];
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

	error_log( $user->stripe_account_id );

    $account                  = \Stripe\Account::Retrieve( $user->stripe_account_id );
	$account->payout_schedule = scfwc_get_payout_schedule( $user->ID );

	return $account->save();
}

function scfwc_user_monthly_fee( $user_id = 0 ) {
	$wc_stripe_settings = get_option( 'woocommerce_stripe_settings', array() );

	$user = $user_id ? get_user_by( 'id', $user_id ) : wp_get_current_user();
	$monthly_fee    = $user->monthly_fee;

	$monthly_fee = ! empty( $monthly_fee ) ? $monthly_fee : $wc_stripe_settings['monthly_fee'];

	return apply_filters( 'scfwc_user_monthly_fee', $monthly_fee, $user );

}