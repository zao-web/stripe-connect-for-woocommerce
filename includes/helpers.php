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

function scfwc_create_customer( $user_id = 0 ) {
	$user = $user_id ? get_user_by( 'id', $user_id ) : wp_get_current_user();

	// Retrieve account, source ID, associate to customer, save, and create subscription
	if ( empty( $user->stripe_account_id ) ) {
		return false;
	}

	$account   = \Stripe\Account::retrieve( $user->stripe_account_id );
	$source_id = $account->external_accounts->data[0]->id;

	// Create Stripe Customer for seller, in order to create their recurring subscription
	$new_stripe_customer = new WC_Stripe_Customer( get_current_user_id() );
	$customer_id         = $new_stripe_customer->create_customer();

	$customer = \Stripe\Customer::retrieve( $customer_id );
	$customer->source = $source_id;
	$customer->save();

	return \Stripe\Subscription::create( array(
	"customer" => $customer_id,
	"items" => array(
		array(
		"plan" => CHAMFR_SERVICE_PLAN_ID,
		"quantity" => 1,
		),
	) ) );
}