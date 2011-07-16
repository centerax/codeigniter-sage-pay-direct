<?php defined('BASEPATH') OR exit('No direct script access allowed');

// You can apply for a sim account here http://j.mp/mSVIVk
$config ['sagepay_direct']['mode'] = '';

// SagePay vendorname
$config ['sagepay_direct']['vendorname'] = '';

// Current version is 2.23
$config ['sagepay_direct']['protocol_version'] = '2.23';

//This has to be enabled on your Sage Pay account
$config ['sagepay_direct']['currency'] = 'GBP';

/*
 * Ecommerce (E) or Moto (M) (mail/telephone payments)
	E = Use the e-commerce merchant account (default).
	C = Use the continuous authority merchant account (if present).
	M = Use the mail order, telephone order account (if present).
*/
$config ['sagepay_direct']['account_type'] = 'E';

/*
 * PAYMENT, AUTHENTICATE, DEFERRED
 	@see http://j.mp/pbXEyx
*/
$config ['sagepay_direct']['payment_type'] = 'PAYMENT';

/*
 * 3D callback url
*/
$config ['sagepay_direct']['threed_callback_url'] = 'sagepay/callbackthreed';

/*
 * 3D checks flag
	0 = If 3D-Secure checks are possible and rules allow, perform the checks and apply the
	    authorisation rules (default).
	1 = Force 3D-Secure checks for this transaction only (if your account is 3D-enabled) and apply
		rules for authorisation.
	2 = Do not perform 3D-Secure checks for this transaction only and always authorise.
	3 = Force 3D-Secure checks for this transaction (if your account is 3D-enabled) but ALWAYS obtain
		an auth code, irrespective of rule base.
*/
$config ['sagepay_direct']['threed_checks'] = 0;


if ( $config ['sagepay_direct']['mode'] == "live" ){

  $config ['sagepay_direct']['live_purchase_url'] = "https://live.sagepay.com/gateway/service/vspdirect-register.vsp";
  $config ['sagepay_direct']['live_threed_post_url'] = "https://live.sagepay.com/gateway/service/direct3dcallback.vsp";

}elseif ($config ['sagepay_direct']['mode'] == "test"){

  $config ['sagepay_direct']['test_purchase_url'] = "https://test.sagepay.com/gateway/service/vspdirect-register.vsp";
  $config ['sagepay_direct']['test_threed_post_url'] = "https://test.sagepay.com/gateway/service/direct3dcallback.vsp";

}else{ //SIMULATOR

  $config ['sagepay_direct']['simulator_purchase_url'] = "https://test.sagepay.com/simulator/VSPDirectGateway.asp";
  $config ['sagepay_direct']['simulator_threed_post_url'] = "https://test.sagepay.com/simulator/VSPDirectCallback.asp";

}