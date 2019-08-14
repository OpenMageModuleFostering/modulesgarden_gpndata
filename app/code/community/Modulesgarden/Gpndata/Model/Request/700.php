<?php
/* * ********************************************************************
 * Customization Services by ModulesGarden.com
 * Copyright (c) ModulesGarden, INBS Group Brand, All Rights Reserved 
 * (2014-01-28, 09:51:36)
 * 
 *
 *  CREATED BY MODULESGARDEN       ->        http://modulesgarden.com
 *  CONTACT                        ->       contact@modulesgarden.com
 *
 *
 *
 *
 * This software is furnished under a license and may be used and copied
 * only  in  accordance  with  the  terms  of such  license and with the
 * inclusion of the above copyright notice.  This software  or any other
 * copies thereof may not be provided or otherwise made available to any
 * other person.  No title to and  ownership of the  software is  hereby
 * transferred.
 *
 *
 * ******************************************************************** */

/**
 * @author Grzegorz Draganik <grzegorz@modulesgarden.com>
 */

/**
 * 700 - Start Credit Card charge (3DS Enabled)
 */
class Modulesgarden_Gpndata_Model_Request_700 extends Modulesgarden_Gpndata_Model_Request {
	
	protected $_order;
	protected $_info;
	protected $_payment;
	protected $_type = 'authorize_capture'; // authorize|authorize_capture
	protected $_amount;
	protected $_recurringProfile;
	protected $_is3ds = false;
	
	protected $_merchanttransid;
	
	public function setOrder(Mage_Sales_Model_Order $order){
		$this->_order = $order;
	}
	
	public function setInfoInstance(Mage_Payment_Model_Info $info){
		$this->_info = $info;
	}
	
	public function setRecurringProfile(Mage_Payment_Model_Recurring_Profile $profile){
		$this->_recurringProfile = $profile;
	}
	
	public function setPayment($payment){
		$this->_payment = $payment;
	}
	
	public function setAmount($amount){
		$this->_amount = $amount;
	}
	
	public function setIs3ds($bool){
		$this->_is3ds = $bool;
	}
	
	
	public function setType($type){
		if (!in_array($type, array(Modulesgarden_Gpndata_Model_Payment::TYPE_AUTHORIZE, Modulesgarden_Gpndata_Model_Payment::TYPE_AUTHORIZE_CAPTURE)))
			Mage::throwException('Request type not supported');
		$this->_type = $type;
	}
	
