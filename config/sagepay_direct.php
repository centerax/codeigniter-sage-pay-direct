<?php

$config ['sagepay_direct']['mode'] = 'simulator';
$config ['sagepay_direct']['vendorname'] = 'ebizmartssp';
$config ['sagepay_direct']['protocol_version'] = '2.23';
$config ['sagepay_direct']['currency'] = 'GBP'; //This has to be enabled on your Sage Pay account
$config ['sagepay_direct']['account_type'] = 'E'; //Ecommerce or Moto (mail/telephone payments)
$config ['sagepay_direct']['payment_type'] = 'PAYMENT'; //PAYMENT, AUTHENTICATE, DEFERRED


if ($config ['sagepay_direct']['mode'] == "live"){
  $config ['sagepay_direct']['live_purchase_url']="https://live.sagepay.com/gateway/service/vspdirect-register.vsp";
  $config ['sagepay_direct']['live_threed_callback_url']="https://live.sagepay.com/gateway/service/direct3dcallback.vsp";
}
elseif ($config ['sagepay_direct']['mode'] == "test")
{
  $config ['sagepay_direct']['test_purchase_url']="https://test.sagepay.com/gateway/service/vspdirect-register.vsp";
  $config ['sagepay_direct']['test_threed_callback_url']="https://test.sagepay.com/gateway/service/direct3dcallback.vsp";
}
else //simulator
{
  $config ['sagepay_direct']['simulator_purchase_url']="https://test.sagepay.com/simulator/VSPDirectGateway.asp";
  $config ['sagepay_direct']['simulator_threed_callback_url']="https://test.sagepay.com/simulator/VSPDirectCallback.asp";
}