<?php
class Gateway {
	private $_config;
	private $_module;
	private $_basket;

	public function __construct($module = false, $basket = false) {
		$this->_module	= $module;
		$this->_basket =& $GLOBALS['cart']->basket;
	}

	##################################################

	public function transfer() {
		$transfer	= array(
			'action'	=> (filter_var($this->_module['payment_page_url'], FILTER_VALIDATE_URL)) ? $this->_module['payment_page_url'] : 'https://gateway.cardstream.com/hosted/',
			'method'	=> 'post',
			'target'	=> '_self',
			'submit'	=> 'auto',
		);
		return $transfer;
	}

	public function repeatVariables() {
		return false;
	}

	public function fixedVariables() {

        $address = '';

        if(isset($this->_basket['billing_address']['line1'])){
            $address .= $this->_basket['billing_address']['line1']."\n";
        }

        if(isset($this->_basket['billing_address']['line2'])){
            $address .= $this->_basket['billing_address']['line2']."\n";
        }

        if(isset($this->_basket['billing_address']['town'])){
            $address .= $this->_basket['billing_address']['town']."\n";
        }



		$hidden	= array(			
			'merchantID' => $this->_module['merchant_id'],
			'merchantPwd' => $this->_module['merchant_password'],
			'amount' => ($this->_basket['total']*100),
			'countryCode' => 826,
			'currencyCode' => 826,
            'action' => 'SALE',
            'type' => 1,
			'transactionUnique' => md5($this->_basket['cart_order_id'].time()),
			'orderRef' => $this->_basket['cart_order_id'],
			'redirectURL' => $GLOBALS['storeURL'].'/index.php?_g=rm&type=gateway&cmd=process&module=CharityClear',
            'customerAddress' => $address,
			'customerPostCode' => $this->_basket['billing_address']['postcode'],
			'customerEmail' => $this->_basket['billing_address']['email'],
			'customerPhone' => $this->_basket['billing_address']['phone'],
            'merchantData' => 'CubeCart-hosted-1'
		);

        if (isset($this->_module['merchant_passphrase'])) {
            $sig_fields = http_build_query($hidden, '', '&') . $this->_module['merchant_passphrase'];
            $hidden['signature'] = hash('SHA512', $sig_fields);
        }


		return (isset($hidden)) ? $hidden : false;
	}

	##################################################

	public function call() {
		return false;
	}

	public function process() {
		
		$order				= Order::getInstance();
		$cart_order_id		= $_POST['orderRef'];
		$order_summary		= $order->getSummary($cart_order_id);

        if (isset($_POST['signature'])) {
            $check = $_POST;
            unset($check['signature']);
            ksort($check);
           	$build_query =  http_build_query($check, '', '&');
				$build_query = preg_replace('/%0D%0A|%0A%0D|%0A|%0D/i', '%0A', $build_query);
				$sig_check = ($_POST['signature'] == hash("SHA512", $build_query. $this->_module['merchant_passphrase']));
           }else{
            $sig_check = true;
        }

        if($_POST['responseCode'] == '0' && $sig_check){
			$order->orderStatus(Order::ORDER_PROCESS, $cart_order_id);
			$order->paymentStatus(Order::PAYMENT_SUCCESS, $cart_order_id);
		}
		
		$transData['notes']			= $sig_check == true ? 'response signature check verified' : 'response signature check failed';
		$transData['gateway']		= 'CardStream';
		$transData['order_id']		= $_POST['orderRef'];
		$transData['trans_id']		= $_POST['xref'];
		$transData['amount']		= ($_POST['amountReceived']>0) ? ($_POST['amountReceived']/100) : '';
		$transData['status']		= $_POST['responseMessage'];
		$transData['customer_id']	= $order_summary['customer_id'];
		$transData['extra']			= '';
		$order->logTransaction($transData);
		
		$url = explode('/modules/gateway/CharityClear',$GLOBALS['storeURL']);

			httpredir($url[0].'/index.php?_a=complete');
			return false;
		// ccNow doesn't send back any data at all right now so we have to leave it pending
		//httpredir(currentPage(array('_g', 'type', 'cmd', 'module'), array('_a' => 'complete')));
	}

	public function form() {
		return false;
	}
}
