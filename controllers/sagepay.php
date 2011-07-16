<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * THIS IS JUST A DEMO CONTROLLER SHOWING THE WORKFLOW AND BASIC IMPLEMENTATION
 */
class Sagepay extends CI_Controller {

	/**
	 * Returning from 3D secure bank page.
	 */
	public function callbackthreed()
	{
		if( $this->input->post('PaRes') AND $this->input->post('MD') ){

			$this->load->spark('sage-pay-direct/0.0.1');

			$result = $this->sagepay_direct->callback_threed($this->input->post('PaRes'), $this->input->post('MD'));
			var_dump($result);
		}
	}

	/**
	 * Post transaction
	 */
	public function index()
	{
		$this->load->spark('sage-pay-direct/0.0.1');

		$this->sagepay_direct->set_options(
											array('debug' => true)
										  );

		$cc = array(
						'card_holder' => 'pablo b',
						'card_number' => '5404000000000001', //Sandbox Credit Cards can be found here, http://j.mp/r2qmAm
						'cv2' => '123',
						'card_type' => 'MC',
						'expiry_date' => '0617'
					);

		$billing = array(
							'billing_country' => 'UY',
							'billing_post_code' => '11200',
							'billing_surname' => 'Benitez',
							'billing_firstnames' => 'Pablo S',
							'billing_address1' => 'Avda. Dr. Americo Ricaldoni 12345678',
							'billing_city' => 'Montevideo'
						);

		$amount = (float)(rand(1, 5710) .'.'. rand(0,99));

		$result = $this->sagepay_direct->place($amount, $cc, $billing);

		//3D request, we have to redirect customer to the bank
		if( $result['response']['Status'] == '3DAUTH' ){

			echo
				"<html>
					<head><title>Redirecting to bank page</title></head>
					<body onload=\"document.getElementById('frm-threed').submit()\">
						<form name=\"form\" action=\"" . $result['response']['ACSURL'] . "\" method=\"POST\" id=\"frm-threed\"/>
							<input type=\"hidden\" name=\"PaReq\" value=\"" . $result['response']['PAReq'] . "\"/>
							<input type=\"hidden\" name=\"TermUrl\" value=\"" . $result['callback_url'] . "\"/>
							<input type=\"hidden\" name=\"MD\" value=\"" . $result['response']['MD'] . "\"/>
							<noscript>
								<center><p>Please click button below to Authenticate your card</p><input type=\"submit\" value=\"Go\"/></p></center>
							</noscript>
						</form>
					</body>
				</html>";
			exit;

		}else{

			//If no 3D checks are to be performed, based on response Status, just print the transaction result
			var_dump($result);

		}

		exit;
	}
}