<?php
/**
 * Sets up settings for Stripe Connect
 */

define( "CLIENT_ID"   , "ca_Bg4rkEibrQjMy1TGSuJWNvFCeWMc0Fn2"); // Your client ID: https://dashboard.stripe.com/account/applications/settings
define( "REDIRECT_URL", home_url( 'dashboard' ) ); // https://dashboard.stripe.com/account/applications/settings
define( "SECRET_KEY"  , WC_Stripe_API::get_secret_key() );
define( 'CHAMFR_SERVICE_PLAN_ID', 'plan_Ch0F2ExyOKiuXY' );