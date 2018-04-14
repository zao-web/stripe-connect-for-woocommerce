<?php
require_once STRIPE_CONNECT_WC_PATH . 'oauth/vendor/autoload.php';

define("CLIENT_ID"   , "ca_Bg4rkEibrQjMy1TGSuJWNvFCeWMc0Fn2"); // Your client ID: https://dashboard.stripe.com/account/applications/settings
define("REDIRECT_URL", "https://chamfr.local/dashboard"); // https://dashboard.stripe.com/account/applications/settings
define( "SECRET_KEY", 'sk_test_zJMYqEr8cR3BquoYOI0w8SMc' );

\Stripe\Stripe::setApiKey(SECRET_KEY);

// initialize a new generic provider
$provider = new \League\OAuth2\Client\Provider\GenericProvider([
    'clientId'                => CLIENT_ID,
    'clientSecret'            => SECRET_KEY,
    'redirectUri'             => REDIRECT_URL,
    'urlAuthorize'            => 'https://connect.stripe.com/oauth/authorize',
    'urlAccessToken'          => 'https://connect.stripe.com/oauth/token',
    'urlResourceOwnerDetails' => 'https://api.stripe.com/v1/account'
]);

$error = '';

// Check for an authorization code
if (isset($_GET['code'])){
  $code = $_GET['code'];

  // Try to retrieve the access token
  try {
    $accessToken = $provider->getAccessToken('authorization_code',
      ['code' => $_GET['code']
    ]);

    // You could retrieve the API key with `$accessToken->getToken()`, but it's better to authenticate using the Stripe-account header (below)

    // Retrieve the account ID to be used for authentication: https://stripe.com/docs/connect/authentication
    $account_id = $provider->getResourceOwner($accessToken)->getId();

	update_user_meta( get_current_user_id(), 'stripe_account_id', $account_id );

	$token = $oauth->getAccessToken( $_GET['code'] );

	update_user_meta( get_current_user_id(), '_stripe_connect_access_key', $token );

    // Retrieve the account from Stripe: https://stripe.com/docs/api/php#retrieve_account
    $account = \Stripe\Account::Retrieve($account_id);
	$account->payout_schedule = scfwc_get_payout_schedule();
	$account->save();

	wc_add_notice(__( 'Success! Your account has been connected' ) );

  }
  catch (Exception $e){
    $error = $e->getMessage();
  }
}
// Handle errors
elseif (isset($_GET['error'])){
  $error = $_GET['error_description'];
}
// No authorization code -- display an error, etc.
else {
  $error = "No authorization code received";
}

?>
