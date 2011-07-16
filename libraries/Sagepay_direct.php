<?php defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * CodeIgniter SagePay DIRECT integration library
 *
 * Perform transactions using http://sagepay.com/ DIRECT protocol
 *
 * @package        	CodeIgniter
 * @subpackage    	Libraries
 * @category    	Libraries
 * @author        	Pablo S. Benitez
 * @link			http://getsparks.org/packages/<TODO>/show
 */
class Sagepay_direct
{
	/**
	 * Code Igniter instance
	 * @var object
	 */
	private $_ci;

	/**
	 * Debug flag, you can $this->sagepay_direct->debug = true; or $this->sagepay_direct->set_options(array('debug'=>true));
	 * @var boolean
	 */
	public $debug = false;

	/**
	 * Store configuration data
	 * @var array
	 */
	private $_config = array();

	/**
	 * SagePay NOT valid response codes
	 * @var array
	 */
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

	/**
	 * Options setter
	 * @param array $options
	 * @return void
	 */
    public function set_options($options = array())
    {
		if( count($options) ){
			$this->_config = array_merge($this->_config, $options);

			if( array_key_exists('debug', $options) === TRUE ){
				$this->debug = (bool)$options['debug'];
			}

		}
    }

	/**
	 * Place (register) a transaction to SagePay
	 * @param float $amount Amount to be charged
	 * @param array $cc Credit Card information
	 * @param array $billing Billing information
	 * @param array $shipping OPTIONAL, if not sent $billing will be copied
	 * @param array $additional Here you can add or override information such as Currency, CustomerEMail, Description, etc.
	 */
	public function place($amount, $cc, $billing, $shipping = null, $additional = array())
	{
		// We have to send delivery data to SagePay so if no shipping data is
		// available we just send billing
		if( is_null($shipping) ){
			$shipping = $this->billing_as_shipping($billing);
		}

		// Initialize post array
		$post = array();

		$post ['VPSProtocol'] = $this->_config['protocol_version'];
		$post ['Vendor'] = $this->_config['vendorname'];
		$post ['VendorTxCode'] = $this->_get_vendor_tx_code();
		$post ['TxType'] = $this->_config['payment_type'];
		//Formatted to 2 decimal places with leading digit but no commas or currency symbols
		$post ['Amount'] = number_format($amount, 2, '.', '');
		$post ['Currency'] = $this->_config['currency'];

		// Add customer's IP address only if it is valid
		if( $this->_ci->input->valid_ip($this->_ci->input->ip_address()) ){
			$post ['ClientIPAddress'] = $this->_ci->input->ip_address();
		}

		// 3D checks flag
		$post ['Apply3DSecure'] = $this->_config['threed_checks'];

		// Default is 'E' for Ecommerce
		$post ['AccountType'] = $this->_config['account_type'];

		// Adding CreditCard, Billing and Delivery information
		$post += $this->to_camel_keys($cc);
		$post += $this->to_camel_keys($billing);
		$post += $this->to_camel_keys($shipping);

		// If no description is available, send a dummy one because it's required
		if(!isset($post['Description'])){
			$post['Description'] = 'Please enter description to be sent to Sage Pay.';
		}

		//Description is required
		$post['Description'] = urlencode($post['Description']);

		// If you want to override any post value such as currency send eg. $additional['Currency']='EUR'
		if( count($additional) ){
			array_merge($post, $additional);
		}

		$result = $this->_post_request($post, $this->_get_post_url());

		//3D response
		if( $result['response']['Status'] == '3DAUTH' ){

			$result['callback_url'] = $this->_ci->config->site_url($this->_config['threed_callback_url']);

		}

		return $result;
	}

	/**
	 * Build 3D request post.
	 * @param string $pares PARes value
	 * @param string $md MD value
	 * @return array 3D post result
	 */
	public function callback_threed($pares, $md)
	{
		$post_url = $this->_config[ $this->_config['mode'] . '_threed_post_url' ];

		$data = array();
		$data ['PARes']   = $pares;
		$data ['MD']      = $md;

		$result = $this->_post_request($data, $post_url);

		return $result;
	}

	/**
	 * Return purchase URL for current mode
	 * @return string The purchase url
	 */
	protected function _get_post_url()
	{
		return $this->_config[ $this->_config['mode'] . '_purchase_url' ];
	}

	/**
	 * POST request wrapper
	 * @param array $data Parameters to be posted
	 * @param string $url URL to POST data to
	 * @return array Response information
	 */
	protected function _post_request(array $data, $url)
	{
		// Initialize cURL session
        $this->_ci->curl->create($url);

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

			$debug_request = $data;

			if( array_key_exists('CardNumber', $debug_request) ){
				$debug_request ['CardNumber'] = '******************';
			}
			if( array_key_exists('CV2', $debug_request) ){
				$debug_request ['CV2'] = '***';
			}

			$output ['request']= print_r($debug_request, true);
			$output ['raw_response']= print_r($raw_response, true);

			log_message('debug', print_r($output, true));

		}

		$output ['response'] = $sagepay;

		return $output;
	}

	/**
	 * Camelize array keys
	 * @param array Array to be modified
	 * @param string $prefix To add to keys
	 * @return array Modified array
	 */
	public function to_camel_keys(array $array, $prefix = '')
	{
		$keys = array_keys($array);

		return array_combine(array_map(array($this, 'under_to_camel'), $keys), array_values($array));
	}

	/**
	 * Underscode to camelcase string
	 * @param string $str String to be camelcased
	 * @return string Result string
	 */
	public function under_to_camel($str)
	{
		if($str == 'cv2'){
			return strtoupper($str);
		}

    	return ucfirst( camelize($str) );
	}

	/**
	 * Generate an unique vendor tx code formatted string
	 * @return string Vendor Tx Code (max 40 chars)
	 */
	protected function _get_vendor_tx_code()
	{
		return $this->clean_input( $this->ls((date('d-m-Y_H.i.s') . '_' . random_string('alnum', 10)), 40), 'VendorTxCode' );
	}

	/**
	 * Copy billing information to delivery
	 * @param array Billing data
	 * @return array Shipping data
	 */
	public function billing_as_shipping(array $billing_data)
	{
		$newkeys = array();
		$keys = array_keys($billing_data);
		foreach($keys as $billkey){
			$newkeys []= str_replace('billing_', 'delivery_', $billkey);
		}
		return array_combine($newkeys, array_values($billing_data));
	}

	/**
	 * Cut string to certain $length, will start from position zero
	 * @param string $str String to cut
	 * @param int $length Length
	 * @return string Shorter string
	 */
	public function ls($str, $length)
	{
		return substr($str, 0, $length);
	}

	/**
	 * From SagePay PHP-Integration-Kit.
	 * Filters unwanted characters out of an input string.
	 * @param string $strRawText Text to filter
	 * @param string $strType OPTIONAL, Type of string
	 * @return string Filtered string
	 */
	public function clean_input($strRawText, $strType = '')
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