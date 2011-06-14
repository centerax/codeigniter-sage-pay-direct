<?php

class Sagepay_direct
{
	private $_ci;
	public $debug = false;
	private $_config = array();
	protected $_invalid_response_statuses = array(
													'ERROR',
													'INVALID',
													'REJECTED',
													'MALFORMED',
													'NOTAUTHED',
												 );

	public function __construct()
	{
		$this->_ci =& get_instance();
		log_message('debug', 'SagePay Direct Class Initialized');


		//Load config
		$this->_ci->load->config('sagepay_direct');

		$this->_config = $this->_ci->config->item('sagepay_direct');

		// Load the cURL spark
		$this->_ci->load->spark('curl/1.2.0');

		// Load needed CI helpers
		$this->_ci->load->helper('string');
		$this->_ci->load->helper('inflector');
	}

    public function set_options($options = array())
    {
		if( count($options) ){
			$this->_config = array_merge($this->_config, $options);

			if( array_key_exists('debug', $options) === TRUE ){
				$this->debug = (bool)$options['debug'];
			}

		}
    }

	public function place($amount, $cc, $billing, $shipping = null, $additional = array())
	{
		// We have to send delivery data to SagePay so if no shipping data is
		// available we just send billing
		if( is_null($shipping) ){
			$shipping = $this->billing_as_shipping($billing);
		}

		$post = array();

		$post ['VPSProtocol'] = $this->_config['protocol_version'];
		$post ['Vendor'] = $this->_config['vendorname'];
		$post ['VendorTxCode'] = $this->_get_vendor_tx_code();
		$post ['TxType'] = $this->_config['payment_type'];
		$post ['Amount'] = number_format($amount, 2); //Formatted to 2 decimal places with leading digit but no commas or currency symbols
		$post ['Currency'] = $this->_config['currency'];

		if( $this->_ci->input->valid_ip($this->_ci->input->ip_address()) ){
			$post ['ClientIPAddress'] = $this->_ci->input->ip_address();
		}

		$post ['Apply3DSecure'] = 2;
		$post ['AccountType'] = $this->_config['account_type'];

		$post += $this->to_camel_keys($cc);
		$post += $this->to_camel_keys($billing);
		$post += $this->to_camel_keys($shipping);

		if(!isset($post['Description'])){
			$post['Description'] = 'Please enter description to be sent to Sage Pay.';
		}

		$post['Description'] = urlencode($post['Description']);

		// If you want to override any post value such as currency send eg. $additional['Currency']='EUR'
		if( count($additional) ){
			array_merge($post, $additional);
		}

		return $this->_post_request($post);
	}

	protected function _get_post_url()
	{
		return $this->_config[ $this->_config['mode'] . '_purchase_url' ];
	}

	protected function _post_request($data)
	{
		// Initialize cURL session
        $this->_ci->curl->create($this->_get_post_url());

        // We still want the response even if there is an error code over 400
        $this->_ci->curl->option('failonerror', FALSE);

        // Call the correct method with parameters
        $this->_ci->curl->post($data);

        // Execute and return the response from the REST server
        $raw_response = $this->_ci->curl->execute();

		$output = array();
		$output ['success'] = true;

		if( FALSE === $raw_response ){
			$output ['success'] = false;
			$output ['error_detail'] = 'Fatal error, ' . $this->_ci->curl->error_string;
			return $output;
		}

		//var_dump($this->_ci->curl->info);

		$response = explode(chr(10), $raw_response);

		$sagepay = array();
        // Tokenise the response
        for ($i = 0; $i < count($response); $i++) {
            // Find position of first "=" character
            $splitAt = strpos($response[$i], "=");
            // Create an associative (hash) array with key/value pairs ('trim' strips excess whitespace)
            $arVal = (string) trim(substr($response[$i], ($splitAt + 1)));
            if (!empty($arVal)) {
                $sagepay[trim(substr($response[$i], 0, $splitAt))] = $arVal;
            }
        }

		if( in_array($sagepay['Status'], $this->_invalid_response_statuses) ){
			$output ['success'] = false;
			$output ['error_detail'] = 'Gateway error, ' . $sagepay['StatusDetail'];
		}

		if( TRUE === $this->debug ){
			$output ['request']= $data;
			$output ['raw_response']= $raw_response;
		}

		$output ['response'] = $sagepay;

		return $output;
	}

	public function to_camel_keys(array $array, $prefix = '')
	{
		$keys = array_keys($array);
		//$values = array_map('urlencode', array_values($array));
		return array_combine(array_map(array($this, 'under_to_camel'), $keys), array_values($array));
	}

	public function under_to_camel($str)
	{
		if($str == 'cv2'){
			return strtoupper($str);
		}

    	//return preg_replace_callback('/_([a-z])/', create_function('$c', 'return strtoupper($c[1]);'), ucfirst($str));
    	return ucfirst( camelize($str) );
	}

	protected function _get_vendor_tx_code()
	{
		return $this->clean_input( $this->ls((date('d-m-Y_H.i.s') . '_' . random_string('alnum', 10)), 40), 'VendorTxCode' );
	}

	public function billing_as_shipping(array $billing_data)
	{
		$newkeys = array();
		$keys = array_keys($billing_data);
		foreach($keys as $billkey){
			$newkeys []= str_replace('billing_', 'delivery_', $billkey);
		}
		return array_combine($newkeys, array_values($billing_data));
	}

	public function ls($str, $length)
	{
		return substr($str, 0, $length);
	}

	// Filters unwanted characters out of an input string.  Useful for tidying up FORM field inputs
	function clean_input($strRawText, $strType = '')
	{

		if ($strType=="Number") {
			$strClean="0123456789.";
			$bolHighOrder=false;
		}
		else if ($strType=="VendorTxCode") {
			$strClean="ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_.";
			$bolHighOrder=false;
		}
		else {
	  		$strClean=" ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789.,'/{}@():?-_&£$=%~<>*+\"";
			$bolHighOrder=true;
		}

		$strCleanedText="";
		$iCharPos = 0;

		do
		{
	    	// Only include valid characters
			$chrThisChar=substr($strRawText,$iCharPos,1);

			if (strspn($chrThisChar,$strClean,0,strlen($strClean))>0) {
				$strCleanedText=$strCleanedText . $chrThisChar;
			}
			else if ($bolHighOrder==true) {
					// Fix to allow accented characters and most high order bit chars which are harmless
					if (bin2hex($chrThisChar)>=191) {
						$strCleanedText=$strCleanedText . $chrThisChar;
					}
				}

			$iCharPos=$iCharPos+1;
			}
		while ($iCharPos<strlen($strRawText));

	  	$cleanInput = ltrim($strCleanedText);
		return $cleanInput;

	}

}