	/**
	 * from today
	 *		startDate = ProfileStartDate + frequency
	 *		charge now -> init + recurring
	 * from the future
	 *		startDate = ProfileStartDate
	 *		charge now -> init
	 * 
	 * @return Modulesgarden_Gpndata_Model_Response
	 */
	public function post(){
		$this->_xml->addChild('apiCmd', '700');
		
		$billingAddress = $this->_getBillingAddress();
		$country		= $billingAddress->getCountry();
		$countryiso		= Modulesgarden_Gpndata_Model_Gpndata_Map::getCustomerCountryCode( $country );
		$stateregioniso = Modulesgarden_Gpndata_Model_Gpndata_Map::getCustomerStateCode(
			Modulesgarden_Gpndata_Model_Gpndata_Map::getCustomerCountryId($country),
			$billingAddress->getRegionCode(),
			$billingAddress->getRegion()
		);
		$phone1country	= Modulesgarden_Gpndata_Model_Gpndata_Map::getCustomerCountryPhoneNumber($country);
		$phone_area		= substr($billingAddress->getData("telephone"), 0, 3);
		
		$transaction = $this->_xml->addChild('transaction');
		
		if ($this->_recurringProfile){
			$freq = $this->_recurringProfile->getPeriodFrequency();
			switch ($this->_recurringProfile->getPeriodUnit()){
				case 'day':			$gpndataFreq = $freq . 'd'; break;
				case 'week':		$gpndataFreq = $freq . 'w'; break;
				case 'semi_month':	$gpndataFreq = ($freq * 2) . 'w'; break;
				case 'month':		$gpndataFreq = $freq . 'm'; break;
				case 'year':		$gpndataFreq = $freq . 'y'; break;
			}
			
			if ($this->_isStartDateToday($this->_recurringProfile)){
				$startDate = $this->_calculateStartDate($this->_recurringProfile);
				
			} else { // start date in the future
				$startDate = Mage::getModel('core/date')->date('Y-m-d', strtotime($this->_recurringProfile->getStartDatetime()));
			}

			$rebill = $this->_xml->addChild('rebill');
			$rebill->addChild('freq',			$gpndataFreq); // eg 7d Valid values are: d (days), w (weeks), m (months), y (years)
			$rebill->addChild('start',			$startDate);
			$rebill->addChild('amount',			$this->_recurringProfile->getRecurringAmount());
			$rebill->addChild('desc',			$this->_getRequestDescription());
			if ($this->_recurringProfile->getPeriodMaxCycles())
				$rebill->addChild('count',		$this->_recurringProfile->getPeriodMaxCycles());
//			$rebill->addChild('followup_time',	'');
//			$rebill->addChild('followup_amount','');
			
			$merchantspecific3 = 'rebill';
		}
		
		$transaction->addChild('merchanttransid',	$this->getMerchantTransId());
		$transaction->addChild('amount',			$this->_amount);
		$transaction->addChild('curcode',			$this->_getRequestCurrencyCode());
		$transaction->addChild('statement',			$this->_getRequestDescription());
		$transaction->addChild('description',		$this->_getRequestDescription());
		$transaction->addChild('merchantspecific1',	'');
		$transaction->addChild('merchantspecific2',	'');
		$transaction->addChild('merchantspecific3',	isset($merchantspecific3) ? $merchantspecific3 : '');
		
		$customer = $this->_xml->addChild('customer');
		$customer->addChild('firstname',	$billingAddress->getData("firstname"));
		$customer->addChild('lastname',		$billingAddress->getData("lastname"));
		$customer->addChild('birthday',		'');
		$customer->addChild('birthmonth',	'');
		$customer->addChild('birthyear',	'');
		$customer->addChild('email',		$this->_getEmail());
		$customer->addChild('countryiso',	$countryiso);
		$customer->addChild('stateregioniso',$stateregioniso);
		$customer->addChild('zippostal',	$billingAddress->getData("postcode"));
		$customer->addChild('city',			$billingAddress->getData("city"));
		$customer->addChild('address1',		$billingAddress->getData("street"));
		$customer->addChild('address2',		'');
		$customer->addChild('phone1country',preg_replace("/[^0-9]/","", $phone1country));
		$customer->addChild('phone1area',	preg_replace("/[^0-9]/","", $phone_area));
		$customer->addChild('phone1phone',	preg_replace("/[^0-9]/","", $billingAddress->getData("telephone")));
		$customer->addChild('phone2country','');
		$customer->addChild('phone2area',	'');
		$customer->addChild('phone2phone',	'');
		$customer->addChild('accountid',	$this->_getRequestCustomerId());
		$customer->addChild('ipaddress',	$this->_getRequestRemoteIp());
		
		$creditcard = $this->_xml->addChild('creditcard');
		$creditcard->addChild('ccnumber',			$this->_info->getCcNumber());
		$creditcard->addChild('cccvv',				$this->_info->getCcCid());
		$creditcard->addChild('expmonth',			$this->_info->getCcExpMonth());
		$creditcard->addChild('expyear',			$this->_info->getCcExpYear());
		$creditcard->addChild('nameoncard',			$this->_getRequestCustomerName());
		$creditcard->addChild('billingcountryiso',	$countryiso);
		$creditcard->addChild('billingstateregioniso',$stateregioniso);
		$creditcard->addChild('billingzippostal',	$billingAddress->getData("postcode"));
		$creditcard->addChild('billingcity',		$billingAddress->getData("city"));
		$creditcard->addChild('billingaddress1',	$billingAddress->getData("street"));
		$creditcard->addChild('billingaddress2',	'');
		$creditcard->addChild('billingphone1country',preg_replace("/[^0-9]/","", $phone1country));
		$creditcard->addChild('billingphone1area',	preg_replace("/[^0-9]/","", $phone_area));
		$creditcard->addChild('billingphone1phone',	preg_replace("/[^0-9]/","", $billingAddress->getData("telephone")));
		
		
		$this->_xml->addChild('checksum', $this->calculateChecksum());
		
		/*
		 * Valid values are:
		 * Direct = non- 3DS Charge (Auth/Capture)
		 * Auth = non-3DS Auth Only
		 * 3DS = 3DS Charge (Auth/Capture)
		 * Auth3DS = 3DS Auth Only
		 * 
		 * Note: only those choices available as indicated in the Merchant Profile are accepted.  
		 */
		$auth = $this->_xml->addChild('auth');
		if ($this->_is3ds){
			$auth->addChild('type', $this->_type == Modulesgarden_Gpndata_Model_Payment::TYPE_AUTHORIZE ? 'Auth3DS' : '3DS');
			if ($this->_order) {
				$auth->addChild('sid', $this->_order->getId());
			} elseif ($this->_recurringProfile){
				$auth->addChild('sid', 'p' . $this->_recurringProfile->getProfileId());
			}
			
		} else {
			$auth->addChild('type', $this->_type == Modulesgarden_Gpndata_Model_Payment::TYPE_AUTHORIZE ? 'Auth' : 'Direct');
		}
		
		return parent::post();
	}
	
