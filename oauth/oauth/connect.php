<?php
require_once STRIPE_CONNECT_WC_PATH . 'oauth/vendor/autoload.php';

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

    // Retrieve and save the account ID to be used for authentication: https://stripe.com/docs/connect/authentication
    $account_id = $provider->getResourceOwner($accessToken)->getId();

	update_user_meta( get_current_user_id(), 'stripe_account_id'         , $account_id );
  update_user_meta( get_current_user_id(), '_stripe_connect_access_key', $accessToken );

  scfwc_update_user_payout_schedule();

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