	public function setMerchantTransId($transid){
		$this->_merchanttransid = $transid;
	}
	
	public function getMerchantTransId(){
		if ($this->_merchanttransid === null){
			$id = $this->_recurringProfile ? $this->_recurringProfile->getId() : $this->_order->getId();
			$this->_merchanttransid = 'gpndata_' . $id . '_' . $this->_payment->getId() . '_' . uniqid();
		}
		return $this->_merchanttransid;
	}
	
	protected function calculateChecksum(){
		return sha1(
			$this->_config['username'] .
			$this->_config['password'] .
			'700' .
			$this->getMerchantTransId() .
			$this->_amount .
			$this->_getRequestCurrencyCode() .
			$this->_info->getCcNumber() .
			$this->_info->getCcCid() .
			$this->_getRequestCustomerName() .
			$this->_config['api_key']
		);
	}
	
	
	/**
	 * GET VALUES BASED ON RECURRING PROFILE OR ORDER
	 */
	
	protected function _getBillingAddress(){
		if ($this->_recurringProfile){
			$address = Mage::getModel('sales/order_address');
			$address->addData( $this->_recurringProfile->getBillingAddressInfo() );
			return $address;
			
		} else {
			return $this->_order->getBillingAddress();
		}
	}
	
	protected function _getEmail(){
		$billingAddress = $this->_getBillingAddress();
		if (!$billingAddress->getData('email')){
			if ($this->_recurringProfile){
				$customer_id = $this->_recurringProfile->getCustomerId();
			} elseif ($this->_order) {
				$customer_id = $this->_order->getCustomerId();
			}
			$customer = Mage::getModel('customer/customer')->load($customer_id);
			$billingAddress->setData('email', $customer->getEmail());
		}
		return $billingAddress->getData('email');
	}
	
	protected function _getRequestCustomerName(){	return $this->_getRequestData('customer_name'); }
	protected function _getRequestRemoteIp(){		return $this->_getRequestData('remote_ip'); }
	protected function _getRequestCurrencyCode(){	return $this->_getRequestData('currency_code'); }
	protected function _getRequestDescription(){	return $this->_getRequestData('description'); }
	protected function _getRequestCustomerId(){		return $this->_getRequestData('customer_id'); }
	
	protected function _getRequestData($key){
		$recProfileOrderInfo = $this->_recurringProfile ? $this->_recurringProfile->getOrderInfo() : null;
		switch ($key){
			case 'remote_ip':
				return isset($recProfileOrderInfo['remote_ip']) ?
					$recProfileOrderInfo['remote_ip'] :
					($this->_order->getRemoteIp() ? $this->_order->getRemoteIp() : $_SERVER['REMOTE_ADDR']);
				
			case 'customer_name':
				return isset($recProfileOrderInfo['customer_firstname']) ? 
					$recProfileOrderInfo['customer_firstname'] . ' ' . $recProfileOrderInfo['customer_lastname'] :
					($this->_order ? $this->_order->getCustomerName() : 'Guest');
				
			case 'customer_id':
				return isset($recProfileOrderInfo['customer_id']) ? 
					$recProfileOrderInfo['customer_id'] :
					($this->_order ? $this->_order->getCustomerId() : uniqid());
				
			case 'currency_code':
				return $this->_recurringProfile ?
					$this->_recurringProfile->getCurrencyCode() :
					$this->_order->getOrderCurrencyCode();
				
			case 'description':
				return $this->_recurringProfile ?
					'Payment for recurring profile #' . $this->_recurringProfile->getId() :
					'Payment for order #' . $this->_order->getId();
		}
	}
	
	protected function _isStartDateToday($profile){
		$start = Mage::getModel('core/date')->date('Y-m-d', strtotime($profile->getStartDatetime()));
		$now = Mage::getModel('core/date')->date('Y-m-d', time());
		
		return $start == $now;
	}
	
	protected function _calculateStartDate($profile){
		$start = Mage::getModel('core/date')->date('Y-m-d', strtotime($profile->getStartDatetime()));
		
		switch ($profile->getPeriodUnit()){
			case 'day':			$added = $profile->getPeriodFrequency() . ' day'; break;
			case 'week':		$added = $profile->getPeriodFrequency() . ' week'; break;
			case 'semi_month':	$added = ($profile->getPeriodFrequency()*2) . ' week'; break;
			case 'month':		$added = $profile->getPeriodFrequency() . ' month'; break;
			case 'year':		$added = $profile->getPeriodFrequency() . ' year'; break;
			default: Mage::throwException('Unable to calculate start date');
		}
		
		return Mage::getModel('core/date')->date('Y-m-d', strtotime($start . ' +' . $added));
	}
	
	/**
	 * @todo move it to the helper
	 * 
	 * @param float $amount
	 * @param bool $asFloat
	 * @return mixed
	 */
	protected function _formatAmount($amount, $asFloat = false){
		$amount = sprintf('%.2F', $amount); // "f" depends on locale, "F" doesn't
		return $asFloat ? (float)$amount : $amount;
	}
	
}

/*
<transaction>
	<apiUser></apiUser>
	<apiPassword></apiPassword>
	<apiCmd>700</apiCmd>
	<transaction>
		<merchanttransid></merchanttransid>
		<amount></amount>
		<curcode></curcode>
		<statement></statement>
		<description></description>
		<merchantspecific1></merchantspecific1>
		<merchantspecific2></merchantspecific2>
		<merchantspecific3></merchantspecific3>
	</transaction>
	<rebill>
		<freq></freq>
		<start>yyyy-mm-dd</start>
		<amount></amount>
		<desc></desc>
		<count></count>
		<followup_time></followup_time>
		<followup_amount></followup_amount>
	</rebill>
	<customer>
		<firstname></firstname>
		<lastname></lastname>
		<birthday></birthday>
		<birthmonth></birthmonth>
		<birthyear></birthyear>
		<email></email>
		<countryiso></countryiso>
		<stateregioniso></stateregioniso>
		<zippostal></zippostal>
		<city></city>
		<address1></address1>
		<address2></address2>
		<phone1country></phone1country>
		<phone1area></phone1area>
		<phone1phone></phone1phone>
		<phone2country></phone2country>
		<phone2area></phone2area>
		<phone2phone></phone2phone>
		<accountid></accountid>
		<ipaddress></ipaddress>
	</customer>
	<creditcard>
		<ccnumber></ccnumber>
		<cccvv></cccvv>
		<expmonth></expmonth>
		<expyear></expyear>
		<nameoncard></nameoncard>
		<billingcountryiso></billingcountryiso>
		<billingstateregioniso></billingstateregioniso>
		<billingzippostal></billingzippostal>
		<billingcity></billingcity>
		<billingaddress1></billingaddress1>
		<billingaddress2></billingaddress2>
		<billingphone1country></billingphone1country>
		<billingphone1area></billingphone1area>
		<billingphone1phone></billingphone1phone>
	</creditcard>
	<checksum></checksum>
	<auth>
		<type></type>
	</auth>
</transaction>
*/